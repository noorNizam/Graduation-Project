<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\SchedulerLogServiceInterface;
use App\Presentation\Requests\GetSchedulerLogsRequest;

class SchedulerLogController
{
    public function __construct(
        private SchedulerLogServiceInterface $schedulerLogService
    ) {}

    public function index(GetSchedulerLogsRequest $request)
    {
        $validated = $request->validated();

        $result = $this->schedulerLogService->getLogs(
            $validated['job_name'] ?? null,
            $validated['skip'] ?? null,
            $validated['take'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
