<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\RewardModel;
use Illuminate\Pagination\LengthAwarePaginator;

interface RewardRepositoryInterface
{
    public function create(array $data): RewardModel;
    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator;
    public function findExistingReward(int $userId, string $type, int $threshold): ?RewardModel;
}