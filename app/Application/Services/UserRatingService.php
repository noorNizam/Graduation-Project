<?php

namespace App\Application\Services;

use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\UserRatingRepositoryInterface;
use App\Domain\Services\UserRatingServiceInterface;
use App\Infrastructure\Models\ServingRequest;
use App\Traits\HandlesDatabaseTransactions;

class UserRatingService implements UserRatingServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private UserRatingRepositoryInterface $ratingRepository,
        private ServingRepositoryInterface $servingRepository
    ) {}

    public function rateServing(int $userId, int $servingId, float $rating): array
    {
        $serving = $this->servingRepository->findById($servingId);
        if (! $serving) {
            return ['success' => false, 'message' => 'Serving not found'];
        }

        $hasCompleted = ServingRequest::where('serving_id', $servingId)
            ->where('requester_id', $userId)
            ->where('status', ServingRequest::STATUS_COMPLETED)
            ->exists();

        if (! $hasCompleted) {
            return [
                'success' => false,
                'message' => 'You must have a completed request for this serving to rate it',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($userId, $servingId, $rating) {
            $this->ratingRepository->upsert($userId, $servingId, $rating);

            $average = $this->ratingRepository->averageRatingForServing($servingId);

            $this->servingRepository->update($servingId, ['rate' => $average]);

            return ['rating' => $rating, 'average' => $average];
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Rating submitted successfully',
        ];
    }
}
