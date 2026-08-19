<?php

namespace App\Domain\Services;

interface ReportServiceInterface
{
    public function getUserStatistics(): array;

    public function getServingStatistics(): array;

    public function getComplaintStatistics(): array;

    public function getWeeklyComplaints(): array;

    public function getMonthlyComplaints(): array;

    public function getDashboardStatistics(): array;

    public function exportExcelReport(): string;
}
