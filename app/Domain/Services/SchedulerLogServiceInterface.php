<?php

namespace App\Domain\Services;

interface SchedulerLogServiceInterface
{
    /**
     * Runs a job and records a scheduler log with start/end times and any errors.
     *
     * @return int exit code (0 = success, 1 = one or more errors)
     */
    public function run(string $jobName, callable $job): int;

    /**
     * Runs an Artisan command by signature and records a scheduler log.
     *
     * @return int exit code (0 = success, 1 = one or more errors)
     */
    public function runCommand(string $jobName, string $signature): int;

    /**
     * @return array ['success' => bool, 'data' => mixed, 'message' => string|null]
     */
    public function getLogs(?string $jobName = null, ?int $skip = null, ?int $take = null): array;
}
