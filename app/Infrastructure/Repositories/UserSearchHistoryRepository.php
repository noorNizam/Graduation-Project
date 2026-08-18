<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\UserSearchHistoryRepositoryInterface;
use App\Infrastructure\Models\UserSearchHistory;
use Illuminate\Support\Collection;

class UserSearchHistoryRepository implements UserSearchHistoryRepositoryInterface
{
    public function create(array $data): UserSearchHistory
    {
        return UserSearchHistory::create($data);
    }

    public function findRecentByUserId(int $userId, int $days = 30, ?int $limit = null): Collection
    {
        $query = UserSearchHistory::where('user_id', $userId)
            ->where('searched_at', '>=', now()->subDays($days))
            ->orderByDesc('searched_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
