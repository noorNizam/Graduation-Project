<?php

namespace App\Domain\Services;

interface UserRatingServiceInterface
{
    public function rateServing(int $userId, int $servingId, float $rating): array;
}
