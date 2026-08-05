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

class ServingRequestRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $requester;

    private PaymentUnit $hourUnit;

    private PaymentUnit $usdUnit;

    private WalletModel $ownerWallet;

    private WalletModel $requesterWallet;

    private int $paidTypeId;

    private int $voluntaryTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hourUnit = PaymentUnit::factory()->create(['name' => 'Hour']);
        $this->usdUnit = PaymentUnit::factory()->create(['name' => 'USD']);

        $this->paidTypeId = ServingType::factory()->create(['name' => 'paid'])->id;
        $this->voluntaryTypeId = ServingType::factory()->create(['name' => 'voluntary'])->id;

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
    }

    public function test_paid_serving_with_non_hour_unit_cannot_be_requested(): void
    {
        $serving = $this->createServing($this->paidTypeId, $this->usdUnit->id, 50);

        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $serving->id,
                'automatically_cancel_after' => 14,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Requests are only allowed for paid servings priced in hours or voluntary servings');
    }

    public function test_voluntary_serving_can_be_requested(): void
    {
        $serving = $this->createServing($this->voluntaryTypeId, $this->hourUnit->id, 0);

        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $serving->id,
                'automatically_cancel_after' => 14,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_accepting_voluntary_request_does_not_use_escrow(): void
    {
        $serving = $this->createServing($this->voluntaryTypeId, $this->hourUnit->id, 0);

        $requestResponse = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $serving->id,
                'automatically_cancel_after' => 14,
            ]);

        $requestId = $requestResponse->json('data.id');

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(100, $this->requesterWallet->fresh()->balance);
        $this->assertEquals(0, $this->ownerWallet->fresh()->balance);

        $this->assertDatabaseHas('serving_requests', [
            'id' => $requestId,
            'status' => ServingRequest::STATUS_ACCEPTED,
            'held_amount' => null,
        ]);
    }

    public function test_paid_hour_serving_can_be_requested(): void
    {
        $serving = $this->createServing($this->paidTypeId, $this->hourUnit->id, 50);

        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $serving->id,
                'automatically_cancel_after' => 14,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    private function createServing(int $typeId, int $unitId, int $costAmount): Serving
    {
        return Serving::factory()->create([
            'user_id' => $this->owner->id,
            'serving_type_id' => $typeId,
            'unit_id' => $unitId,
            'cost_amount' => $costAmount,
            'status' => Serving::STATUS_ACTIVE,
        ]);
    }
}
