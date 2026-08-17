<?php

namespace App\Domain\Services;

interface RewardServiceInterface
{
    public function incrementServiceCount(int $userId): void;
    public function checkAndApplyRewards(int $userId): array;
    public function getUserRewards(int $userId): array;
}