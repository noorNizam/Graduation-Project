<?php

namespace App\Presentation\Controllers\Admin;

use App\Domain\Services\ReportServiceInterface;

class ReportController
{
    public function __construct(
        private ReportServiceInterface $reportService
    ) {}

    public function userStatistics()
    {
        $result = $this->reportService->getUserStatistics();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function servingStatistics()
    {
        $result = $this->reportService->getServingStatistics();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function complaintStatistics()
    {
        $result = $this->reportService->getComplaintStatistics();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function weeklyComplaints()
    {
        $result = $this->reportService->getWeeklyComplaints();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function monthlyComplaints()
    {
        $result = $this->reportService->getMonthlyComplaints();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function dashboard()
    {
        $result = $this->reportService->getDashboardStatistics();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function exportExcel()
    {
        $fileName = $this->reportService->exportExcelReport();

        return response()->download(
            storage_path('app/public/'.$fileName)
        )->deleteFileAfterSend(true);
    }
}
