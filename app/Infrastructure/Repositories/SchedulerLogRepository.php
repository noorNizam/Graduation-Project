<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\SchedulerLogRepositoryInterface;
use App\Infrastructure\Models\SchedulerLog;
use Illuminate\Database\Eloquent\Collection;

class SchedulerLogRepository implements SchedulerLogRepositoryInterface
{
    public function create(array $data): SchedulerLog
    {
        return SchedulerLog::create($data);
    }

    public function findLogs(?string $jobName = null, ?int $skip = null, ?int $take = null): Collection
    {
        return SchedulerLog::query()
            ->when($jobName !== null, fn ($query) => $query->where('job_name', $jobName))
            ->when($skip !== null, fn ($query) => $query->skip($skip))
            ->when($take !== null, fn ($query) => $query->take($take))
            ->latest('started_at')
            ->get();
    }
}
