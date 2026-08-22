<?php

namespace App\Application\Services;

use App\Domain\Services\RewardServiceInterface;
use App\Domain\Services\NotificationServiceInterface;
use App\Infrastructure\Models\RewardModel;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Domain\Enums\NotificationType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RewardService implements RewardServiceInterface
{
    public function __construct(
        private NotificationServiceInterface $notificationService  // 🔥 أضيفي
    ) {}

    private function getLifetimeLevels(): array
    {
        return [
            5 => 2,
            10 => 3,
            25 => 5,
            50 => 10,
        ];
    }

    private function getWeeklyReward(): array
    {
        return ['threshold' => 3, 'hours' => 1];
    }

    private function getMonthlyReward(): array
    {
        return ['threshold' => 10, 'hours' => 3];
    }

    public function incrementServiceCount(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $user = User::whereKey($userId)->lockForUpdate()->first();
            if (! $user) {
                return;
            }

            $user->increment('services_requested_count');

            if (! $user->weekly_reset_at || $user->weekly_reset_at->startOfWeek()->ne(Carbon::now()->startOfWeek())) {
                $user->weekly_service_count = 1;
                $user->weekly_reset_at = Carbon::now();
            } else {
                $user->increment('weekly_service_count');
            }

            if (! $user->monthly_reset_at || $user->monthly_reset_at->format('Y-m') != Carbon::now()->format('Y-m')) {
                $user->monthly_service_count = 1;
                $user->monthly_reset_at = Carbon::now();
            } else {
                $user->increment('monthly_service_count');
            }

            $user->save();
        });
    }

    public function checkAndApplyRewards(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            $user = User::whereKey($userId)->lockForUpdate()->first();
            if (! $user) {
                return ['success' => false, 'message' => 'User not found'];
            }

            $applied = [];

            $lifetimeRewards = $this->applyLifetimeRewards($user);
            if ($lifetimeRewards) {
                $applied = array_merge($applied, $lifetimeRewards);
            }

            $weekly = $this->applyWeeklyReward($user);
            if ($weekly) {
                $applied[] = $weekly;
            }

            $monthly = $this->applyMonthlyReward($user);
            if ($monthly) {
                $applied[] = $monthly;
            }

            return [
                'success' => true,
                'message' => count($applied) > 0
                    ? 'Reward has been added '.implode(', ', $applied)
                    : 'There is no new reward',
                'data' => $applied,
            ];
        });
    }

    private function applyLifetimeRewards(User $user): array
    {
        $applied = [];
        $count = $user->services_requested_count;

        foreach ($this->getLifetimeLevels() as $threshold => $hours) {
            if ($count >= $threshold) {
                $existing = RewardModel::where('user_id', $user->id)
                    ->where('type', 'lifetime')
                    ->where('threshold', $threshold)
                    ->first();

                if (! $existing) {
                    $this->addReward($user->id, $hours, 'lifetime', $threshold);
                    $applied[] = "{$threshold} service (+{$hours} hour)";
                }
            }
        }

        return $applied;
    }

    private function applyWeeklyReward(User $user): ?string
    {
        $reward = $this->getWeeklyReward();

        if ($user->weekly_service_count >= $reward['threshold']) {
            $existing = RewardModel::where('user_id', $user->id)
                ->where('type', 'weekly')
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->first();

            if (! $existing) {
                $this->addReward($user->id, $reward['hours'], 'weekly', $reward['threshold']);

                return "weekly (+{$reward['hours']} hour)";
            }
        }

        return null;
    }

    private function applyMonthlyReward(User $user): ?string
    {
        $reward = $this->getMonthlyReward();

        if ($user->monthly_service_count >= $reward['threshold']) {
            $existing = RewardModel::where('user_id', $user->id)
                ->where('type', 'monthly')
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->first();

            if (! $existing) {
                $this->addReward($user->id, $reward['hours'], 'monthly', $reward['threshold']);

                return "monthly (+{$reward['hours']} hour)";
            }
        }

        return null;
    }

    private function addReward(int $userId, int $hours, string $type, int $threshold): void
    {
        $wallet = WalletModel::where('user_id', $userId)->first();
        if (! $wallet) {
            return;
        }

        $wallet->balance += $hours;
        $wallet->save();

        $reward = RewardModel::create([
            'user_id' => $userId,
            'hours_added' => $hours,
            'type' => $type,
            'threshold' => $threshold,
            'reason' => $this->getReason($type, $threshold, $hours),
        ]);

        // 🔥 إشعار المكافأة
        $this->notificationService->send(
            $userId,
            NotificationType::REWARD_EARNED,
            '🎁 مكافأة جديدة!',
            "حصلت على {$hours} ساعة إضافية" . ($type !== 'lifetime' ? " ({$type})" : ""),
            [
                'reward_id' => $reward->id,
                'hours_added' => $hours,
                'type' => $type,
                'threshold' => $threshold,
            ]
        );
    }

    private function getReason(string $type, int $threshold, int $hours): string
    {
        return match ($type) {
            'lifetime' => "Lifetime reward for {$threshold} services (+{$hours} hour)",
            'weekly' => "Weekly reward for {$threshold} services (+{$hours} hour)",
            'monthly' => "Monthly reward for {$threshold} services this month (+{$hours} hour)",
            default => "reward (+{$hours} hour)",
        };
    }

    public function getUserRewards(int $userId): array
    {
        $rewards = RewardModel::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalHours = $rewards->sum('hours_added');

        return [
            'success' => true,
            'data' => $rewards,
            'total_hours_added' => $totalHours,
        ];
    }
}