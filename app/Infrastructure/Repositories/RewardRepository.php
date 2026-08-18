<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\RewardRepositoryInterface;
use App\Infrastructure\Models\RewardModel;
use Illuminate\Pagination\LengthAwarePaginator;

class RewardRepository implements RewardRepositoryInterface
{
    public function create(array $data): RewardModel
    {
        return RewardModel::create($data);
    }

    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return RewardModel::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findExistingReward(int $userId, string $type, int $threshold): ?RewardModel
    {
        return RewardModel::where('user_id', $userId)
            ->where('type', $type)
            ->where('threshold', $threshold)
            ->first();
    }
}
