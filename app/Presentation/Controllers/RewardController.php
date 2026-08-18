<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\RewardServiceInterface;

class RewardController
{
    public function __construct(
        private RewardServiceInterface $rewardService
    ) {}

    public function myRewards()
    {
        $result = $this->rewardService->getUserRewards(auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
