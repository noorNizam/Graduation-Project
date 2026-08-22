<?php

namespace Tests\Feature;

use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\NotificationModel;
use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Models\ServingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintFilingTest extends TestCase
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

        $unitId = PaymentUnit::factory()->create(['name' => 'Hour'])->id;
        $typeId = ServingType::factory()->create(['name' => 'paid'])->id;
        ServingCategory::factory()->create(['name' => 'Test Category']);
        $this->servingId = Serving::factory()->create([
            'user_id' => $this->accused->id,
            'unit_id' => $unitId,
            'serving_type_id' => $typeId,
        ])->id;
    }

    public function test_user_can_file_a_complaint_against_another_user(): void
    {
        $response = $this->actingAs($this->complainant, 'sanctum')
            ->postJson('/api/complaints', [
                'serving_id' => $this->servingId,
                'accused_user_id' => $this->accused->id,
                'reason' => 'Did not deliver the work',
                'description' => 'The agreed work was never delivered',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $complaint = ComplaintModel::sole();
        $this->assertSame('pending', $complaint->status);
        $this->assertSame($this->complainant->id, $complaint->complainant_id);
        $this->assertNull($complaint->outcome);
        $this->assertNull($complaint->resolved_at);
    }

    public function test_filing_requires_reason_and_accused(): void
    {
        $this->actingAs($this->complainant, 'sanctum')
            ->postJson('/api/complaints', ['description' => 'no reason'])
            ->assertStatus(422);
    }

    public function test_filing_requires_authentication(): void
    {
        $this->postJson('/api/complaints', [
            'serving_id' => $this->servingId,
            'accused_user_id' => $this->accused->id,
            'reason' => 'x',
        ])->assertStatus(401);
    }

    public function test_filing_notifies_accused_and_admins_with_complaint_id(): void
    {
        $this->actingAs($this->complainant, 'sanctum')
            ->postJson('/api/complaints', [
                'serving_id' => $this->servingId,
                'accused_user_id' => $this->accused->id,
                'reason' => 'Did not deliver the work',
            ])
            ->assertStatus(201);

        $complaintId = ComplaintModel::sole()->id;

        $accusedNotification = NotificationModel::where('user_id', $this->accused->id)
            ->where('type', 'complaint_filed')
            ->first();
        $this->assertNotNull($accusedNotification);
        $this->assertSame($complaintId, $accusedNotification->data['complaint_id']);

        $adminNotification = NotificationModel::where('user_id', $this->admin->id)
            ->where('type', 'new_complaint')
            ->first();
        $this->assertNotNull($adminNotification);
        $this->assertSame($complaintId, $adminNotification->data['complaint_id']);
    }

    public function test_filing_stores_optional_attachment(): void
    {
        $this->actingAs($this->complainant, 'sanctum')
            ->postJson('/api/complaints', [
                'serving_id' => $this->servingId,
                'accused_user_id' => $this->accused->id,
                'reason' => 'Did not deliver the work',
                'attachment' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(201);

        $complaint = ComplaintModel::sole();
        $this->assertNotNull($complaint->attachment_path);
        $this->assertStringStartsWith('complaints/', $complaint->attachment_path);
        Storage::disk('public')->assertExists($complaint->attachment_path);
    }
}
