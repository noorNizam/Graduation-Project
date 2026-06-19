<?php

namespace App\Application\Services;

use App\Domain\Services\PenaltyServiceInterface;
use App\Domain\Repositories\PenaltyRepositoryInterface;
use App\Infrastructure\Repositories\UserRepository;
use App\Infrastructure\Models\WalletModel;
use App\Traits\HandlesDatabaseTransactions;
use App\Domain\Repositories\UserRepositoryInterface;

class PenaltyService implements PenaltyServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private PenaltyRepositoryInterface $penaltyRepository,
        private UserRepositoryInterface $userRepository  
    ) {}

    /**
     * تطبيق عقوبة على مستخدم
     */
    public function applyPenalty(int $userId, string $type, int $complaintId = null, string $reason = null): array
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }

        $result = $this->executeWithTransaction(function () use ($userId, $type, $complaintId, $reason, $user) {
            $penaltyData = [
                'user_id' => $userId,
                'complaint_id' => $complaintId,
                'type' => $type,
                'reason' => $reason,
                'is_active' => true,
            ];

            switch ($type) {
                case 'warning':
                    break;

                case 'deduct_hours':
                    $hours = 1;
                    $penaltyData['hours_deducted'] = $hours;
                    break;

                case 'suspend':
                    $days = 7;
                    $penaltyData['suspended_days'] = $days;
                    $penaltyData['expires_at'] = now()->addDays($days);
                    $user->is_active = false;
                    $user->banned_until = now()->addDays($days);
                    $user->save();
                    break;

                case 'ban':
                    $penaltyData['expires_at'] = null;
                    $user->is_active = false;
                    $user->banned_until = null;
                    $user->save();
                    break;

                default:
                    return ['success' => false, 'message' => 'نوع عقوبة غير معروف'];
            }

            return $this->penaltyRepository->create($penaltyData);
        });

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'تم تطبيق العقوبة بنجاح'
        ];
    }

    /**
     * خصم ساعات من محفظة المستخدم
     */
    public function deductHours(int $userId, int $hours, int $complaintId = null, string $reason = null): array
    {
        $wallet = WalletModel::where('user_id', $userId)->first();
        if (!$wallet) {
            return ['success' => false, 'message' => 'محفظة المستخدم غير موجودة'];
        }

        if ($wallet->balance < $hours) {
            return [
                'success' => false,
                'message' => "رصيد الساعات غير كافٍ. الرصيد الحالي: {$wallet->balance} ساعة"
            ];
        }

        $result = $this->executeWithTransaction(function () use ($userId, $hours, $complaintId, $reason, $wallet) {
            $wallet->balance -= $hours;
            $wallet->save();

            return $this->penaltyRepository->create([
                'user_id' => $userId,
                'complaint_id' => $complaintId,
                'type' => 'deduct_hours',
                'hours_deducted' => $hours,
                'reason' => $reason ?? "خصم {$hours} ساعة بسبب شكوى منحلة",
                'is_active' => true,
            ]);
        });

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => [
                'penalty' => $result['data'],
                'new_balance' => $wallet->balance,
            ],
            'message' => "تم خصم {$hours} ساعة من محفظة المستخدم. الرصيد المتبقي: {$wallet->balance} ساعة"
        ];
    }

    /**
     * إضافة ساعات إلى محفظة المستخدم
     */
    public function addHours(int $userId, int $hours, string $reason = null): array
    {
        $wallet = WalletModel::where('user_id', $userId)->first();
        if (!$wallet) {
            return ['success' => false, 'message' => 'محفظة المستخدم غير موجودة'];
        }

        $result = $this->executeWithTransaction(function () use ($userId, $hours, $reason, $wallet) {
            $wallet->balance += $hours;
            $wallet->save();

            return [
                'success' => true,
                'new_balance' => $wallet->balance,
                'message' => "تم إضافة {$hours} ساعة إلى محفظة المستخدم. الرصيد الجديد: {$wallet->balance} ساعة"
            ];
        });

        return $result;
    }

    /**
     * عرض رصيد محفظة المستخدم
     */
    public function getWalletBalance(int $userId): array
    {
        $wallet = WalletModel::with(['user', 'unit'])
            ->where('user_id', $userId)
            ->first();

        if (!$wallet) {
            return ['success' => false, 'message' => 'محفظة المستخدم غير موجودة'];
        }

        return [
            'success' => true,
            'data' => [
                'user_id' => $wallet->user_id,
                'user_name' => $wallet->user->full_name ?? null,
                'title' => $wallet->title,
                'balance' => $wallet->balance,
                'unit' => $wallet->unit->name ?? 'ساعة',
                'unit_id' => $wallet->unit_id,
            ]
        ];
    }

    /**
     * جلب عقوبات المستخدم
     */
    public function getUserPenalties(int $userId, int $perPage = 15): array
    {
        $penalties = $this->penaltyRepository->findByUser($userId, $perPage);

        return [
            'success' => true,
            'data' => $penalties->items(),
            'meta' => [
                'current_page' => $penalties->currentPage(),
                'per_page' => $penalties->perPage(),
                'total' => $penalties->total(),
                'last_page' => $penalties->lastPage(),
            ]
        ];
    }

    /**
     * جلب كل العقوبات (للمدير)
     */
    public function getAllPenalties(array $filters = [], int $perPage = 15): array
    {
        $penalties = $this->penaltyRepository->findAll($filters, $perPage);

        return [
            'success' => true,
            'data' => $penalties->items(),
            'meta' => [
                'current_page' => $penalties->currentPage(),
                'per_page' => $penalties->perPage(),
                'total' => $penalties->total(),
                'last_page' => $penalties->lastPage(),
            ]
        ];
    }

    /**
     * جلب عقوبة معينة
     */
    public function getPenaltyById(int $id): array
    {
        $penalty = $this->penaltyRepository->findById($id);

        if (!$penalty) {
            return ['success' => false, 'message' => 'العقوبة غير موجودة'];
        }

        return [
            'success' => true,
            'data' => $penalty
        ];
    }

    /**
     * إلغاء عقوبة
     */
    public function deactivatePenalty(int $penaltyId): array
    {
        $penalty = $this->penaltyRepository->findById($penaltyId);
        if (!$penalty) {
            return ['success' => false, 'message' => 'العقوبة غير موجودة'];
        }

        $penalty = $this->penaltyRepository->update($penaltyId, ['is_active' => false]);

        if ($penalty->type === 'suspend' || $penalty->type === 'ban') {
            $user = $this->userRepository->findById($penalty->user_id);
            if ($user) {
                $user->is_active = true;
                $user->banned_until = null;
                $user->save();
            }
        }

        return [
            'success' => true,
            'data' => $penalty,
            'message' => 'تم إلغاء العقوبة بنجاح'
        ];
    }

    /**
     * إنشاء محفظة لمستخدم
     */
    public function createWalletForUser(int $userId, string $title = 'المحفظة الرئيسية', int $unitId = 1): array
    {
        $existingWallet = WalletModel::where('user_id', $userId)->first();
        if ($existingWallet) {
            return ['success' => false, 'message' => 'المستخدم لديه محفظة بالفعل'];
        }

        $wallet = WalletModel::create([
            'user_id' => $userId,
            'title' => $title,
            'balance' => 0,
            'unit_id' => $unitId,
        ]);

        return [
            'success' => true,
            'data' => $wallet,
            'message' => 'تم إنشاء المحفظة بنجاح'
        ];
    }
}