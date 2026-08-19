<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ReportRepositoryInterface;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ComplaintModel;
use Carbon\Carbon;

class ReportRepository implements ReportRepositoryInterface
{
    // ===================== المستخدمين =====================
    public function countAllUsers(): int
    {
        return User::count();
    }

    public function countActiveUsers(): int
    {
        return User::where('is_active', true)->count();
    }

    public function countBlockedUsers(): int
    {
        return User::where('is_active', false)->count();
    }

    // ===================== الخدمات =====================
    public function countAllServings(): int
    {
        return Serving::count();
    }

    public function countVoluntaryServings(): int
    {
        return Serving::where('type', 'voluntary')->count();
    }

    public function countPaidServings(): int
    {
        return Serving::where('type', 'paid')->count();
    }

    public function countExchangeServings(): int
    {
        return Serving::whereHas('servingType', function ($q) {
            $q->where('name', 'paid');
        })->whereHas('unit', function ($q) {
            $q->where('name', 'Hour');
        })->count();
    }

    // ===================== الشكاوي =====================
    public function countAllComplaints(): int
    {
        return ComplaintModel::count();
    }

    public function countComplaintsByStatus(string $status): int
    {
        return ComplaintModel::where('status', $status)->count();
    }

    public function getComplaintsByDateRange(string $startDate, string $endDate): array
    {
        return ComplaintModel::whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->toArray();
    }
}