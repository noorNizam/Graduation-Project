<?php

namespace App\Presentation\Controllers\Admin;

use App\Domain\Services\RewardServiceInterface;
use App\Infrastructure\Models\RewardModel;

class AdminRewardController
{
    public function __construct(
        private RewardServiceInterface $rewardService
    ) {}

    // عرض كل المكافآت (مع فلترة)
    public function index()
    {
        $rewards = RewardModel::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $rewards->items(),
            'meta' => [
                'current_page' => $rewards->currentPage(),
                'per_page' => $rewards->perPage(),
                'total' => $rewards->total(),
                'last_page' => $rewards->lastPage(),
            ]
        ]);
    }

    // عرض مكافآت مستخدم معين
    public function getUserRewards(int $userId)
    {
        $result = $this->rewardService->getUserRewards($userId);
        return response()->json($result, $result['success'] ? 200 : 404);
    }

    // عرض إحصائيات المكافآت
    public function statistics()
    {
        $totalRewards = RewardModel::count();
        $totalHoursAdded = RewardModel::sum('hours_added');
        $topUsers = RewardModel::select('user_id')
            ->selectRaw('SUM(hours_added) as total_hours')
            ->groupBy('user_id')
            ->with('user')
            ->orderBy('total_hours', 'desc')
            ->limit(10)
            ->get();

        $byType = RewardModel::select('type')
            ->selectRaw('COUNT(*) as count, SUM(hours_added) as total_hours')
            ->groupBy('type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_rewards' => $totalRewards,
                'total_hours_added' => $totalHoursAdded,
                'top_users' => $topUsers,
                'by_type' => $byType,
            ]
        ]);
    }

    // إلغاء مكافأة (يدوياً)
    public function destroy(int $id)
    {
        $reward = RewardModel::find($id);
        if (!$reward) {
            return response()->json(['success' => false, 'message' => 'Reward not found'], 404);
        }

        // ترجيع الساعات من المحفظة
        $wallet = \App\Infrastructure\Models\WalletModel::where('user_id', $reward->user_id)->first();
        if ($wallet && $reward->hours_added > 0) {
            $wallet->balance -= $reward->hours_added;
            $wallet->save();
        }

        $reward->delete();

        return response()->json(['success' => true, 'message' => 'Reward deleted successfully']);
    }
}