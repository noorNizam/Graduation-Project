<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Chat;
use App\Infrastructure\Models\ComplaintDocument;
use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\IdentityVerificationModel;
use App\Infrastructure\Models\Message;
use App\Infrastructure\Models\MessageRecipient;
use App\Infrastructure\Models\PenaltyModel;
use App\Infrastructure\Models\RewardModel;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use App\Infrastructure\Models\TopPerformer;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\UserSearchHistory;
use App\Infrastructure\Models\WalletModel;
use App\Infrastructure\Models\WorkGalleryItem;
use App\Infrastructure\Models\WorkGalleryItemFile;
use Carbon\Carbon;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private array $cast = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoDataSeeder::class);

        foreach (User::where('role', 'user')->orderBy('id')->get() as $user) {
            $this->cast[$user->email] = $user->id;
        }
    }

    public function test_seeder_creates_marker_and_renames_cast_users(): void
    {
        $this->assertDatabaseHas('users', ['email' => 'demo.cast@system.com', 'full_name' => 'Demo Data Marker']);
        $this->assertDatabaseHas('users', ['email' => 'user@system.com', 'full_name' => 'أحمد الحلبي']);
        $this->assertDatabaseHas('users', ['email' => 'user10@system.com', 'full_name' => 'مريم عيد']);
        $this->assertSame(0.0, (float) WalletModel::where('user_id', $this->cast['user8@system.com'])->value('balance'));
        $this->assertSame(250.0, (float) WalletModel::where('user_id', $this->cast['user10@system.com'])->value('balance'));
    }

    public function test_seeder_is_guarded_against_duplicate_runs(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(15, Serving::count());
        $this->assertSame(19, ServingRequest::count());
        $this->assertSame(9, ComplaintModel::count());
        $this->assertSame(33, Message::count());
    }

    public function test_seeder_covers_serving_types_statuses_and_display_types(): void
    {
        $this->assertSame(13, Serving::where('status', 'active')->count());
        $this->assertSame(2, Serving::where('status', 'inactive')->count());

        $paidTypeId = DB::table('serving_types')->where('name', 'paid')->value('id');
        $voluntaryTypeId = DB::table('serving_types')->where('name', 'voluntary')->value('id');
        $hourUnitId = DB::table('payment_units')->where('name', 'Hour')->value('id');
        $usdUnitId = DB::table('payment_units')->where('name', 'USD')->value('id');
        $sypUnitId = DB::table('payment_units')->where('name', 'SYP')->value('id');

        $this->assertSame(7, Serving::where('serving_type_id', $paidTypeId)->where('unit_id', $hourUnitId)->count());
        $this->assertSame(4, Serving::where('serving_type_id', $paidTypeId)->where('unit_id', $usdUnitId)->count());
        $this->assertSame(2, Serving::where('serving_type_id', $paidTypeId)->where('unit_id', $sypUnitId)->count());
        $this->assertSame(2, Serving::where('serving_type_id', $voluntaryTypeId)->count());

        $this->assertSame(3, Serving::whereNotNull('image_url')->count());

        $this->assertSame('exchanged', Serving::with('servingType', 'unit')->where('title', 'سباكة وتمديدات منزلية')->first()->display_type);
        $this->assertSame('paid', Serving::with('servingType', 'unit')->where('title', 'تصميم هوية بصرية')->first()->display_type);
        $this->assertSame('voluntary', Serving::with('servingType', 'unit')->where('title', 'ترجمة مستندات Documents Translation')->first()->display_type);
    }

    public function test_seeder_covers_all_request_statuses_and_escrow_states(): void
    {
        $statusCounts = ServingRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $this->assertSame(3, $statusCounts['pending'] ?? 0);
        $this->assertSame(5, $statusCounts['accepted'] ?? 0);
        $this->assertSame(2, $statusCounts['completion_requested'] ?? 0);
        $this->assertSame(7, $statusCounts['completed'] ?? 0);
        $this->assertSame(1, $statusCounts['canceled'] ?? 0);
        $this->assertSame(1, $statusCounts['rejected'] ?? 0);

        $this->assertSame(6, ServingRequest::whereNotNull('held_amount')->count());

        $autoCancelCandidate = ServingRequest::where('status', 'accepted')
            ->where('automatically_cancel_after', 7)
            ->where('updated_at', '<=', Carbon::now()->subDays(7))
            ->first();
        $this->assertNotNull($autoCancelCandidate);

        $revisionCandidate = ServingRequest::where('status', 'accepted')->where('revision_count', 1)->first();
        $this->assertNotNull($revisionCandidate);

        $autoCompleteCandidate = ServingRequest::where('status', 'completion_requested')
            ->where('completion_requested_at', '<=', Carbon::now()->subDays(2))
            ->whereNotNull('held_amount')
            ->first();
        $this->assertNotNull($autoCompleteCandidate);

        $voluntaryAccepted = ServingRequest::where('status', 'accepted')->whereNull('held_amount')->first();
        $this->assertNotNull($voluntaryAccepted);

        $currentMonthCompletions = ServingRequest::where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->startOfMonth())
            ->count();
        $this->assertSame(6, $currentMonthCompletions);

        $previousMonthCompletion = ServingRequest::where('status', 'completed')
            ->where('completed_at', '<', Carbon::now()->startOfMonth())
            ->first();
        $this->assertNotNull($previousMonthCompletion);
    }

    public function test_seeder_covers_all_complaint_states_and_penalties(): void
    {
        $statusCounts = ComplaintModel::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $this->assertSame(1, $statusCounts['pending'] ?? 0);
        $this->assertSame(4, $statusCounts['awaiting_documents'] ?? 0);
        $this->assertSame(1, $statusCounts['under_review'] ?? 0);
        $this->assertSame(3, $statusCounts['resolved'] ?? 0);
        // 'rejected' left the complaint vocabulary entirely.
        $this->assertArrayNotHasKey('rejected', $statusCounts);

        $unjustified = ComplaintModel::where('reason', 'شكوى غير مبررة')->first();
        $this->assertNotNull($unjustified);
        $this->assertSame('resolved', $unjustified->status);
        $this->assertSame('unjustified', $unjustified->outcome);

        $noDeadline = ComplaintModel::where('status', 'awaiting_documents')->whereNull('documents_due_at')->first();
        $this->assertNotNull($noDeadline);

        $waitingForParty = ComplaintModel::where('status', 'awaiting_documents')
            ->whereNotNull('documents_due_at')
            ->where('documents_due_at', '>', Carbon::now())
            ->where('complainant_documents_uploaded', true)
            ->where('accused_documents_uploaded', false)
            ->first();
        $this->assertNotNull($waitingForParty);

        $expiredSweepCandidate = ComplaintModel::where('status', 'awaiting_documents')
            ->where('documents_due_at', '<', Carbon::now())
            ->where('complainant_documents_uploaded', true)
            ->first();
        $this->assertNotNull($expiredSweepCandidate);

        $accused = $this->cast['user9@system.com'];
        $this->assertSame(2, ComplaintModel::where('accused_user_id', $accused)->where('status', 'resolved')->count());
        $this->assertSame(2, PenaltyModel::where('user_id', $accused)->where('type', 'deduct_hours')->count());
        $this->assertSame(1, PenaltyModel::where('user_id', $accused)->where('type', 'warning')->count());

        $this->assertSame(5, ComplaintDocument::count());

        $damageComplaint = ComplaintModel::where('complainant_id', $this->cast['user10@system.com'])
            ->where('status', 'awaiting_documents')
            ->where('documents_due_at', '<', Carbon::now())
            ->first();
        $this->assertNotNull($damageComplaint);
        $this->assertSame(2, ComplaintDocument::where('complaint_id', $damageComplaint->id)->where('uploader_role', 'complainant')->count());
        $this->assertTrue(Storage::disk('public')->exists($damageComplaint->documents()->first()->stored_path));

        $underReview = ComplaintModel::where('status', 'under_review')->first();
        $this->assertSame(1, ComplaintDocument::where('complaint_id', $underReview->id)->where('uploader_role', 'complainant')->count());
        $this->assertSame(1, ComplaintDocument::where('complaint_id', $underReview->id)->where('uploader_role', 'accused')->count());
    }

    public function test_seeder_covers_reward_thresholds_and_counters(): void
    {
        $sara = User::find($this->cast['user2@system.com']);
        $this->assertSame(12, $sara->services_requested_count);
        $this->assertSame(3, $sara->weekly_service_count);
        $this->assertSame(10, $sara->monthly_service_count);

        $rewardTypes = RewardModel::where('user_id', $sara->id)->pluck('type')->all();
        sort($rewardTypes);
        $this->assertSame(['lifetime', 'lifetime', 'monthly', 'weekly'], $rewardTypes);

        $this->assertSame(4, RewardModel::where('user_id', $sara->id)->count());
        $this->assertSame(2, RewardModel::where('user_id', $this->cast['user@system.com'])->count());
        $this->assertSame(1, RewardModel::where('user_id', $this->cast['user3@system.com'])->count());
    }

    public function test_seeder_creates_search_history_within_thirty_days(): void
    {
        $this->assertSame(16, UserSearchHistory::count());
        $this->assertSame(16, UserSearchHistory::where('searched_at', '>=', Carbon::now()->subDays(30))->count());
        $this->assertNotNull(UserSearchHistory::where('query', 'توصيل؟')->first());
        $this->assertNotNull(UserSearchHistory::where('query', 'web development')->first());
    }

    public function test_seeder_creates_work_gallery_with_and_without_files(): void
    {
        $this->assertSame(8, WorkGalleryItem::count());
        $this->assertSame(11, WorkGalleryItemFile::count());
        $this->assertSame(3, WorkGalleryItem::doesntHave('files')->count());

        $item = WorkGalleryItem::where('title', 'ملف أعمال ترجمة')->first();
        $this->assertSame('application/pdf', $item->files->first()->file_type);

        $this->assertTrue(Storage::disk('public')->exists('servings/demo/plumbing.png'));
        $this->assertTrue(Storage::disk('public')->exists('work_gallery/demo'));
    }

    public function test_seeder_creates_chats_and_encrypts_messages_at_rest(): void
    {
        $this->assertSame(2, Chat::where('type', 'personal')->count());
        $this->assertSame(1, Chat::where('type', 'group')->count());
        $this->assertSame(33, Message::count());
        $this->assertSame(63, MessageRecipient::count());

        $raw = DB::table('messages')->where('sender_id', $this->cast['user@system.com'])->first()->content;
        $this->assertNotSame('مرحباً عمر، جاهز لتركيب الشبكة السبت؟', $raw);
        $this->assertGreaterThan(50, strlen($raw));
    }

    public function test_seeder_creates_identity_verification_states(): void
    {
        $this->assertSame(1, IdentityVerificationModel::where('status', 'approved')->count());
        $this->assertSame(1, IdentityVerificationModel::where('status', 'declined')->count());
        $this->assertSame(1, IdentityVerificationModel::where('status', 'pending')->count());

        $this->assertSame(1, (int) User::where('email', 'user2@system.com')->value('is_identity_verified'));
        $this->assertSame(0, (int) User::where('email', 'user3@system.com')->value('is_identity_verified'));
    }

    public function test_seeder_creates_top_performer_rows_for_previous_and_current_month(): void
    {
        $lastMonth = Carbon::now()->subMonthNoOverflow()->endOfMonth()->setTime(23, 0, 0);
        $this->assertSame(5, TopPerformer::where('date', $lastMonth)->count());

        $currentMonth = Carbon::now()->startOfMonth();
        $this->assertSame(6, TopPerformer::where('date', '>=', $currentMonth)->count());
    }

    public function test_cleanup_command_removes_all_demo_data(): void
    {
        $documentPath = ComplaintDocument::first()->stored_path;
        $documentComplaintId = ComplaintDocument::first()->complaint_id;

        $this->artisan('demo:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'demo.cast@system.com']);
        $this->assertSame(0, Serving::count());
        $this->assertSame(0, ServingRequest::count());
        $this->assertSame(0, ComplaintModel::count());
        $this->assertSame(0, ComplaintDocument::count());
        $this->assertSame(0, PenaltyModel::count());
        $this->assertSame(0, RewardModel::count());
        $this->assertSame(0, UserSearchHistory::count());
        $this->assertSame(0, WorkGalleryItem::count());
        $this->assertSame(0, WorkGalleryItemFile::count());
        $this->assertSame(0, Chat::count());
        $this->assertSame(0, Message::count());
        $this->assertSame(0, MessageRecipient::count());
        $this->assertSame(0, IdentityVerificationModel::count());
        $this->assertSame(0, TopPerformer::count());

        $this->assertDatabaseHas('users', ['email' => 'user@system.com', 'full_name' => 'systemUser']);
        $this->assertDatabaseHas('users', ['email' => 'user2@system.com', 'full_name' => 'systemUser2']);

        $this->assertSame(0.0, (float) WalletModel::where('user_id', $this->cast['user10@system.com'])->value('balance'));
        $this->assertFalse(Storage::disk('local')->exists('manifests/demo-seed-manifest.json'));
        $this->assertFalse(Storage::disk('public')->exists('servings/demo'));
        $this->assertFalse(Storage::disk('public')->exists('work_gallery/demo'));
        $this->assertFalse(Storage::disk('public')->exists($documentPath));
        $this->assertFalse(Storage::disk('public')->exists('complaints/documents/'.$documentComplaintId));
    }

    public function test_cleanup_command_fails_without_manifest_to_protect_real_data(): void
    {
        Storage::disk('local')->delete('manifests/demo-seed-manifest.json');

        $this->artisan('demo:cleanup')->assertExitCode(1);

        $this->assertDatabaseHas('users', ['email' => 'demo.cast@system.com']);
        $this->assertSame(15, Serving::count());
    }

    public function test_cleanup_force_recovers_scope_without_manifest(): void
    {
        Storage::disk('local')->delete('manifests/demo-seed-manifest.json');

        $this->artisan('demo:cleanup --force')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'demo.cast@system.com']);
        $this->assertSame(0, Serving::count());
        $this->assertSame(0, ServingRequest::count());
        $this->assertSame(0, ComplaintModel::count());
        $this->assertSame(0, ComplaintDocument::count());
        $this->assertSame(0, PenaltyModel::count());
        $this->assertSame(0, RewardModel::count());
        $this->assertSame(0, UserSearchHistory::count());
        $this->assertSame(0, WorkGalleryItem::count());
        $this->assertSame(0, WorkGalleryItemFile::count());
        $this->assertSame(0, Chat::count());
        $this->assertSame(0, Message::count());
        $this->assertSame(0, MessageRecipient::count());
        $this->assertSame(0, IdentityVerificationModel::count());
        $this->assertSame(0, TopPerformer::count());

        $this->assertDatabaseHas('users', ['email' => 'user2@system.com', 'full_name' => 'systemUser2']);
        $this->assertSame(0.0, (float) WalletModel::where('user_id', $this->cast['user10@system.com'])->value('balance'));
    }
}
