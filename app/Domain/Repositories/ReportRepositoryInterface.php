<?php

namespace App\Domain\Repositories;

use Carbon\CarbonInterface;

interface ReportRepositoryInterface
{
    // ===================== Users =====================
    public function countAllUsers(): int;

    public function countActiveUsers(): int;

    public function countBlockedUsers(): int;

    // ===================== Servings =====================
    public function countAllServings(): int;

    public function countVoluntaryServings(): int;

    public function countPaidServings(): int;

    public function countExchangeServings(): int;

    // ===================== Complaints =====================
    public function countAllComplaints(): int;

    public function countComplaintsByStatus(string $status): int;

    public function getComplaintsByDateRange(CarbonInterface $startDate, CarbonInterface $endDate): array;
}
