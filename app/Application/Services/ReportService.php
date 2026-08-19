<?php

namespace App\Application\Services;

use App\Domain\Repositories\ReportRepositoryInterface;
use App\Domain\Services\ReportServiceInterface;
use App\Exports\ReportExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ReportService implements ReportServiceInterface
{
    public function __construct(
        private ReportRepositoryInterface $reportRepository
    ) {}

    // ===================== Users =====================
    public function getUserStatistics(): array
    {
        $total = $this->reportRepository->countAllUsers();
        $active = $this->reportRepository->countActiveUsers();
        $blocked = $this->reportRepository->countBlockedUsers();

        return [
            'success' => true,
            'data' => [
                'total_users' => $total,
                'active_users' => $active,
                'blocked_users' => $blocked,
                'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
            ],
        ];
    }

    // ===================== Servings =====================
    public function getServingStatistics(): array
    {
        return [
            'success' => true,
            'data' => [
                'total_servings' => $this->reportRepository->countAllServings(),
                'voluntary_servings' => $this->reportRepository->countVoluntaryServings(),
                'paid_servings' => $this->reportRepository->countPaidServings(),
                'exchange_servings' => $this->reportRepository->countExchangeServings(),
            ],
        ];
    }

    // ===================== Complaints =====================
    public function getComplaintStatistics(): array
    {
        $statuses = ['pending', 'awaiting_documents', 'under_review', 'resolved', 'rejected'];
        $data = [];

        foreach ($statuses as $status) {
            $data[$status] = $this->reportRepository->countComplaintsByStatus($status);
        }

        return [
            'success' => true,
            'data' => [
                'total_complaints' => $this->reportRepository->countAllComplaints(),
                ...$data,
            ],
        ];
    }

    // ===================== Weekly complaints =====================
    public function getWeeklyComplaints(): array
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $complaints = $this->reportRepository->getComplaintsByDateRange($start, $end);

        return [
            'success' => true,
            'data' => [
                'period' => $start->format('Y-m-d').' to '.$end->format('Y-m-d'),
                'total' => count($complaints),
                'complaints' => $complaints,
            ],
        ];
    }

    // ===================== Monthly complaints =====================
    public function getMonthlyComplaints(): array
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $complaints = $this->reportRepository->getComplaintsByDateRange($start, $end);

        return [
            'success' => true,
            'data' => [
                'period' => $start->format('Y-m-d').' to '.$end->format('Y-m-d'),
                'total' => count($complaints),
                'complaints' => $complaints,
            ],
        ];
    }

    // ===================== Full dashboard =====================
    public function getDashboardStatistics(): array
    {
        return [
            'success' => true,
            'data' => [
                'users' => $this->getUserStatistics()['data'],
                'servings' => $this->getServingStatistics()['data'],
                'complaints' => $this->getComplaintStatistics()['data'],
                'weekly_complaints' => $this->getWeeklyComplaints()['data'],
                'monthly_complaints' => $this->getMonthlyComplaints()['data'],
                'generated_at' => Carbon::now()->toISOString(),
            ],
        ];
    }

    // ===================== Excel export =====================
    public function exportExcelReport(): string
    {
        $data = $this->getDashboardStatistics()['data'];
        $fileName = 'report_'.Carbon::now()->format('Y-m-d_H-i-s').'.xlsx';

        $stored = Excel::store(new ReportExport($data), $fileName, 'public');

        if (! $stored) {
            throw new \RuntimeException('Failed to store the report export');
        }

        return $fileName;
    }
}
