<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingProposalServiceInterface;
use App\Infrastructure\Models\SchedulerLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HealthCheckController
{
    public function __construct(
        private ServingProposalServiceInterface $proposalService
    ) {}

    public function health()
    {
        $services = [
            'app' => $this->checkApp(),
            'nginx' => $this->checkNginx(),
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'scheduler' => $this->checkScheduler(),
            'reverb' => $this->checkReverb(),
        ];

        $checks = [
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'search_index' => $this->checkSearchIndex(),
        ];

        $serviceStatuses = array_map(fn (array $service) => $service['status'], $services);
        $overall = in_array('down', $serviceStatuses, true)
            ? 'down'
            : (in_array('degraded', $serviceStatuses, true) ? 'degraded' : 'ok');

        return response()->json([
            'status' => $overall,
            'services' => $services,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ], $overall === 'down' ? 503 : 200);
    }

    private function checkApp(): array
    {
        return [
            'status' => 'ok',
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
        ];
    }

    private function checkNginx(): array
    {
        try {
            $response = Http::timeout(2)->get(config('health.nginx_url'));

            if ($response->successful() && trim($response->body()) === 'ok') {
                return [
                    'status' => 'ok',
                    'probe_url' => config('health.nginx_url'),
                    'status_code' => $response->status(),
                ];
            }

            if ($response->serverError()) {
                return [
                    'status' => 'down',
                    'probe_url' => config('health.nginx_url'),
                    'status_code' => $response->status(),
                    'message' => 'Nginx returned a server error',
                ];
            }

            return [
                'status' => 'degraded',
                'probe_url' => config('health.nginx_url'),
                'status_code' => $response->status(),
                'message' => 'Probe did not return expected payload',
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: nginx probe failed', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'probe_url' => config('health.nginx_url'),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo()->query('SELECT 1');
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'status' => 'ok',
                'connection' => config('database.default'),
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: database unreachable', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'connection' => config('database.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $now = time();
            $pendingJobs = DB::table('jobs')
                ->where('available_at', '<=', $now)
                ->whereNull('reserved_at')
                ->count();

            $staleReservedJobs = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<', $now - 300)
                ->count();

            $failedJobs = DB::table('failed_jobs')->count();

            $status = $failedJobs > 0 || $staleReservedJobs > 0 ? 'degraded' : 'ok';

            return [
                'status' => $status,
                'connection' => config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'stale_reserved_jobs' => $staleReservedJobs,
                'failed_jobs' => $failedJobs,
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: queue unreachable', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'connection' => config('queue.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkScheduler(): array
    {
        try {
            $latest = SchedulerLog::query()
                ->latest('started_at')
                ->first();

            if (! $latest) {
                return [
                    'status' => 'unknown',
                    'message' => 'No scheduler runs recorded yet',
                ];
            }

            $hoursSinceLastRun = (int) round($latest->started_at->diffInHours(now()));
            $status = $hoursSinceLastRun <= 2 ? 'ok' : 'degraded';

            return [
                'status' => $status,
                'last_job' => $latest->job_name,
                'last_run_at' => $latest->started_at->toISOString(),
                'hours_since_last_run' => $hoursSinceLastRun,
                'last_run_errors' => $latest->errors,
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: scheduler log query failed', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkReverb(): array
    {
        $host = config('health.reverb_host');
        $port = config('health.reverb_port');
        $url = "http://{$host}:{$port}/";

        try {
            $response = Http::timeout(config('health.reverb_timeout'))->get($url);

            return [
                'status' => 'ok',
                'host' => $host,
                'port' => $port,
                'status_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: reverb unreachable', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $key = 'health-check:'.now()->timestamp;
        try {
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return [
                'status' => $value === 'ok' ? 'ok' : 'degraded',
                'store' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: cache unreachable', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'store' => config('cache.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk('public');
            $probe = 'health-check-'.now()->timestamp.'.tmp';
            $disk->put($probe, 'ok');
            $writable = $disk->get($probe) === 'ok';
            $disk->delete($probe);

            $path = storage_path('app/public');
            $freeBytes = disk_free_space($path);

            return [
                'status' => $writable && $freeBytes !== false ? 'ok' : 'degraded',
                'writable' => $writable,
                'free_bytes' => $freeBytes !== false ? (int) $freeBytes : null,
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: storage unreachable', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkSearchIndex(): array
    {
        try {
            $status = $this->proposalService->indexStatus();

            $data = $status['data'] ?? [];
            $indexOk = ($data['exists'] ?? false) && ($data['up_to_date'] ?? false);

            return [
                'status' => $indexOk ? 'ok' : 'degraded',
                'exists' => $data['exists'] ?? false,
                'indexed_docs' => $data['indexed_docs'] ?? null,
                'active_servings' => $data['active_servings'] ?? null,
                'up_to_date' => $data['up_to_date'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Health check: search index check failed', ['message' => $e->getMessage()]);

            return [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }
    }
}
