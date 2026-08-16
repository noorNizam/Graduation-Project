<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\SchedulerLog;
use Illuminate\Database\Eloquent\Collection;

interface SchedulerLogRepositoryInterface
{
    public function create(array $data): SchedulerLog;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Infrastructure\Models\SchedulerLog>
     */
    public function findLogs(?string $jobName = null, ?int $skip = null, ?int $take = null): Collection;
}
