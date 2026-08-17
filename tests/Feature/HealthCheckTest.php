<?php

namespace Tests\Feature;

use App\Domain\Services\ServingProposalServiceInterface;
use App\Infrastructure\Models\SchedulerLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ServingProposalServiceInterface::class, $this->fakeProposalService());
    }

    private function fakeHealthHttp(array $overrides = []): void
    {
        Http::fake(array_merge([
            config('health.nginx_url') => Http::response('ok', 200),
            'http://'.config('health.reverb_host').':'.config('health.reverb_port').'/' => Http::response('', 200),
        ], $overrides));
    }

    public function test_health_reports_all_services_and_checks(): void
    {
        $this->fakeHealthHttp();

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('services.app.status', 'ok')
            ->assertJsonPath('services.nginx.status', 'ok')
            ->assertJsonPath('services.database.status', 'ok')
            ->assertJsonPath('services.queue.status', 'ok')
            ->assertJsonPath('services.scheduler.status', 'unknown')
            ->assertJsonPath('services.reverb.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.storage.status', 'ok')
            ->assertJsonPath('checks.search_index.status', 'ok')
            ->assertJsonStructure(['timestamp']);
    }

    public function test_health_probes_nginx(): void
    {
        $this->fakeHealthHttp();

        $this->getJson('/api/health');

        Http::assertSent(fn (Request $request) => $request->url() === config('health.nginx_url'));
    }

    public function test_health_reports_nginx_down_when_unreachable(): void
    {
        $this->fakeHealthHttp([
            config('health.nginx_url') => Http::response('ok', 503),
        ]);

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('services.nginx.status', 'down');
    }

    public function test_health_reports_scheduler_degraded_when_stale(): void
    {
        $this->fakeHealthHttp();

        SchedulerLog::create([
            'job_name' => 'serving-requests:auto-complete',
            'started_at' => now()->subHours(6),
            'finished_at' => now()->subHours(6)->addMinutes(1),
            'errors' => [],
        ]);

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('services.scheduler.status', 'degraded')
            ->assertJsonPath('services.scheduler.hours_since_last_run', 6);
    }

    public function test_health_reports_scheduler_ok_when_recent(): void
    {
        $this->fakeHealthHttp();

        SchedulerLog::create([
            'job_name' => 'serving-requests:auto-complete',
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(29),
            'errors' => [],
        ]);

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('services.scheduler.status', 'ok');
    }

    public function test_health_returns_503_when_database_is_down(): void
    {
        $this->fakeHealthHttp();

        DB::shouldReceive('connection')->andThrow(new \Exception('connection refused'));

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('services.database.status', 'down');
    }

    public function test_health_reports_queue_degraded_on_stale_jobs(): void
    {
        $this->fakeHealthHttp();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 1,
            'reserved_at' => now()->timestamp - 600,
            'available_at' => now()->timestamp - 1200,
            'created_at' => now()->timestamp - 1200,
        ]);

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('services.queue.status', 'degraded')
            ->assertJsonPath('services.queue.stale_reserved_jobs', 1);
    }

    public function test_health_checks_cache_round_trip(): void
    {
        $this->fakeHealthHttp();

        Cache::shouldReceive('put')->andReturn(true);
        Cache::shouldReceive('get')->andReturn('ok');
        Cache::shouldReceive('forget')->andReturn(true);

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('checks.cache.status', 'ok');
    }

    private function fakeProposalService(): ServingProposalServiceInterface
    {
        return new class implements ServingProposalServiceInterface
        {
            public function rebuildIndex(): array
            {
                return ['success' => true];
            }

            public function getProposedServings(int $userId, int $skip, int $take): array
            {
                return ['success' => true, 'data' => []];
            }

            public function indexStatus(): array
            {
                return ['success' => true, 'data' => ['exists' => true, 'indexed_docs' => 5, 'active_servings' => 5, 'up_to_date' => true]];
            }
        };
    }
}
