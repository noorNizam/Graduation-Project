<?php

namespace App\Application\Services;

use App\Domain\Repositories\SchedulerLogRepositoryInterface;
use App\Domain\Services\SchedulerLogServiceInterface;
use App\Infrastructure\Models\SchedulerLog;
use Illuminate\Support\Facades\Artisan;

class SchedulerLogService implements SchedulerLogServiceInterface
{
    public function __construct(
        private SchedulerLogRepositoryInterface $schedulerLogRepository
    ) {}

    public function run(string $jobName, callable $job): int
    {
        $startedAt = now();
        $errors = [];

        try {
            $exitCode = $job();
            if (is_int($exitCode) && $exitCode !== 0) {
                $errors[] = "Job returned non-zero exit code {$exitCode}";
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $finishedAt = now();

        $this->schedulerLogRepository->create([
            'job_name' => $jobName,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'errors' => $errors,
        ]);

        return empty($errors) ? 0 : 1;
    }

    public function runCommand(string $jobName, string $signature): int
    {
        return $this->run($jobName, function () use ($signature) {
            $exitCode = Artisan::call($signature);

            if ($exitCode !== 0) {
                $output = trim(Artisan::output());
                throw new \RuntimeException(
                    "Command '{$signature}' exited with code {$exitCode}".($output !== '' ? ": {$output}" : '')
                );
            }
        });
    }

    public function getLogs(?string $jobName = null, ?int $skip = null, ?int $take = null): array
    {
        $logs = $this->schedulerLogRepository->findLogs($jobName, $skip, $take);

        return [
            'success' => true,
            'data' => $logs->map(fn (SchedulerLog $log) => [
                'id' => $log->id,
                'job_name' => $log->job_name,
                'started_at' => $log->started_at?->toISOString(),
                'finished_at' => $log->finished_at?->toISOString(),
                'errors' => $log->errors,
                'created_at' => $log->created_at?->toISOString(),
            ])->values(),
        ];
    }
}
