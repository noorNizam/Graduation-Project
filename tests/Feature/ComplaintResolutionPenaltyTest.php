<?php

namespace Tests\Feature;

use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\NotificationModel;
use App\Infrastructure\Models\PenaltyModel;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Models\ServingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintResolutionPenaltyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $complainant;

    private User $accused;

    private int $servingId;

    protected function setUp(): void
    {
        parent::setUp();

        ServingCategory::factory()->create(['name' => 'Test Category']);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->complainant = User::factory()->create(['role' => 'user']);
        $this->accused = User::factory()->create(['role' => 'user']);

        $unitId = \App\Infrastructure\Models\PaymentUnit::factory()->create(['name' => 'Hour'])->id;
        $typeId = ServingType::factory()->create(['name' => 'paid'])->id;
        $this->servingId = Serving::factory()->create([
            'user_id' => $this->accused->id,
            'unit_id' => $unitId,
            'serving_type_id' => $typeId,
        ])->id;
    }

    private function makeComplaint(string $status = 'pending', ?string $outcome = null): ComplaintModel
    {
        return ComplaintModel::create([
            'serving_id' => $this->servingId,
            'complainant_id' => $this->complainant->id,
            'accused_user_id' => $this->accused->id,
            'reason' => 'Test complaint',
            'description' => 'Test description',
            'status' => $status,
            'outcome' => $outcome,
        ]);
    }

    private function giveAccusedWallet(float $balance): void
    {
        WalletModel::factory()->create([
            'user_id' => $this->accused->id,
            'balance' => $balance,
        ]);
    }

    private function accusedBalance(): float
    {
        return (float) WalletModel::where('user_id', $this->accused->id)->value('balance');
    }

    private function seedJustifiedResolved(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->makeComplaint('resolved', 'justified');
        }
    }

    public function test_resolve_without_outcome_is_rejected(): void
    {
        $this->giveAccusedWallet(10);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", ['status' => 'resolved'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('outcome');

        // Nothing changed: still pending, no penalties, no audit stamps.
        $this->assertSame('pending', $complaint->fresh()->status);
        $this->assertSame(0, PenaltyModel::count());
        $this->assertNull($complaint->fresh()->resolved_at);

        // A stray escrow field is not accepted as a substitute verdict:
        // the outcome error is still raised.
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'escrow_action' => 'release_to_owner',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outcome']);
    }

    public function test_resolve_unjustified_applies_no_penalty_and_notifies_parties(): void
    {
        $this->giveAccusedWallet(10);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'unjustified',
            ])
            ->assertOk();

        $this->assertSame(0, PenaltyModel::count());
        $this->assertSame(10.0, $this->accusedBalance());

        $accusedNotified = NotificationModel::where('user_id', $this->accused->id)
            ->where('type', 'complaint_status_changed')
            ->first();
        $this->assertNotNull($accusedNotified);
    }

    public function test_first_justified_resolve_deducts_one_hour_and_stamps_audit(): void
    {
        $this->giveAccusedWallet(10);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'justified',
            ])
            ->assertOk();

        $this->assertSame(9.0, $this->accusedBalance());

        $penalty = PenaltyModel::sole();
        $this->assertSame('deduct_hours', $penalty->type);
        $this->assertSame(1, $penalty->hours_deducted);
        $this->assertSame($complaint->id, $penalty->complaint_id);

        $complaint->refresh();
        $this->assertSame('justified', $complaint->outcome);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertSame($this->admin->id, $complaint->resolved_by);
    }

    public function test_second_justified_resolve_issues_warning(): void
    {
        $this->seedJustifiedResolved(1);
        $this->giveAccusedWallet(10);

        // The seeded complaint counts as #1; resolving another makes it #2.
        $this->makeComplaint('resolved', 'unjustified');

        $complaint = $this->makeComplaint();
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'justified',
            ])
            ->assertOk();

        $warning = PenaltyModel::where('type', 'warning')->first();
        $this->assertNotNull($warning, 'Second justified resolution must issue a warning');
    }

    public function test_third_justified_resolve_suspends_for_seven_days(): void
    {
        $this->seedJustifiedResolved(2);
        $this->giveAccusedWallet(10);

        $complaint = $this->makeComplaint();
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'justified',
            ])
            ->assertOk();

        $suspend = PenaltyModel::where('type', 'suspend')->sole();
        $this->assertTrue($suspend->is_active);
        $this->assertNotNull($suspend->expires_at, 'Suspension must carry an expiry date');
        $this->assertSame(
            now()->addDays(7)->format('Y-m-d'),
            $suspend->expires_at->format('Y-m-d')
        );

        $this->accused->refresh();
        $this->assertFalse($this->accused->is_active);
    }

    public function test_unjustified_resolutions_do_not_count_toward_escalation(): void
    {
        // 4 unjustified resolutions exist but none may trigger escalation.
        $this->makeComplaint('resolved', 'unjustified');
        $this->makeComplaint('resolved', 'unjustified');
        $this->makeComplaint('resolved', 'unjustified');

        $this->giveAccusedWallet(10);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'unjustified',
            ])
            ->assertOk();

        $types = PenaltyModel::pluck('type')->all();
        $this->assertNotContains('ban', $types);
        $this->assertNotContains('suspend', $types);
        $this->assertNotContains('warning', $types);
    }

    public function test_escalation_suspension_does_not_steal_an_existing_admin_block(): void
    {
        // Admin blocks the user FIRST, then a justified resolution escalates
        // to suspension. The penalty may keep the account closed, but it must
        // not convert the block into scheduler-owned state.
        $this->accused->update([
            'is_active' => false,
            'block_source' => User::BLOCK_SOURCE_ADMIN,
        ]);

        $this->seedJustifiedResolved(2);
        $this->giveAccusedWallet(0);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'justified',
            ])
            ->assertOk();

        $suspend = PenaltyModel::where('user_id', $this->accused->id)
            ->where('type', 'suspend')
            ->first();
        $this->assertNotNull($suspend, 'Escalation still records the penalty');
        $this->assertTrue($suspend->is_active);

        $this->accused->refresh();
        $this->assertFalse($this->accused->is_active);
        $this->assertSame(
            User::BLOCK_SOURCE_ADMIN,
            $this->accused->block_source,
            'Penalty escalation must not claim ownership of an admin block'
        );

        // When the (shadowed) suspension lapses, the scheduler deactivates
        // the row but leaves the manual block fully intact.
        $suspend->update(['expires_at' => now()->subDay()]);
        $this->artisan('penalties:expire-suspensions')->assertSuccessful();

        $this->assertFalse($suspend->fresh()->is_active);
        $this->accused->refresh();
        $this->assertFalse($this->accused->is_active);
        $this->assertSame(User::BLOCK_SOURCE_ADMIN, $this->accused->block_source);
    }

    public function test_deactivating_a_ban_does_not_lift_an_admin_block(): void
    {
        $this->accused->update([
            'is_active' => false,
            'block_source' => User::BLOCK_SOURCE_ADMIN,
        ]);

        $ban = PenaltyModel::create([
            'user_id' => $this->accused->id,
            'type' => 'ban',
            'reason' => '5th confirmed complaint',
            'is_active' => true,
        ]);

        app(\App\Domain\Services\PenaltyServiceInterface::class)
            ->deactivatePenalty($ban->id);

        $this->assertFalse($ban->fresh()->is_active);
        $this->accused->refresh();
        $this->assertFalse(
            $this->accused->is_active,
            'Deactivating an escalated ban must not free an admin-blocked user'
        );
        $this->assertSame(User::BLOCK_SOURCE_ADMIN, $this->accused->block_source);
    }

    public function test_re_resolving_the_same_complaint_does_not_re_apply_penalties(): void
    {
        $this->giveAccusedWallet(10);
        $complaint = $this->makeComplaint();

        $resolve = fn () => $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/complaints/{$complaint->id}/status", [
                'status' => 'resolved',
                'outcome' => 'justified',
                'admin_note' => 'Updated note',
            ])
            ->assertOk();

        $resolve();

        $penaltyCountAfterFirst = PenaltyModel::count();
        $walletAfterFirst = (float) $this->accused->wallets()->first()->refresh()->balance;
        $this->assertSame(1, $penaltyCountAfterFirst);

        // A second identical PUT (e.g. fixing a typo in the note) is not a
        // new verdict: no additional deductions, no escalation progress.
        $resolve();

        $this->assertSame($penaltyCountAfterFirst, PenaltyModel::count());
        $this->assertSame(
            $walletAfterFirst,
            (float) $this->accused->wallets()->first()->refresh()->balance
        );
    }

    public function test_expiry_command_reactivates_user_after_suspension_ends(): void
    {
        $this->giveAccusedWallet(0);
        User::where('id', $this->accused->id)->update([
            'is_active' => false,
            'block_source' => User::BLOCK_SOURCE_SUSPENSION,
        ]);

        $activeBan = PenaltyModel::create([
            'user_id' => $this->complainant->id,
            'type' => 'ban',
            'is_active' => true,
        ]);

        $expiredSuspend = PenaltyModel::create([
            'user_id' => $this->accused->id,
            'type' => 'suspend',
            'reason' => '7-day suspension due to repeated complaints',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('penalties:expire-suspensions')->assertSuccessful();

        $this->assertFalse($expiredSuspend->fresh()->is_active);
        $this->accused->refresh();
        $this->assertTrue($this->accused->is_active);
        $this->assertNull($this->accused->block_source);

        // Ban without expiry stays untouched and its user stays blocked.
        $this->assertTrue($activeBan->fresh()->is_active);
    }

    public function test_expiry_command_never_lifts_a_manual_admin_block(): void
    {
        // Suspension expired, but afterwards an admin manually blocked the
        // same user for unrelated reasons. The scheduler must leave the
        // manual block alone.
        $expiredSuspend = PenaltyModel::create([
            'user_id' => $this->accused->id,
            'type' => 'suspend',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);
        $this->accused->update([
            'is_active' => false,
            'block_source' => User::BLOCK_SOURCE_ADMIN,
        ]);

        $this->artisan('penalties:expire-suspensions')->assertSuccessful();

        $this->assertFalse($expiredSuspend->fresh()->is_active);
        $this->accused->refresh();
        $this->assertFalse(
            $this->accused->is_active,
            'Manual admin blocks must survive suspension expiry'
        );
        $this->assertSame(User::BLOCK_SOURCE_ADMIN, $this->accused->block_source);
    }

    public function test_expired_suspension_does_not_lift_when_other_active_block_exists(): void
    {
        User::where('id', $this->accused->id)->update(['is_active' => false]);

        PenaltyModel::create([
            'user_id' => $this->accused->id,
            'type' => 'ban',
            'is_active' => true,
        ]);
        $expiredSuspend = PenaltyModel::create([
            'user_id' => $this->accused->id,
            'type' => 'suspend',
            'is_active' => true,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('penalties:expire-suspensions')->assertSuccessful();

        $this->assertFalse($expiredSuspend->fresh()->is_active);
        $this->accused->refresh();
        $this->assertFalse($this->accused->is_active, 'User must stay blocked while an active ban exists');
    }
}
