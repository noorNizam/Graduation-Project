<?php

namespace Tests\Feature;

use App\Domain\Services\SchedulerLogServiceInterface;
use App\Infrastructure\Models\SchedulerLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerLogTest extends TestCase
{
    use RefreshDatabase;

    private function logRow(array $overrides = []): SchedulerLog
    {
        return SchedulerLog::create(array_merge([
            'job_name' => 'top-performers:calculate',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'errors' => [],
        ], $overrides));
    }

    public function test_run_logs_a_row_with_timing_and_no_errors(): void
    {
        $exitCode = app(SchedulerLogServiceInterface::class)->run('test-job', function () {
            return 0;
        });

        $this->assertSame(0, $exitCode);

        $this->assertDatabaseHas('scheduler_logs', [
            'job_name' => 'test-job',
        ]);

        $log = SchedulerLog::where('job_name', 'test-job')->first();

        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->finished_at);
        $this->assertTrue($log->finished_at->gte($log->started_at));
        $this->assertSame([], $log->errors);
    }

    public function test_run_logs_error_message_when_job_throws(): void
    {
        $exitCode = app(SchedulerLogServiceInterface::class)->run('failing-job', function () {
            throw new \RuntimeException('boom');
        });

        $this->assertSame(1, $exitCode);

        $log = SchedulerLog::where('job_name', 'failing-job')->first();

        $this->assertNotNull($log);
        $this->assertSame(['boom'], $log->errors);
        $this->assertNotNull($log->finished_at);
    }

    public function test_run_logs_non_zero_exit_code_as_error(): void
    {
        $exitCode = app(SchedulerLogServiceInterface::class)->run('failing-job', function () {
            return 1;
        });

        $this->assertSame(1, $exitCode);

        $log = SchedulerLog::where('job_name', 'failing-job')->first();

        $this->assertSame(['Job returned non-zero exit code 1'], $log->errors);
    }

    public function test_run_command_creates_log_for_successful_command(): void
    {
        $exitCode = app(SchedulerLogServiceInterface::class)->runCommand(
            'serving-requests:auto-complete',
            'serving-requests:auto-complete'
        );

        $this->assertSame(0, $exitCode);

        $log = SchedulerLog::where('job_name', 'serving-requests:auto-complete')->first();

        $this->assertNotNull($log);
        $this->assertSame([], $log->errors);
    }

    public function test_run_command_logs_error_when_command_is_missing(): void
    {
        $exitCode = app(SchedulerLogServiceInterface::class)->runCommand(
            'missing-job',
            'non-existent:command'
        );

        $this->assertSame(1, $exitCode);

        $log = SchedulerLog::where('job_name', 'missing-job')->first();

        $this->assertNotNull($log);
        $this->assertNotEmpty($log->errors);
        $this->assertStringContainsString('non-existent:command', $log->errors[0]);
    }

    public function test_run_command_logs_command_output_when_command_fails(): void
    {
        $exitCode = app(SchedulerLogServiceInterface::class)->runCommand(
            'top-performers:calculate',
            'top-performers:calculate'
        );

        $this->assertSame(1, $exitCode);

        $log = SchedulerLog::where('job_name', 'top-performers:calculate')->first();

        $this->assertNotNull($log);
        $this->assertNotEmpty($log->errors);
        $this->assertStringContainsString('Paid serving type or Hour payment unit not found', $log->errors[0]);
    }

    public function test_scheduler_logs_api_returns_logs(): void
    {
        $this->logRow();

        $response = $this->getJson('/api/scheduler-logs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $response->assertJsonStructure([
            'success',
            'data' => [
                [
                    'id',
                    'job_name',
                    'started_at',
                    'finished_at',
                    'errors',
                    'created_at',
                ],
            ],
        ]);
    }

    public function test_scheduler_logs_api_filters_by_job_name(): void
    {
        $this->logRow(['job_name' => 'top-performers:calculate']);
        $this->logRow(['job_name' => 'serving-requests:auto-complete']);

        $response = $this->getJson('/api/scheduler-logs?job_name=top-performers:calculate');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job_name', 'top-performers:calculate');
    }

    public function test_scheduler_logs_api_respects_skip_and_take(): void
    {
        $this->logRow(['job_name' => 'job-1', 'started_at' => now()->subMinutes(3)]);
        $this->logRow(['job_name' => 'job-2', 'started_at' => now()->subMinutes(2)]);
        $this->logRow(['job_name' => 'job-3', 'started_at' => now()->subMinutes(1)]);

        $response = $this->getJson('/api/scheduler-logs?skip=1&take=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job_name', 'job-2');
    }

    public function test_scheduler_logs_api_validates_take_minimum(): void
    {
        $response = $this->getJson('/api/scheduler-logs?take=0');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_scheduler_logs_api_validates_skip_minimum(): void
    {
        $response = $this->getJson('/api/scheduler-logs?skip=-1');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
