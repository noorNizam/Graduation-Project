<?php

namespace Tests\Feature;

use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $complainant;

    private User $accused;

    private User $admin;

    private int $servingId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->complainant = User::factory()->create(['role' => 'user']);
        $this->accused = User::factory()->create(['role' => 'user']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        WalletModel::factory()->create(['user_id' => $this->accused->id, 'balance' => 5]);

        $unitId = PaymentUnit::factory()->create(['name' => 'Hour'])->id;
        $typeId = ServingType::factory()->create(['name' => 'paid'])->id;
        $this->servingId = Serving::factory()->create([
            'user_id' => $this->accused->id,
            'unit_id' => $unitId,
            'serving_type_id' => $typeId,
        ])->id;
    }

    private function makeComplaint(string $status = 'pending'): ComplaintModel
    {
        return ComplaintModel::create([
            'serving_id' => $this->servingId,
            'complainant_id' => $this->complainant->id,
            'accused_user_id' => $this->accused->id,
            'reason' => 'Test complaint',
            'description' => 'Test description',
            'status' => $status,
        ]);
    }

    private function uploadAs(User $user, int $complaintId): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user, 'sanctum')->postJson(
            "/api/complaints/{$complaintId}/upload-documents",
            ['documents' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]]
        );
    }

    public function test_admin_can_request_documents_and_fields_are_persisted()
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/complaints/{$complaint->id}/status", [
            'status' => 'awaiting_documents',
            'documents_requested_from' => 'both',
            'documents_due_at' => Carbon::now()->addDays(3)->toDateString(),
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $complaint->refresh();
        $this->assertSame('awaiting_documents', $complaint->status);
        $this->assertSame('both', $complaint->documents_requested_from);
        $this->assertNotNull($complaint->documents_due_at);
    }

    public function test_invalid_documents_requested_from_is_rejected()
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/complaints/{$complaint->id}/status", [
            'status' => 'awaiting_documents',
            'documents_requested_from' => 'nobody',
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_by_non_party_is_forbidden()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $stranger = User::factory()->create(['role' => 'user']);

        $this->uploadAs($stranger, $complaint->id)->assertForbidden();
    }

    public function test_upload_when_not_awaiting_documents_is_rejected()
    {
        $complaint = $this->makeComplaint('pending');

        $this->uploadAs($this->complainant, $complaint->id)->assertStatus(422);
    }

    public function test_first_upload_before_deadline_waits_for_the_other_party()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->addDays(3);
        $complaint->save();

        $response = $this->uploadAs($this->complainant, $complaint->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.decision.message', 'Waiting for the other party to upload documents before the deadline');

        $complaint->refresh();
        $this->assertSame('awaiting_documents', $complaint->status);
        $this->assertTrue((bool) $complaint->complainant_documents_uploaded);
        $this->assertFalse((bool) $complaint->accused_documents_uploaded);
    }

    public function test_both_uploads_before_deadline_moves_to_under_review()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->addDays(3);
        $complaint->save();

        $this->uploadAs($this->complainant, $complaint->id)->assertOk();
        $this->uploadAs($this->accused, $complaint->id)->assertOk();

        $complaint->refresh();
        $this->assertSame('under_review', $complaint->status);
    }

    public function test_complainant_only_upload_after_deadline_applies_penalty_and_resolves()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->subDay();
        $complaint->save();

        $response = $this->uploadAs($this->complainant, $complaint->id);

        $response->assertOk()->assertJsonPath('data.decision.message', 'Penalty applied and complaint resolved');

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);

        $this->assertDatabaseHas('penalties', [
            'user_id' => $this->accused->id,
            'complaint_id' => $complaint->id,
            'type' => 'deduct_hours',
            'hours_deducted' => 1,
        ]);
        $this->assertSame(4.0, (float) $this->accused->wallets()->first()->refresh()->balance);
    }

    public function test_accused_only_upload_after_deadline_rejects_complaint()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->subDay();
        $complaint->save();

        $response = $this->uploadAs($this->accused, $complaint->id);

        $response->assertOk()->assertJsonPath('data.decision.message', 'Complaint rejected');

        $complaint->refresh();
        $this->assertSame('rejected', $complaint->status);
    }

    public function test_upload_without_deadline_never_auto_decides()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = null;
        $complaint->save();

        // Updated 30 days ago, but no deadline was set -> stays open
        ComplaintModel::whereKey($complaint->id)->update(['updated_at' => Carbon::now()->subDays(30)]);

        $this->uploadAs($this->complainant, $complaint->id)
            ->assertOk()
            ->assertJsonPath('data.decision.message', 'Waiting for the other party to upload documents');

        $complaint->refresh();
        $this->assertSame('awaiting_documents', $complaint->status);
    }

    public function test_document_request_without_due_date_keeps_deadline_null()
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/complaints/{$complaint->id}/status", [
            'status' => 'awaiting_documents',
            'documents_requested_from' => 'both',
        ])->assertOk();

        $complaint->refresh();
        $this->assertNull($complaint->documents_due_at);
    }

    public function test_unrelated_status_update_does_not_wipe_document_deadline()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_requested_from = 'both';
        $complaint->documents_due_at = Carbon::now()->addDays(3)->toDateString();
        $complaint->save();

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/complaints/{$complaint->id}/status", [
            'status' => 'under_review',
        ])->assertOk();

        $complaint->refresh();
        $this->assertNotNull($complaint->documents_due_at);
    }

    public function test_expired_complaint_sweep_command_resolves_and_skips_unexpired()
    {
        $expired = $this->makeComplaint('awaiting_documents');
        $expired->documents_due_at = Carbon::now()->subDay()->toDateString();
        $expired->complainant_documents_uploaded = true;
        $expired->save();

        $notExpired = $this->makeComplaint('awaiting_documents');
        $notExpired->documents_due_at = Carbon::now()->addDays(3)->toDateString();
        $notExpired->complainant_documents_uploaded = true;
        $notExpired->save();

        $noUploads = $this->makeComplaint('awaiting_documents');
        $noUploads->documents_due_at = Carbon::now()->subDay()->toDateString();
        $noUploads->save();

        $noDeadline = $this->makeComplaint('awaiting_documents');
        $noDeadline->documents_due_at = null;
        $noDeadline->complainant_documents_uploaded = true;
        ComplaintModel::whereKey($noDeadline->id)->update(['updated_at' => Carbon::now()->subDays(30)]);
        $noDeadline->refresh();

        $this->artisan('complaints:resolve-expired')->assertSuccessful();

        $this->assertSame('resolved', $expired->fresh()->status);
        $this->assertSame('awaiting_documents', $notExpired->fresh()->status);
        $this->assertSame('awaiting_documents', $noUploads->fresh()->status);
        $this->assertSame('awaiting_documents', $noDeadline->fresh()->status);
    }
}
