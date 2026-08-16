<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\UserSearchHistory;
use Illuminate\Support\Collection;

interface UserSearchHistoryRepositoryInterface
{
    public function create(array $data): UserSearchHistory;

    public function findRecentByUserId(int $userId, int $days = 30, ?int $limit = null): Collection;
}
