<?php

namespace Database\Seeders;

use App\Domain\Services\TopPerformerServiceInterface;
use App\Infrastructure\Models\Chat;
use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\IdentityVerificationModel;
use App\Infrastructure\Models\Message;
use App\Infrastructure\Models\MessageRecipient;
use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\PenaltyModel;
use App\Infrastructure\Models\RewardModel;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\TopPerformer;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\UserSearchHistory;
use App\Infrastructure\Models\WalletModel;
use App\Infrastructure\Models\WorkGalleryItem;
use App\Infrastructure\Models\WorkGalleryItemFile;
use App\Models\ServingCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const MARKER_EMAIL = 'demo.cast@system.com';

    private const DEMO_PASSWORD = 'P@ssw0rd';

    private const MANIFEST_PATH = 'demo-seed-manifest.json';

    private User $marker;

    private array $cast = [];

    private $hourUnit;

    private array $servingTypes = [];

    private array $categories = [];

    private array $servings = [];

    private array $manifest = [];

    public static function markerEmail(): string
    {
        return self::MARKER_EMAIL;
    }

    public function run(): void
    {
        if (User::where('email', self::MARKER_EMAIL)->exists()) {
            $this->command?->warn('Demo data already seeded. Run `php artisan demo:cleanup` before re-seeding.');

            return;
        }

        DB::transaction(function () {
            $this->setupReferenceData();
            $this->createMarkerAndCast();
            $this->seedWallets();
            $this->seedServings();
            $this->seedRequests();
            $this->seedComplaints();
            $this->seedRewards();
            $this->seedSearchHistory();
            $this->seedWorkGallery();
            $this->seedChats();
            $this->seedIdentityVerifications();
            $this->seedTopPerformers();
        });

        Storage::disk('local')->put(self::MANIFEST_PATH, json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->command?->info('Demo data seeded successfully. Manifest written to storage/app/demo-seed-manifest.json');
    }

    private function setupReferenceData(): void
    {
        $this->hourUnit = PaymentUnit::where('name', PaymentUnit::NAME_HOUR)->firstOrFail();

        foreach (ServingType::all() as $type) {
            $this->servingTypes[$type->name] = $type;
        }

        foreach (ServingCategory::all() as $category) {
            $this->categories[$category->name] = $category;
        }
    }

    private function createMarkerAndCast(): void
    {
        $this->marker = User::create([
            'full_name' => 'Demo Data Marker',
            'email' => self::MARKER_EMAIL,
            'password' => self::DEMO_PASSWORD,
            'phone_number' => '0999000000',
            'role' => User::ROLE_USER,
            'is_active' => true,
            'email_verified_at' => Carbon::now()->subDays(30),
        ]);

        WalletModel::create([
            'user_id' => $this->marker->id,
            'title' => "Demo Data Marker's Wallet",
            'balance' => 0,
            'unit_id' => $this->hourUnit->id,
        ]);

        $castSpecs = [
            'user@system.com' => ['أحمد الحلبي', 'سباك', 'دمشق', 'male', '1990-04-12', '0933111001'],
            'user2@system.com' => ['سارة خليل', 'مدرسة لغة إنجليزية', 'حلب', 'female', '1994-08-23', '0933111002'],
            'user3@system.com' => ['محمد عثمان', 'كهربائي', 'حمص', 'male', '1988-01-30', '0933111003'],
            'user4@system.com' => ['نور الشامي', 'مصممة جرافيك', 'دمشق', 'female', '1996-11-05', '0933111004'],
            'user5@system.com' => ['خالد يوسف', 'مطور ويب', 'اللاذقية', 'male', '1992-06-17', '0933111005'],
            'user6@system.com' => ['ريم قاسم', 'أخصائية تغذية', 'حماة', 'female', '1995-02-09', '0933111006'],
            'user7@system.com' => ['عمر ديب', 'سائق توصيل', 'طرطوس', 'male', '1985-09-21', '0933111007'],
            'user8@system.com' => ['لينا حمدان', 'محاسبة', 'ريف دمشق', 'female', '1993-03-14', '0933111008'],
            'user9@system.com' => ['حسن مراد', 'فني تكييف', 'درعا', 'male', '1987-12-02', '0933111009'],
            'user10@system.com' => ['مريم عيد', 'مصورة فوتوغرافية', 'إدلب', 'female', '1997-07-19', '0933111010'],
        ];

        foreach ($castSpecs as $email => [$fullName, $job, $address, $gender, $birthDate, $phone]) {
            $user = User::where('email', $email)->firstOrFail();
            $this->manifest['original_names'][$email] = $user->full_name;
            $user->update([
                'full_name' => $fullName,
                'current_job' => $job,
                'address' => $address,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'phone_number' => $phone,
            ]);
            $this->cast[$email] = $user;
        }

        $this->manifest['users_renamed'] = array_keys($castSpecs);
        $this->manifest['cast_user_ids'] = array_map(fn (User $u) => $u->id, $this->cast);
        $this->manifest['wallet_reset_user_ids'] = array_merge(array_map(fn (User $u) => $u->id, $this->cast), [$this->marker->id]);
    }

    private function seedWallets(): void
    {
        $balances = [
            'user@system.com' => 25,
            'user2@system.com' => 50,
            'user3@system.com' => 10,
            'user4@system.com' => 2,
            'user5@system.com' => 100,
            'user6@system.com' => 5,
            'user7@system.com' => 8,
            'user8@system.com' => 0,
            'user9@system.com' => 40,
            'user10@system.com' => 250,
        ];

        foreach ($balances as $email => $balance) {
            WalletModel::where('user_id', $this->cast[$email]->id)->update(['balance' => $balance]);
        }
    }

    private function seedServings(): void
    {
        $specs = [
            ['user@system.com', 'سباكة وتمديدات منزلية', 'تركيب وتصليح شبكات المياه والصرف الصحي داخل المنازل والمحلات التجارية.', 'Home Services', 'paid', 'Hour', 10, 4.8, 'active', 'direct', 'plumbing'],
            ['user@system.com', 'فك وتركيب سخانات المياه', 'فك وتركيب سخانات الغاز والكهرباء مع ضمان على العمل لمدة شهر.', 'Home Services', 'paid', 'Hour', 7, 4.5, 'active', 'direct', null],
            ['user2@system.com', 'دروس خصوصية بالإنجليزية', 'دروس لغة إنجليزية لجميع المستويات والمدارس والجامعات حضورياً أو أونلاين.', 'Education & Tutoring', 'paid', 'Hour', 5, 4.9, 'active', 'online', null],
            ['user2@system.com', 'ترجمة مستندات Documents Translation', 'ترجمة المستندات والعقود من العربية إلى الإنجليزية والعكس بجودة عالية.', 'Education & Tutoring', 'voluntary', 'Hour', 0, 0, 'active', 'online', null],
            ['user3@system.com', 'أعمال كهرباء منزلية', 'تمديدات كهربائية وإصلاح الأعطال وتغيير القواطع واللمبات.', 'Home Services', 'paid', 'Hour', 8, 4.7, 'active', 'direct', 'electrical'],
            ['user4@system.com', 'تصميم هوية بصرية', 'تصميم شعارات وهويات بصرية متكاملة للشركات والمشاريع الناشئة.', 'Tech Support', 'paid', 'USD', 50, 5.0, 'active', 'online', null],
            ['user5@system.com', 'تطوير مواقع ويب Web Development', 'بناء مواقع وتطبيقات ويب حديثة ومتجاوبة باستخدام أحدث التقنيات.', 'Tech Support', 'paid', 'USD', 100, 4.6, 'active', 'online', null],
            ['user6@system.com', 'استشارات غذائية Nutrition Consultation', 'خطط غذائية شخصية ومتابعة أسبوعية لتحقيق أهدافك الصحية.', 'Health & Wellness', 'paid', 'SYP', 50000, 4.2, 'active', 'online', null],
            ['user7@system.com', 'توصيل داخل المدينة', 'توصيل الطلبات والطرود داخل المدينة بسرعة وأمان ومواعيد دقيقة.', 'Transportation', 'paid', 'Hour', 3, 3.9, 'active', 'direct', null],
            ['user7@system.com', 'نقل أثاث', 'نقل أثاث المنازل والمكاتب مع التغليف والتركيب في الموقع الجديد.', 'Transportation', 'voluntary', 'Hour', 0, 0, 'active', 'direct', null],
            ['user8@system.com', 'مسك دفاتر ومحاسبة', 'مسك الدفاتر وتجهيز القوائم المالية للمشاريع الصغيرة والمتوسطة.', 'Tech Support', 'paid', 'SYP', 30000, 4.0, 'active', 'online', null],
            ['user9@system.com', 'صيانة تكييف', 'صيانة وتركيب المكيفات وتعبئة الغاز وإصلاح الأعطال بكافة أنواعها.', 'Home Services', 'paid', 'Hour', 12, 4.4, 'active', 'direct', 'ac-maintenance'],
            ['user10@system.com', 'جلسة تصوير احترافية', 'جلسات تصوير شخصية وعائلية ومناسبات بجودة عالية وتسليم سريع.', 'Tech Support', 'paid', 'USD', 80, 4.7, 'active', 'direct', null],
            ['user9@system.com', 'تركيب مطابخ', 'تركيب مطابخ خشبية وألمنيوم مع التوصيل للبيت.', 'Home Services', 'paid', 'Hour', 15, 4.1, 'inactive', 'direct', null],
            ['user5@system.com', 'صيانة شبكات', 'إعداد وصيانة شبكات الحاسب والبنية التحتية للشركات.', 'Tech Support', 'paid', 'USD', 60, 4.3, 'inactive', 'direct', null],
        ];

        foreach ($specs as [$email, $title, $description, $category, $type, $unit, $cost, $rate, $status, $meetingType, $imageKey]) {
            $serving = Serving::create([
                'user_id' => $this->cast[$email]->id,
                'title' => $title,
                'description' => $description,
                'category_id' => $this->categories[$category]->id,
                'serving_type_id' => $this->servingTypes[$type]->id,
                'cost_amount' => $cost,
                'rate' => $rate,
                'unit_id' => PaymentUnit::where('name', $unit)->firstOrFail()->id,
                'location_lat' => 33.5138,
                'location_lng' => 36.2765,
                'location_address' => 'دمشق، سوريا',
                'meeting_type' => $meetingType,
                'status' => $status,
                'created_at' => Carbon::now()->subDays(rand(20, 45)),
                'updated_at' => Carbon::now()->subDays(rand(1, 10)),
            ]);

            if ($imageKey) {
                $path = 'servings/demo/'.$imageKey.'.png';
                $this->makePlaceholderPng($path);
                $serving->update(['image_url' => '/storage/'.$path]);
            }

            $this->servings[] = $serving;
            $this->manifest['servings'][] = $serving->id;
        }
    }

    private function seedRequests(): void
    {
        $specs = [
            ['pending', 'user4@system.com', 0, null, null, null, null, null, null, 14, 0],
            ['pending', 'user8@system.com', 4, null, null, null, null, null, null, 14, 0],
            ['accepted', 'user6@system.com', 8, 3, 1, 14, null, null, null, 14, 0],
            ['accepted', 'user5@system.com', 2, 5, 5, 7, null, null, null, 7, 0],
            ['accepted', 'user10@system.com', 1, 7, 8, 7, null, null, null, 7, 0],
            ['accepted', 'user10@system.com', 4, 8, 8, 7, null, null, null, 7, 1],
            ['completion_requested', 'user9@system.com', 2, 5, 6, 14, 3, null, null, 14, 0],
            ['completion_requested', 'user7@system.com', 0, 10, 3, 14, 1, null, null, 14, 0],
            ['completed', 'user5@system.com', 0, null, null, 14, null, 5, null, 14, 0],
            ['completed', 'user10@system.com', 2, null, null, 14, null, 4, null, 14, 0],
            ['completed', 'user7@system.com', 4, null, null, 14, null, 6, null, 14, 0],
            ['completed', 'user2@system.com', 11, null, null, 14, null, 3, null, 14, 0],
            ['completed', 'user6@system.com', 11, null, null, 14, null, 40, null, 14, 0],
            ['canceled', 'user4@system.com', 8, null, null, 14, null, null, 3, 14, 0],
            ['rejected', 'user8@system.com', 0, null, null, 14, null, null, null, 14, 0],
            ['accepted', 'user10@system.com', 3, null, 2, 14, null, null, null, 14, 0],
            ['completed', 'user6@system.com', 3, null, null, 14, null, 4, null, 14, 0],
            ['completed', 'user5@system.com', 9, null, null, 14, null, 6, null, 14, 0],
            ['pending', 'user8@system.com', 9, null, null, null, null, null, null, 14, 0],
        ];

        foreach ($specs as [$status, $requesterEmail, $servingIndex, $heldAmount, $acceptedDaysAgo, $cancelWindow, $completionRequestedDaysAgo, $completedDaysAgo, $canceledDaysAgo, $autoCancelAfter, $revisionCount]) {
            $serving = $this->servings[$servingIndex];
            $now = Carbon::now();
            $createdAt = $now->subDays($acceptedDaysAgo ?? ($completedDaysAgo ?? ($canceledDaysAgo ?? rand(1, 5))));

            $request = ServingRequest::create([
                'serving_id' => $serving->id,
                'requester_id' => $this->cast[$requesterEmail]->id,
                'message' => $this->requestMessage($status),
                'status' => $status,
                'held_amount' => $heldAmount,
                'held_at' => $acceptedDaysAgo !== null ? Carbon::now()->subDays($acceptedDaysAgo) : null,
                'automatically_cancel_after' => $autoCancelAfter,
                'accepted_at' => $acceptedDaysAgo !== null ? Carbon::now()->subDays($acceptedDaysAgo) : null,
                'completion_requested_at' => $completionRequestedDaysAgo !== null ? Carbon::now()->subDays($completionRequestedDaysAgo) : null,
                'completed_at' => $completedDaysAgo !== null ? Carbon::now()->subDays($completedDaysAgo) : null,
                'canceled_at' => $canceledDaysAgo !== null ? Carbon::now()->subDays($canceledDaysAgo) : null,
                'revision_count' => $revisionCount,
                'created_at' => $createdAt,
                'updated_at' => $completedDaysAgo !== null
                    ? Carbon::now()->subDays($completedDaysAgo)
                    : ($canceledDaysAgo !== null ? Carbon::now()->subDays($canceledDaysAgo) : Carbon::now()->subDays($acceptedDaysAgo ?? 1)),
            ]);

            $this->manifest['serving_requests'][] = $request->id;
        }
    }

    private function requestMessage(string $status): ?string
    {
        return match ($status) {
            'pending' => 'مرحباً، هل يمكنك تنفيذ هذا العمل؟',
            'accepted' => 'أنا مهتم بالخدمة، متى يمكن البدء؟',
            'completed' => 'شكراً لك على العمل الممتاز.',
            default => null,
        };
    }

    private function seedComplaints(): void
    {
        $specs = [
            ['pending', 8, 'user6@system.com', 'user7@system.com', 'تأخر في التوصيل', 'طلب التوصيل تأخر أكثر من ساعتين عن الموعد المتفق عليه.', null, null, false, false, null],
            ['awaiting_documents', 11, 'user8@system.com', 'user9@system.com', 'عمل غير مكتمل', 'لم يكتمل تركيب المكيف بشكل صحيح ويوجد تسريب هواء.', 'both', 3, false, false, 'يرجى تقديم المستندات المطلوبة خلال ثلاثة أيام.'],
            ['awaiting_documents', 4, 'user4@system.com', 'user3@system.com', 'تأخر في التنفيذ', 'التمديدات الكهربائية لم تنجز في الموعد المتفق عليه.', 'complainant', null, false, false, null],
            ['awaiting_documents', 2, 'user5@system.com', 'user2@system.com', 'جودة الدروس', 'الدروس لا تطابق المستوى المتفق عليه في البداية.', 'both', 2, true, false, null],
            ['awaiting_documents', 4, 'user10@system.com', 'user3@system.com', 'ضرر في التمديدات', 'أدى العمل إلى مشكلة في الشبكة الكهربائية بالمنزل.', 'both', -1, true, false, null],
            ['under_review', 0, 'user7@system.com', 'user@system.com', 'جودة السباكة', 'يوجد تسريب في أحد التوصيلات بعد أسبوع من التنفيذ.', 'both', 1, true, true, null],
            ['resolved', 11, 'user6@system.com', 'user9@system.com', 'فقدان قطع', 'لم يتم إرجاع القطع القديمة بعد انتهاء الصيانة.', null, null, false, false, 'تم خصم ساعة من رصيد المشكو منه.'],
            ['resolved', 11, 'user5@system.com', 'user9@system.com', 'تأخير كبير', 'استغرق العمل ثلاثة أيام بدلاً من يوم واحد.', null, null, false, false, 'تم خصم ساعتين وإصدار إنذار للمشكو منه.'],
            ['rejected', 1, 'user7@system.com', 'user@system.com', 'شكوى غير مبررة', 'لا توجد أدلة كافية تثبت وجود المشكلة.', null, null, false, false, 'الأدلة غير كافية لاتخاذ إجراء.'],
        ];

        foreach ($specs as [$status, $servingIndex, $complainantEmail, $accusedEmail, $reason, $description, $requestedFrom, $dueDays, $complainantUploaded, $accusedUploaded, $adminNote]) {
            $complaint = ComplaintModel::create([
                'serving_id' => $this->servings[$servingIndex]->id,
                'complainant_id' => $this->cast[$complainantEmail]->id,
                'accused_user_id' => $this->cast[$accusedEmail]->id,
                'reason' => $reason,
                'description' => $description,
                'status' => $status,
                'admin_note' => $adminNote,
                'documents_requested_from' => $requestedFrom,
                'documents_due_at' => $dueDays !== null ? Carbon::now()->addDays($dueDays) : null,
                'complainant_documents_uploaded' => $complainantUploaded,
                'accused_documents_uploaded' => $accusedUploaded,
                'created_at' => Carbon::now()->subDays(rand(3, 15)),
                'updated_at' => Carbon::now()->subDays(rand(0, 3)),
            ]);

            $this->manifest['complaints'][] = $complaint->id;
        }

        $resolvedAccused = $this->cast['user9@system.com'];
        $complaints = ComplaintModel::where('accused_user_id', $resolvedAccused->id)->where('status', 'resolved')->get();

        $penaltySpecs = [
            ['deduct_hours', 1, 'Deducted 1 hours due to 1 resolved complaints against user'],
            ['deduct_hours', 2, 'Deducted 2 hours due to 2 resolved complaints against user'],
        ];

        foreach ($penaltySpecs as $i => [$type, $hours, $reason]) {
            PenaltyModel::create([
                'user_id' => $resolvedAccused->id,
                'complaint_id' => $complaints[$i]->id,
                'type' => $type,
                'hours_deducted' => $hours,
                'reason' => $reason,
                'is_active' => true,
                'created_at' => Carbon::now()->subDays(6 - $i),
            ]);
        }

        PenaltyModel::create([
            'user_id' => $resolvedAccused->id,
            'complaint_id' => $complaints[1]->id,
            'type' => 'warning',
            'reason' => 'Warning due to repeated complaints (2 resolved complaints)',
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(30),
            'created_at' => Carbon::now()->subDays(4),
        ]);
    }

    private function seedRewards(): void
    {
        $specs = [
            [
                'user2@system.com',
                ['services_requested_count' => 12, 'weekly_service_count' => 3, 'weekly_reset_at' => Carbon::now()->subDays(2), 'monthly_service_count' => 10, 'monthly_reset_at' => Carbon::now()->subDays(5)],
                [
                    ['lifetime', 5, 2, 20, 'Lifetime reward for 5 services (+2 hour)'],
                    ['lifetime', 10, 3, 20, 'Lifetime reward for 10 services (+3 hour)'],
                    ['weekly', 3, 1, 2, 'Weekly reward for 3 services (+1 hour)'],
                    ['monthly', 10, 3, 5, 'Monthly reward for 10 services this month (+3 hour)'],
                ],
            ],
            [
                'user@system.com',
                ['services_requested_count' => 6, 'weekly_service_count' => 3, 'weekly_reset_at' => Carbon::now()->subDays(3), 'monthly_service_count' => 6, 'monthly_reset_at' => Carbon::now()->subDays(6)],
                [
                    ['lifetime', 5, 2, 15, 'Lifetime reward for 5 services (+2 hour)'],
                    ['weekly', 3, 1, 3, 'Weekly reward for 3 services (+1 hour)'],
                ],
            ],
            [
                'user3@system.com',
                ['services_requested_count' => 3, 'weekly_service_count' => 3, 'weekly_reset_at' => Carbon::now()->subDays(1), 'monthly_service_count' => 3, 'monthly_reset_at' => Carbon::now()->subDays(1)],
                [
                    ['weekly', 3, 1, 1, 'Weekly reward for 3 services (+1 hour)'],
                ],
            ],
        ];

        foreach ($specs as [$email, $counters, $rewards]) {
            $user = $this->cast[$email];
            $user->update($counters);

            foreach ($rewards as [$type, $threshold, $hours, $daysAgo, $reason]) {
                RewardModel::create([
                    'user_id' => $user->id,
                    'hours_added' => $hours,
                    'type' => $type,
                    'threshold' => $threshold,
                    'reason' => $reason,
                    'created_at' => Carbon::now()->subDays($daysAgo),
                ]);
            }
        }
    }

    private function seedSearchHistory(): void
    {
        $specs = [
            ['user@system.com', 'سباكة', 2],
            ['user@system.com', 'سخانات', 5],
            ['user@system.com', 'تمديدات', 9],
            ['user2@system.com', 'دروس انجليزي', 1],
            ['user2@system.com', 'translation', 4],
            ['user3@system.com', 'كهرباء منزلية', 3],
            ['user4@system.com', 'هوية بصرية', 3],
            ['user5@system.com', 'web development', 6],
            ['user5@system.com', 'تصميم', 8],
            ['user6@system.com', 'nutrition', 2],
            ['user6@system.com', 'تغذية', 7],
            ['user7@system.com', 'توصيل؟', 1],
            ['user8@system.com', 'محاسبة', 2],
            ['user9@system.com', 'صيانة تكييف', 4],
            ['user10@system.com', 'تصوير', 5],
            ['user10@system.com', 'تكييف', 10],
        ];

        foreach ($specs as [$email, $query, $daysAgo]) {
            $history = UserSearchHistory::create([
                'user_id' => $this->cast[$email]->id,
                'query' => $query,
                'searched_at' => Carbon::now()->subDays($daysAgo),
            ]);

            $this->manifest['search_history'][] = $history->id;
        }
    }

    private function seedWorkGallery(): void
    {
        $specs = [
            ['user@system.com', 'تركيب كامل لشبكة مياه', 'شبكة مياه وصرف صحي كاملة لشقة سكنية.', 30, 2],
            ['user@system.com', 'إصلاح تسريب سخان', 'إصلاح تسريب في سخان غاز واستبدال القطع التالفة.', 12, 1],
            ['user@system.com', 'تمديدات حمام', null, 8, 0],
            ['user2@system.com', 'ملف أعمال ترجمة', 'نماذج من أعمال الترجمة العربية والإنجليزية.', 20, 1],
            ['user2@system.com', 'دروس جماعية مجانية', 'دورة مجانية لتعليم اللغة الإنجليزية للمبتدئين.', 10, 0],
            ['user9@system.com', 'صيانة مكيفات مركز تجاري', 'صيانة شاملة لثمانية مكيفات في مركز تجاري.', 25, 3],
            ['user10@system.com', 'تصوير حفل زفاف', 'تغطية مصورة كاملة لحفل زفاف.', 15, 4],
            ['user10@system.com', 'بورتريه احترافي', 'جلسة بورتريه داخل الاستوديو.', 5, 0],
        ];

        foreach ($specs as [$email, $title, $description, $daysAgo, $fileCount]) {
            $item = WorkGalleryItem::create([
                'user_id' => $this->cast[$email]->id,
                'title' => $title,
                'description' => $description,
                'date_of_achievement' => Carbon::now()->subDays($daysAgo)->toDateString(),
                'created_at' => Carbon::now()->subDays($daysAgo),
            ]);

            $this->manifest['gallery_items'][] = $item->id;

            for ($i = 1; $i <= $fileCount; $i++) {
                $extension = $i === 1 && $title === 'ملف أعمال ترجمة' ? 'pdf' : 'png';
                $filename = Str::slug($title).'-'.$i.'.'.$extension;
                $path = 'work_gallery/demo/'.$filename;

                if ($extension === 'png') {
                    $this->makePlaceholderPng($path);
                } else {
                    Storage::disk('public')->put($path, 'PDF placeholder for demo data.');
                }

                $file = WorkGalleryItemFile::create([
                    'work_gallery_item_id' => $item->id,
                    'file_url' => '/storage/'.$path,
                    'file_type' => $extension === 'png' ? 'image/png' : 'application/pdf',
                ]);

                $this->manifest['gallery_files'][] = $file->id;
            }
        }
    }

    private function seedChats(): void
    {
        $personalSpecs = [
            [
                ['user@system.com', 'user7@system.com'],
                'user@system.com',
                10,
                [
                    ['user@system.com', 'مرحباً عمر، جاهز لتركيب الشبكة السبت؟'],
                    ['user7@system.com', 'أهلاً أستاذ أحمد، متفرغ السبت بعد الظهر.'],
                    ['user@system.com', 'ممتاز، سأرسل لك عنوان الشقة.'],
                    ['user7@system.com', 'تمام، أحضر معي الأدوات اللازمة.'],
                    ['user@system.com', 'كم تستغرق المدة تقريباً؟'],
                    ['user7@system.com', 'حوالي 4 ساعات إذا كانت التمديدات جاهزة.'],
                    ['user@system.com', 'ممتاز، نلتقي السبت الساعة 2.'],
                    ['user7@system.com', 'تم، سأكون في الموعد.'],
                    ['user@system.com', 'شكراً لك، العمل كان ممتازاً.'],
                    ['user7@system.com', 'العفو، سعيد بسماع ذلك.'],
                ],
                2,
            ],
            [
                ['user2@system.com', 'user10@system.com'],
                'user2@system.com',
                5,
                [
                    ['user2@system.com', 'أهلاً مريم، عندي استفسار عن جلسة التصوير.'],
                    ['user10@system.com', 'أهلاً سارة، تفضلي.'],
                    ['user2@system.com', 'بكم جلسة بورتريه بسيطة؟'],
                    ['user10@system.com', 'الجلسة البسيطة 80 دولار وتشمل 10 صور معدلة.'],
                    ['user2@system.com', 'ممتاز، أريد حجز يوم الخميس القادم.'],
                    ['user10@system.com', 'الخميس متاح، سأرسل لك التفاصيل.'],
                    ['user2@system.com', 'شكراً جزيلاً.'],
                    ['user10@system.com', 'على الرحب والسعة.'],
                ],
                1,
            ],
        ];

        foreach ($personalSpecs as [$members, $creatorEmail, $daysAgo, $messages, $lastMessageDaysAgo]) {
            $this->createChat('personal', null, $creatorEmail, $members, $messages, $daysAgo, $lastMessageDaysAgo);
        }

        $groupMembers = ['user@system.com', 'user3@system.com', 'user9@system.com', 'user7@system.com'];
        $groupMessages = [
            ['user@system.com', 'صباح الخير شباب، عندنا مشروع كبير قريباً.'],
            ['user3@system.com', 'مبارك! شو نوع العمل؟'],
            ['user@system.com', 'سباكة وكهرباء لبيت جديد في دمشق.'],
            ['user9@system.com', 'بحاجة مساعدة بالتكييف؟'],
            ['user@system.com', 'بالتأكيد، رح نحتاجك للدور الأخير.'],
            ['user7@system.com', 'أنا جاهز للتنقل والتوصيل متى بدينا.'],
            ['user3@system.com', 'ممكن نشوف المخططات قبل ما نبدأ؟'],
            ['user@system.com', 'بعتها على الخاص اليوم.'],
            ['user9@system.com', 'وصلت، يبدو واضحاً.'],
            ['user3@system.com', 'من جهتي جاهز للمرحلة الأولى.'],
            ['user7@system.com', 'بنسق المواصلات الأسبوع الجاي.'],
            ['user@system.com', 'اجتمعت مع صاحب البيت، الموافقة جاهزة.'],
            ['user9@system.com', 'ممتاز، نحضر العدة.'],
            ['user3@system.com', 'تم، كل شيء جاهز.'],
            ['user7@system.com', 'تمام، نتحدث لاحقاً.'],
        ];

        $this->createChat('group', 'فريق الصيانة', 'user@system.com', $groupMembers, $groupMessages, 8, 1);
    }

    private function createChat(string $type, ?string $name, string $creatorEmail, array $members, array $messages, int $daysAgo, int $lastMessageDaysAgo): void
    {
        $creator = $this->cast[$creatorEmail];
        $chat = Chat::create([
            'type' => $type,
            'name' => $name,
            'created_by' => $creator->id,
            'last_message_at' => Carbon::now()->subDays($lastMessageDaysAgo),
            'created_at' => Carbon::now()->subDays($daysAgo),
        ]);

        $chat->users()->attach(array_map(fn (string $email) => $this->cast[$email]->id, $members), [
            'joined_at' => Carbon::now()->subDays($daysAgo),
        ]);

        $this->manifest['chats'][] = $chat->id;

        $memberIds = array_map(fn (string $email) => $this->cast[$email]->id, $members);
        $messageCount = count($messages);

        foreach ($messages as $i => [$senderEmail, $content]) {
            $sentAt = Carbon::now()->subDays($lastMessageDaysAgo)->addMinutes($i * 30);
            $message = Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $this->cast[$senderEmail]->id,
                'content' => $content,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);

            $this->manifest['messages'][] = $message->id;

            foreach ($memberIds as $memberId) {
                if ($memberId === $message->sender_id) {
                    continue;
                }

                $isLast = $i >= $messageCount - 2;
                $recipient = MessageRecipient::create([
                    'message_id' => $message->id,
                    'user_id' => $memberId,
                    'received_at' => $sentAt,
                    'read_at' => $isLast ? null : $sentAt->addMinutes(5),
                ]);

                $this->manifest['message_recipients'][] = $recipient->id;
            }
        }
    }

    private function seedIdentityVerifications(): void
    {
        $specs = [
            ['user2@system.com', 'approved', 'demo-session-approved', 'demo-vendor-approved', null, ['status' => 'Approved'], Carbon::now()->subDays(20), true],
            ['user3@system.com', 'declined', 'demo-session-declined', 'demo-vendor-declined', null, ['status' => 'Declined'], null, false],
            ['user6@system.com', 'pending', 'demo-session-pending', 'demo-vendor-pending', 'https://verify.didit.me/demo-session-pending', ['status' => 'Pending'], null, false],
        ];

        foreach ($specs as [$email, $status, $sessionId, $vendorToken, $url, $data, $verifiedAt, $isVerified]) {
            $user = $this->cast[$email];

            $verification = IdentityVerificationModel::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'vendor_token' => $vendorToken,
                'verification_url' => $url,
                'status' => $status,
                'verification_data' => $data,
                'verified_at' => $verifiedAt,
                'created_at' => Carbon::now()->subDays(rand(10, 30)),
            ]);

            if ($isVerified) {
                $user->update([
                    'is_identity_verified' => true,
                    'identity_verified_at' => $verifiedAt,
                ]);
            }

            $this->manifest['identity_verifications'][] = $verification->id;
        }
    }

    private function seedTopPerformers(): void
    {
        $paidTypeId = $this->servingTypes['paid']->id;
        $voluntaryTypeId = $this->servingTypes['voluntary']->id;
        $lastMonthDate = Carbon::now()->subMonthNoOverflow()->endOfMonth()->setTime(23, 0, 0);

        $lastMonthRanks = [
            [$paidTypeId, ['user9@system.com', 'user@system.com', 'user3@system.com']],
            [$voluntaryTypeId, ['user2@system.com', 'user7@system.com']],
        ];

        foreach ($lastMonthRanks as [$typeId, $emails]) {
            foreach ($emails as $rank => $email) {
                $performer = TopPerformer::create([
                    'user_id' => $this->cast[$email]->id,
                    'serving_type_id' => $typeId,
                    'rank' => $rank + 1,
                    'date' => $lastMonthDate,
                    'created_at' => $lastMonthDate,
                    'updated_at' => $lastMonthDate,
                ]);

                $this->manifest['top_performers'][] = $performer->id;
            }
        }

        $before = TopPerformer::pluck('id')->all();
        app(TopPerformerServiceInterface::class)->calculateAndStore(Carbon::now());
        $after = TopPerformer::pluck('id')->all();

        foreach (array_diff($after, $before) as $id) {
            $this->manifest['top_performers'][] = $id;
        }
    }

    private function makePlaceholderPng(string $path): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            Storage::disk('public')->put($path, 'PNG placeholder for demo data.');

            return;
        }

        $width = 400;
        $height = 300;
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, random_int(80, 200), random_int(80, 200), random_int(80, 200));
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        $accent = imagecolorallocate($image, 255, 255, 255);
        imagerectangle($image, 20, 20, $width - 20, $height - 20, $accent);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $contents);
    }
}
