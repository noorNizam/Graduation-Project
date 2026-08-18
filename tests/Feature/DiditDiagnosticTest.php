<?php

namespace Tests\Feature;

use AwaisJameel\DiditLaravelClient\Exceptions\DiditRequestException;
use AwaisJameel\DiditLaravelClient\Facades\DiditLaravelClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiditDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['didit-laravel-client.webhook_secret' => 'test-secret']);
        config(['didit-laravel-client.api_key' => 'test-api-key']);
        config(['didit-laravel-client.workflow_id' => 'workflow-123']);
    }

    public function test_diagnostic_reports_config_presence(): void
    {
        $response = $this->getJson('/api/test-didit?dry=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.config.api_key_set', true)
            ->assertJsonPath('data.config.workflow_id_set', true)
            ->assertJsonPath('data.config.webhook_secret_set', true)
            ->assertJsonPath('data.config.base_url', 'https://verification.didit.me');
    }

    public function test_diagnostic_dry_run_does_not_create_a_session(): void
    {
        DiditLaravelClient::shouldReceive('createSession')
            ->never();

        DiditLaravelClient::shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(['status' => 'Approved']);

        $response = $this->getJson('/api/test-didit?dry=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.session_create', null)
            ->assertJsonPath('data.session_readback', null);
    }

    public function test_diagnostic_creates_and_reads_back_session(): void
    {
        DiditLaravelClient::shouldReceive('createSession')
            ->once()
            ->andReturn(['session_id' => 'sess-diag-1', 'url' => 'https://verify.didit.me/session/sess-diag-1']);

        DiditLaravelClient::shouldReceive('getSession')
            ->once()
            ->with('sess-diag-1')
            ->andReturn(['status' => 'In Progress']);

        DiditLaravelClient::shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(['status' => 'Approved']);

        $response = $this->getJson('/api/test-didit');

        $response->assertStatus(200)
            ->assertJsonPath('data.dry_run', false)
            ->assertJsonPath('data.session_create.success', true)
            ->assertJsonPath('data.session_create.session_id', 'sess-diag-1')
            ->assertJsonPath('data.session_create.verification_url', 'https://verify.didit.me/session/sess-diag-1')
            ->assertJsonStructure(['data' => ['session_create' => ['latency_ms']]])
            ->assertJsonPath('data.session_readback.success', true)
            ->assertJsonPath('data.session_readback.decision_status', 'In Progress');
    }

    public function test_diagnostic_reports_session_creation_failure(): void
    {
        DiditLaravelClient::shouldReceive('createSession')
            ->once()
            ->andThrow(new DiditRequestException('Invalid workflow_id'));

        DiditLaravelClient::shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(['status' => 'Approved']);

        $response = $this->getJson('/api/test-didit');

        $response->assertStatus(200)
            ->assertJsonPath('data.session_create.success', false)
            ->assertJsonPath('data.session_create.message', 'Invalid workflow_id')
            ->assertJsonPath('data.session_readback', null);
    }

    public function test_diagnostic_signature_self_test_succeeds(): void
    {
        DiditLaravelClient::shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(['status' => 'Approved']);

        $response = $this->getJson('/api/test-didit?dry=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.webhook_signature_self_test.success', true);
    }

    public function test_diagnostic_signature_self_test_reports_failure_when_secret_missing(): void
    {
        config(['didit-laravel-client.webhook_secret' => null]);

        $response = $this->getJson('/api/test-didit?dry=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.webhook_signature_self_test.success', false);
    }
}
