<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\UserRating;

interface UserRatingRepositoryInterface
{
    public function findByUserAndServing(int $userId, int $servingId): ?UserRating;

    public function upsert(int $userId, int $servingId, float $rating): UserRating;

    public function averageRatingForServing(int $servingId): float;
}
