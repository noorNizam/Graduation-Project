<?php

namespace Tests\Feature;

use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Models\ServingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServingRequestEscrowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $requester;
    private Serving $serving;
    private PaymentUnit $hourUnit;
    private WalletModel $ownerWallet;
    private WalletModel $requesterWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hourUnit = PaymentUnit::factory()->create(['name' => 'Hour']);
        ServingType::factory()->create(['name' => 'paid']);
        $paidType = ServingType::where('name', 'paid')->first();
        ServingCategory::factory()->create(['name' => 'Test Category']);

        $this->owner = User::factory()->create(['role' => 'user']);
        $this->requester = User::factory()->create(['role' => 'user']);

        $this->ownerWallet = WalletModel::factory()->create([
            'user_id' => $this->owner->id,
            'unit_id' => $this->hourUnit->id,
            'balance' => 0,
        ]);
        $this->requesterWallet = WalletModel::factory()->create([
            'user_id' => $this->requester->id,
            'unit_id' => $this->hourUnit->id,
            'balance' => 100,
        ]);

        $this->serving = Serving::factory()->create([
            'user_id' => $this->owner->id,
            'serving_type_id' => $paidType->id,
            'unit_id' => $this->hourUnit->id,
            'cost_amount' => 50,
            'status' => Serving::STATUS_ACTIVE,
        ]);
    }

    public function test_full_completion_flow_escrow(): void
    {
        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'I need your service',
                'automatically_cancel_after' => 14,
            ]);

        $response->assertStatus(201);
        $requestId = $response->json('data.id');

        $this->assertEquals(100, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(0, $this->ownerWallet->fresh()->balance);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertEquals(50, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(0, $this->ownerWallet->fresh()->balance);

        $servingRequest = ServingRequest::find($requestId);
        $this->assertEquals('accepted', $servingRequest->status);
        $this->assertEquals(50, (float) $servingRequest->held_amount);
        $this->assertNotNull($servingRequest->accepted_at);
        $this->assertNotNull($servingRequest->held_at);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/request-completion");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $servingRequest = $servingRequest->fresh();
        $this->assertEquals('completion_requested', $servingRequest->status);
        $this->assertNotNull($servingRequest->completion_requested_at);

        $this->assertEquals(50, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(0, $this->ownerWallet->fresh()->balance);

        $response = $this->actingAs($this->requester, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/confirm-completion");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $servingRequest = $servingRequest->fresh();
        $this->assertEquals('completed', $servingRequest->status);
        $this->assertNotNull($servingRequest->completed_at);
        $this->assertNull($servingRequest->held_amount);
        $this->assertNull($servingRequest->held_at);

        $this->assertEquals(50, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(50, $this->ownerWallet->fresh()->balance);
    }

    public function test_auto_cancel_stale_accepted_request(): void
    {
        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'Test auto-cancel',
                'automatically_cancel_after' => 7,
            ]);
        $requestId = $response->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $this->assertEquals(50, $this->requesterWallet->fresh()->balance);

        $servingRequest = ServingRequest::find($requestId);
        $servingRequest->accepted_at = now()->subDays(8);
        $servingRequest->save();

        $this->artisan('serving-requests:auto-complete')
            ->assertSuccessful();

        $servingRequest = $servingRequest->fresh();
        $this->assertEquals('canceled', $servingRequest->status);
        $this->assertNotNull($servingRequest->canceled_at);
        $this->assertNull($servingRequest->held_amount);
        $this->assertNull($servingRequest->held_at);

        $this->assertEquals(100, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(0, $this->ownerWallet->fresh()->balance);
    }

    public function test_auto_complete_expired_completion_request(): void
    {
        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'Test auto-complete',
                'automatically_cancel_after' => 14,
            ]);
        $requestId = $response->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/request-completion");

        $this->assertEquals(50, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(0, $this->ownerWallet->fresh()->balance);

        $servingRequest = ServingRequest::find($requestId);
        $servingRequest->completion_requested_at = now()->subDays(3);
        $servingRequest->save();

        $this->artisan('serving-requests:auto-complete')
            ->assertSuccessful();

        $servingRequest = $servingRequest->fresh();
        $this->assertEquals('completed', $servingRequest->status);
        $this->assertNotNull($servingRequest->completed_at);
        $this->assertNull($servingRequest->held_amount);
        $this->assertNull($servingRequest->held_at);

        $this->assertEquals(50, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(50, $this->ownerWallet->fresh()->balance);
    }

    public function test_deactivation_rejects_pending_requests_only(): void
    {
        $response1 = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'Pending request',
                'automatically_cancel_after' => 14,
            ]);
        $pendingRequestId = $response1->json('data.id');

        $requester2 = User::factory()->create(['role' => 'user']);
        WalletModel::factory()->create([
            'user_id' => $requester2->id,
            'unit_id' => $this->hourUnit->id,
            'balance' => 100,
        ]);

        $response2 = $this->actingAs($requester2, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'Accepted request',
                'automatically_cancel_after' => 14,
            ]);
        $acceptedRequestId = $response2->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$acceptedRequestId}/accept");

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/servings/{$this->serving->id}/deactivate");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertEquals(Serving::STATUS_INACTIVE, $this->serving->fresh()->status);

        $pendingRequest = ServingRequest::find($pendingRequestId);
        $this->assertEquals('rejected', $pendingRequest->status);

        $acceptedRequest = ServingRequest::find($acceptedRequestId);
        $this->assertEquals('accepted', $acceptedRequest->status);
    }

    public function test_confirm_completion_forbidden_for_owner(): void
    {
        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'Test',
                'automatically_cancel_after' => 14,
            ]);
        $requestId = $response->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/request-completion");

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/confirm-completion");

        $response->assertStatus(403);
    }

    public function test_request_completion_forbidden_for_requester(): void
    {
        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'Test',
                'automatically_cancel_after' => 14,
            ]);
        $requestId = $response->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $response = $this->actingAs($this->requester, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/request-completion");

        $response->assertStatus(403);
    }

    public function test_insufficient_balance_rejects_acceptance(): void
    {
        $poorUser = User::factory()->create(['role' => 'user']);
        WalletModel::factory()->create([
            'user_id' => $poorUser->id,
            'unit_id' => $this->hourUnit->id,
            'balance' => 0,
        ]);

        $response = $this->actingAs($poorUser, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'I have no money',
                'automatically_cancel_after' => 14,
            ]);
        $requestId = $response->json('data.id');

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Insufficient balance to accept this request');
    }

    public function test_pending_confirmations_endpoint(): void
    {
        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'Confirm me',
                'automatically_cancel_after' => 14,
            ]);
        $requestId = $response->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/request-completion");

        $response = $this->actingAs($this->requester, 'sanctum')
            ->getJson('/api/servings/requests/pending-confirmation');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $requestId);
    }
}
