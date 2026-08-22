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
use Illuminate\Support\Facades\DB;
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
            ->assertJsonPath('data.decision.message', 'Waiting for the other party to upload documents');

        $complaint->refresh();
        $this->assertSame('awaiting_documents', $complaint->status);
        $this->assertTrue((bool) $complaint->complainant_documents_uploaded);
        $this->assertFalse((bool) $complaint->accused_documents_uploaded);
    }

    public function test_both_uploads_move_to_under_review_regardless_of_deadline()
    {
        // Even with the deadline long past, uploads are pure bookkeeping:
        // both parties responding moves the case to review, nothing more.
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->subDay();
        $complaint->save();

        $this->uploadAs($this->complainant, $complaint->id)
            ->assertOk()
            ->assertJsonPath('data.decision.message', 'Waiting for the other party to upload documents');

        $this->uploadAs($this->accused, $complaint->id)
            ->assertOk()
            ->assertJsonPath('data.decision.message', 'Both parties uploaded documents, moving to under review');

        $complaint->refresh();
        $this->assertSame('under_review', $complaint->status);
        $this->assertNull($complaint->outcome);
    }

    public function test_late_single_upload_never_penalizes_or_closes_the_complaint()
    {
        // Deadlines are advisory: a party responding after it lapses just
        // records their documents. Resolution stays an admin decision.
        $complainantLate = $this->makeComplaint('awaiting_documents');
        $complainantLate->documents_due_at = Carbon::now()->subDay();
        $complainantLate->save();

        $this->uploadAs($this->complainant, $complainantLate->id)->assertOk();

        $complainantLate->refresh();
        $this->assertSame('awaiting_documents', $complainantLate->status);
        $this->assertNull($complainantLate->outcome);
        $this->assertNull($complainantLate->resolved_at);

        $accusedLate = $this->makeComplaint('awaiting_documents');
        $accusedLate->documents_due_at = Carbon::now()->subDay();
        $accusedLate->save();

        $this->uploadAs($this->accused, $accusedLate->id)->assertOk();

        $accusedLate->refresh();
        $this->assertSame('awaiting_documents', $accusedLate->status);
        $this->assertNull($accusedLate->outcome);
        $this->assertSame(0, \App\Infrastructure\Models\PenaltyModel::count());
    }

    public function test_upload_without_deadline_still_records_documents()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = null;
        $complaint->save();

        $this->uploadAs($this->complainant, $complaint->id)
            ->assertOk()
            ->assertJsonPath('data.decision.message', 'Waiting for the other party to upload documents');

        $complaint->refresh();
        $this->assertSame('awaiting_documents', $complaint->status);
        $this->assertTrue((bool) $complaint->complainant_documents_uploaded);
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

    public function test_expired_complaint_sweep_command_no_longer_exists()
    {
        // Complaint resolution is manual-only: the scheduled sweep that used
        // to auto-decide expired-deadline complaints was removed entirely.
        $this->assertArrayNotHasKey('complaints:resolve-expired', \Illuminate\Support\Facades\Artisan::all());
    }

    public function test_upload_persists_document_rows_with_metadata()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->addDays(3);
        $complaint->save();

        $this->uploadAs($this->complainant, $complaint->id)->assertOk();

        $this->assertDatabaseHas('complaint_documents', [
            'complaint_id' => $complaint->id,
            'uploader_id' => $this->complainant->id,
            'uploader_role' => 'complainant',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10240,
        ]);

        $storedPath = DB::table('complaint_documents')->where('complaint_id', $complaint->id)->value('stored_path');
        $this->assertStringStartsWith('complaints/documents/'.$complaint->id.'/', $storedPath);
    }

    public function test_accused_upload_records_accused_role()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->addDays(3);
        $complaint->save();

        $this->uploadAs($this->accused, $complaint->id)->assertOk();

        $this->assertDatabaseHas('complaint_documents', [
            'complaint_id' => $complaint->id,
            'uploader_id' => $this->accused->id,
            'uploader_role' => 'accused',
            'original_name' => 'doc.pdf',
        ]);
    }

    public function test_multiple_files_create_one_row_per_file()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->addDays(3);
        $complaint->save();

        $this->actingAs($this->complainant, 'sanctum')->postJson("/api/complaints/{$complaint->id}/upload-documents", [
            'documents' => [
                UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('second.png', 20, 'image/png'),
            ],
        ])->assertOk();

        $this->assertDatabaseCount('complaint_documents', 2);
        $this->assertDatabaseHas('complaint_documents', ['complaint_id' => $complaint->id, 'original_name' => 'first.pdf']);
        $this->assertDatabaseHas('complaint_documents', ['complaint_id' => $complaint->id, 'original_name' => 'second.png']);
    }

    public function test_admin_show_returns_uploaded_documents()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->addDays(3);
        $complaint->save();

        $this->uploadAs($this->complainant, $complaint->id)->assertOk();

        $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/complaints/{$complaint->id}")
            ->assertOk()
            ->assertJsonPath('data.documents.0.original_name', 'doc.pdf')
            ->assertJsonPath('data.documents.0.uploader_role', 'complainant')
            ->assertJsonPath('data.documents.0.uploader_id', $this->complainant->id)
            ->assertJsonPath('data.documents.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.documents.0.url', fn ($url) => str_contains($url, '/storage/complaints/documents/'.$complaint->id.'/'));
    }

    public function test_admin_index_includes_documents_per_complaint()
    {
        $withDocs = $this->makeComplaint('awaiting_documents');
        $withDocs->documents_due_at = Carbon::now()->addDays(3);
        $withDocs->save();
        $this->uploadAs($this->complainant, $withDocs->id)->assertOk();

        $withoutDocs = $this->makeComplaint('awaiting_documents');
        $withoutDocs->documents_due_at = Carbon::now()->addDays(3);
        $withoutDocs->save();

        DB::enableQueryLog();

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/complaints')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $documentQueries = collect(DB::getQueryLog())
            ->filter(fn ($log) => str_contains($log['query'], 'complaint_documents'))
            ->count();
        $this->assertSame(1, $documentQueries, 'documents must be eager-loaded in a single query');

        $withoutDocsItem = collect($response->json('data'))->firstWhere('id', $withoutDocs->id);
        $this->assertSame([], $withoutDocsItem['documents']);

        $withDocsItem = collect($response->json('data'))->firstWhere('id', $withDocs->id);
        $this->assertCount(1, $withDocsItem['documents']);
        $this->assertSame('doc.pdf', $withDocsItem['documents'][0]['original_name']);
    }

    public function test_user_show_includes_uploaded_documents()
    {
        $complaint = $this->makeComplaint('awaiting_documents');
        $complaint->documents_due_at = Carbon::now()->addDays(3);
        $complaint->save();

        $this->uploadAs($this->complainant, $complaint->id)->assertOk();

        $this->actingAs($this->complainant, 'sanctum')->getJson("/api/complaints/{$complaint->id}")
            ->assertOk()
            ->assertJsonPath('data.documents.0.original_name', 'doc.pdf')
            ->assertJsonPath('data.documents.0.uploader_role', 'complainant');
    }

    public function test_against_me_lists_only_complaints_where_user_is_accused()
    {
        $complaintAgainstAccused = $this->makeComplaint();
        ComplaintModel::create([
            'serving_id' => $this->servingId,
            'complainant_id' => $this->accused->id,
            'accused_user_id' => $this->complainant->id,
            'reason' => 'Reverse complaint',
            'description' => 'Filed by the accused user',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->accused, 'sanctum')->getJson('/api/complaints/against-me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $complaintAgainstAccused->id)
            ->assertJsonPath('meta.total', 1);

        $complainantView = $this->actingAs($this->complainant, 'sanctum')->getJson('/api/complaints/against-me');

        $complainantView->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_complaints_statistics_endpoint_is_not_shadowed_by_id_route()
    {
        $this->makeComplaint();

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/complaints/statistics');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_admin_filter_accepts_awaiting_documents_status()
    {
        $this->makeComplaint('awaiting_documents');
        $this->makeComplaint('pending');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/complaints?status=awaiting_documents')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_cannot_set_rejected_or_expired_statuses()
    {
        // Rejected is gone from the vocabulary and expired is system-assigned
        // only — neither may be chosen manually.
        $complaint = $this->makeComplaint();

        foreach (['rejected', 'expired'] as $status) {
            $this->actingAs($this->admin, 'sanctum')
                ->putJson("/api/admin/complaints/{$complaint->id}/status", ['status' => $status])
                ->assertStatus(422);
        }

        $complaint->refresh();
        $this->assertSame('pending', $complaint->status);
    }
}
