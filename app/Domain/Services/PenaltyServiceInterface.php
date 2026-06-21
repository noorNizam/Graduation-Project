<?php

namespace App\Domain\Services;

interface PenaltyServiceInterface
{
    public function applyPenalty(int $userId, string $type, ?int $complaintId = null, ?string $reason = null): array;

    public function deductHours(int $userId, int $hours, ?int $complaintId = null, ?string $reason = null): array;

    public function addHours(int $userId, int $hours, ?string $reason = null): array;

    public function getWalletBalance(int $userId): array;

    public function getUserPenalties(int $userId, int $perPage = 15): array;

    public function getAllPenalties(array $filters = [], int $perPage = 15): array;

    public function getPenaltyById(int $id): array;

    public function deactivatePenalty(int $penaltyId): array;

    public function createWalletForUser(int $userId, string $title = 'Main Wallet', int $unitId = 1): array;
}
