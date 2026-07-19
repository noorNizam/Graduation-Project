<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\UserRatingRepositoryInterface;
use App\Infrastructure\Models\UserRating;

class UserRatingRepository implements UserRatingRepositoryInterface
{
    public function findByUserAndServing(int $userId, int $servingId): ?UserRating
    {
        return UserRating::where('user_id', $userId)
            ->where('serving_id', $servingId)
            ->first();
    }

    public function upsert(int $userId, int $servingId, float $rating): UserRating
    {
        return UserRating::updateOrCreate(
            ['user_id' => $userId, 'serving_id' => $servingId],
            ['rating' => $rating]
        );
    }

    public function averageRatingForServing(int $servingId): float
    {
        return (float) UserRating::where('serving_id', $servingId)
            ->avg('rating');
    }
}
