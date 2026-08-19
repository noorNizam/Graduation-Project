<?php

namespace App\Domain\Repositories;

interface ReportRepositoryInterface
{
    // ===================== المستخدمين =====================
    public function countAllUsers(): int;
    public function countActiveUsers(): int;
    public function countBlockedUsers(): int;

    // ===================== الخدمات =====================
    public function countAllServings(): int;
    public function countVoluntaryServings(): int;
    public function countPaidServings(): int;
    public function countExchangeServings(): int;

    // ===================== الشكاوي =====================
    public function countAllComplaints(): int;
    public function countComplaintsByStatus(string $status): int;
    public function getComplaintsByDateRange(string $startDate, string $endDate): array;
}