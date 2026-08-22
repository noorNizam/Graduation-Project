<?php

namespace Tests\Feature;

use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\PenaltyModel;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Models\ServingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeEscrowFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $requester;

    private User $admin;

    private Serving $serving;

    private WalletModel $ownerWallet;

    private WalletModel $requesterWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $hourUnit = PaymentUnit::factory()->create(['name' => 'Hour']);
        $paidType = ServingType::factory()->create(['name' => 'paid']);
        ServingCategory::factory()->create(['name' => 'Test Category']);

        $this->owner = User::factory()->create(['role' => 'user']);
        $this->requester = User::factory()->create(['role' => 'user']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->ownerWallet = WalletModel::factory()->create([
            'user_id' => $this->owner->id,
            'unit_id' => $hourUnit->id,
            'balance' => 0,
        ]);
        $this->requesterWallet = WalletModel::factory()->create([
            'user_id' => $this->requester->id,
            'unit_id' => $hourUnit->id,
            'balance' => 100,
        ]);

        $this->serving = Serving::factory()->create([
            'user_id' => $this->owner->id,
            'serving_type_id' => $paidType->id,
            'unit_id' => $hourUnit->id,
            'cost_amount' => 50,
            'status' => Serving::STATUS_ACTIVE,
        ]);
    }

    /**
     * Drives the full lifecycle up to a disputed request and returns
     * [requestId, complaintId].
     */
    private function disputedRequest(): array
    {
        $response = $this->actingAs($this->requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $this->serving->id,
                'message' => 'I need your service',
                'automatically_cancel_after' => 14,
            ]);
        $response->assertStatus(201);
        $requestId = $response->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/accept")
            ->assertStatus(200);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/request-completion")
            ->assertStatus(200);

        $response = $this->actingAs($this->requester, 'sanctum')
            ->putJson("/api/servings/requests/{$requestId}/dispute");
        $response->assertStatus(200);

        $complaint = ComplaintModel::where('serving_request_id', $requestId)->first();
        $this->assertNotNull($complaint, 'Opening a dispute must create a complaint');

        return [$requestId, (int) $complaint->id];
    }

    public function test_dispute_refund_justifies_complaint_and_penalizes_owner(): void
    {
        [$requestId, $complaintId] = $this->disputedRequest();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaintId}/status", [
                'status' => 'resolved',
                'outcome' => 'justified',
                'admin_note' => 'Owner did not deliver',
            ])
            ->assertOk();

        // Escrow refunded to the requester.
        $this->assertSame(100.0, (float) $this->requesterWallet->fresh()->balance);
        $this->assertSame('canceled', ServingRequest::find($requestId)->status);

        // Outcome persisted + audit stamps.
        $complaint = ComplaintModel::find($complaintId);
        $this->assertSame('justified', $complaint->outcome);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertSame($this->admin->id, $complaint->resolved_by);

        // Owner penalized: 1 justified resolution -> 1 hour deducted
        // (floored at zero since his balance is 0).
        $penalty = PenaltyModel::where('type', 'deduct_hours')
            ->where('user_id', $this->owner->id)
            ->first();
        $this->assertNotNull($penalty);
        $this->assertSame($complaintId, $penalty->complaint_id);
        $this->assertSame(0.0, (float) $this->ownerWallet->fresh()->balance);
    }

    public function test_dispute_release_marks_complaint_unjustified_and_owner_is_not_penalized(): void
    {
        [$requestId, $complaintId] = $this->disputedRequest();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaintId}/status", [
                'status' => 'resolved',
                'outcome' => 'unjustified',
            ])
            ->assertOk();

        // Escrow released to the owner.
        $this->assertSame(50.0, (float) $this->ownerWallet->fresh()->balance);
        $this->assertSame(50.0, (float) $this->requesterWallet->fresh()->balance);
        $this->assertSame('completed', ServingRequest::find($requestId)->status);

        // Complaint dismissed -> no penalty despite being resolved.
        $this->assertSame('unjustified', ComplaintModel::find($complaintId)->outcome);
        $this->assertSame(0, PenaltyModel::count());
    }

    public function test_resolving_plain_complaint_does_not_touch_funds(): void
    {
        $complaint = ComplaintModel::create([
            'serving_id' => $this->serving->id,
            'complainant_id' => $this->requester->id,
            'accused_user_id' => $this->owner->id,
            'reason' => 'Plain complaint, no dispute link',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'unjustified',
            ])
            ->assertOk();

        // Resolved normally: verdict recorded, wallets untouched.
        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertSame('unjustified', $complaint->outcome);
        $this->assertSame(0.0, (float) $this->ownerWallet->fresh()->balance);
        $this->assertSame(100.0, (float) $this->requesterWallet->fresh()->balance);
    }

    public function test_resolving_link_to_non_disputed_request_leaves_held_funds_untouched(): void
    {
        // A request that was accepted but never disputed: money is held but
        // there is no dispute to settle. Resolution proceeds as a plain
        // complaint without moving the held amount.
        $servingRequest = ServingRequest::create([
            'serving_id' => $this->serving->id,
            'requester_id' => $this->requester->id,
            'message' => 'I need your service',
            'status' => 'completion_requested',
            'held_amount' => 50,
            'automatically_cancel_after' => 14,
        ]);

        $complaint = ComplaintModel::create([
            'serving_id' => $this->serving->id,
            'serving_request_id' => $servingRequest->id,
            'complainant_id' => $this->requester->id,
            'accused_user_id' => $this->owner->id,
            'reason' => 'Linked but not disputed',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'justified',
            ])
            ->assertOk();

        // Verdict recorded, penalty applied...
        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertSame('justified', $complaint->outcome);
        $this->assertSame($this->admin->id, $complaint->resolved_by);
        $this->assertSame(1, PenaltyModel::count());

        // ...but the held funds were NOT released or refunded.
        $this->assertSame(50.0, (float) $servingRequest->fresh()->held_amount);
        $this->assertSame(0.0, (float) $this->ownerWallet->fresh()->balance);
        $this->assertSame(100.0, (float) $this->requesterWallet->fresh()->balance);
    }
}
