<?php

namespace Tests\Feature;

use App\Infrastructure\Models\IdentityVerificationModel;
use App\Infrastructure\Models\User;
use AwaisJameel\DiditLaravelClient\Exceptions\DiditRequestException;
use AwaisJameel\DiditLaravelClient\Exceptions\WebhookVerificationException;
use AwaisJameel\DiditLaravelClient\Facades\DiditLaravelClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['didit-laravel-client.webhook_secret' => 'test-secret']);
        config(['didit-laravel-client.api_key' => 'test-api-key']);

        $this->user = User::factory()->create(['role' => 'user']);
    }

    private function createRecord(array $attributes = []): IdentityVerificationModel
    {
        return IdentityVerificationModel::create(array_merge([
            'user_id' => $this->user->id,
            'session_id' => 'sess-'.str()->random(8),
            'vendor_token' => str()->random(32),
            'status' => 'pending',
        ], $attributes));
    }

    public function test_start_returns_verification_url_and_creates_record(): void
    {
        $capturedVendorData = null;

        DiditLaravelClient::shouldReceive('createSession')
            ->once()
            ->andReturnUsing(function ($callbackUrl, $vendorData, $options) use (&$capturedVendorData) {
                $capturedVendorData = $vendorData;

                return ['session_id' => 'sess-123', 'url' => 'https://verify.didit.me/session/sess-123'];
            });

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/identity/verify');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_url', 'https://verify.didit.me/session/sess-123');

        $this->assertDatabaseHas('identity_verifications', [
            'user_id' => $this->user->id,
            'session_id' => 'sess-123',
            'status' => 'pending',
        ]);

        $record = IdentityVerificationModel::where('session_id', 'sess-123')->first();
        $this->assertNotNull($record->vendor_token);
        $this->assertNotEquals((string) $this->user->id, $record->vendor_token);
        $this->assertSame($record->vendor_token, $capturedVendorData);
    }

    public function test_start_is_idempotent_when_pending_session_exists(): void
    {
        $this->createRecord([
            'verification_url' => 'https://verify.didit.me/session/sess-existing',
        ]);

        DiditLaravelClient::shouldReceive('createSession')
            ->never();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/identity/verify');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_url', 'https://verify.didit.me/session/sess-existing');

        $this->assertSame(1, IdentityVerificationModel::count());
    }

    public function test_start_returns_422_when_already_verified(): void
    {
        $this->user->is_identity_verified = true;
        $this->user->save();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/identity/verify');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Already verified');
    }

    public function test_start_requires_authentication(): void
    {
        $this->postJson('/api/identity/verify')
            ->assertStatus(401);
    }

    public function test_start_handles_didit_api_failure_gracefully(): void
    {
        DiditLaravelClient::shouldReceive('createSession')
            ->once()
            ->andThrow(new DiditRequestException('boom'));

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/identity/verify');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('identity_verifications', 0);
    }

    public function test_status_returns_record_and_user_flags(): void
    {
        $record = $this->createRecord(['status' => 'approved', 'verified_at' => now()]);
        $this->user->is_identity_verified = true;
        $this->user->identity_verified_at = now();
        $this->user->save();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/identity/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.session_id', $record->session_id)
            ->assertJsonPath('data.is_identity_verified', true);
    }

    public function test_status_returns_none_when_no_record_exists(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/identity/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'none')
            ->assertJsonPath('data.is_identity_verified', false);
    }

    public function test_webhook_updates_the_record_matching_vendor_token_not_the_latest(): void
    {
        $oldRecord = $this->createRecord();
        $newRecord = $this->createRecord();

        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'status' => 'Approved',
                'vendor_data' => $oldRecord->vendor_token,
            ]);

        $response = $this->postJson('/api/identity/webhook', ['dummy' => 'body']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('identity_verifications', [
            'id' => $oldRecord->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('identity_verifications', [
            'id' => $newRecord->id,
            'status' => 'pending',
        ]);
        $this->assertTrue($this->user->fresh()->is_identity_verified);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andThrow(new WebhookVerificationException('Invalid webhook signature'));

        $response = $this->postJson('/api/identity/webhook', ['status' => 'Approved']);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->assertFalse($this->user->fresh()->is_identity_verified);
    }

    public function test_webhook_approves_user_on_approved_status(): void
    {
        $record = $this->createRecord();

        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'session_id' => $record->session_id,
                'status' => 'Approved',
                'vendor_data' => $record->vendor_token,
            ]);

        $response = $this->postJson('/api/identity/webhook', ['dummy' => 'body']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $record->id,
            'status' => 'approved',
        ]);
        $this->assertTrue($this->user->fresh()->is_identity_verified);
        $this->assertNotNull($this->user->fresh()->identity_verified_at);
    }

    public function test_webhook_resolves_record_by_vendor_token(): void
    {
        $record = $this->createRecord();

        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'status' => 'Approved',
                'vendor_data' => $record->vendor_token,
            ]);

        $response = $this->postJson('/api/identity/webhook', ['dummy' => 'body']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('identity_verifications', [
            'id' => $record->id,
            'status' => 'approved',
        ]);
        $this->assertTrue($this->user->fresh()->is_identity_verified);
    }

    public function test_webhook_declines_user(): void
    {
        $record = $this->createRecord();
        $this->user->is_identity_verified = true;
        $this->user->identity_verified_at = now();
        $this->user->save();

        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'session_id' => $record->session_id,
                'status' => 'Declined',
                'vendor_data' => $record->vendor_token,
            ]);

        $response = $this->postJson('/api/identity/webhook', ['dummy' => 'body']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('identity_verifications', [
            'id' => $record->id,
            'status' => 'declined',
        ]);
        $this->assertFalse($this->user->fresh()->is_identity_verified);
        $this->assertNull($this->user->fresh()->identity_verified_at);
    }

    public function test_webhook_ignores_unknown_status_without_approving(): void
    {
        $record = $this->createRecord();

        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'session_id' => $record->session_id,
                'status' => 'In Progress',
                'vendor_data' => $record->vendor_token,
            ]);

        $response = $this->postJson('/api/identity/webhook', ['dummy' => 'body']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('identity_verifications', [
            'id' => $record->id,
            'status' => 'pending',
        ]);
        $this->assertFalse($this->user->fresh()->is_identity_verified);
    }

    public function test_webhook_rejects_plaintext_user_id_in_vendor_data(): void
    {
        $this->createRecord();
        $otherUser = User::factory()->create(['role' => 'user']);

        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'status' => 'Approved',
                'vendor_data' => (string) $otherUser->id,
            ]);

        $response = $this->postJson('/api/identity/webhook', ['dummy' => 'body']);

        $response->assertStatus(422);
        $this->assertFalse($otherUser->fresh()->is_identity_verified);
        $this->assertSame(1, IdentityVerificationModel::count());
    }

    public function test_webhook_returns_error_when_no_record_matches(): void
    {
        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'session_id' => 'unknown-session',
                'status' => 'Approved',
                'vendor_data' => 'unknown-token',
            ]);

        $response = $this->postJson('/api/identity/webhook', ['dummy' => 'body']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('identity_verifications', 0);
        $this->assertFalse($this->user->fresh()->is_identity_verified);
    }

    public function test_webhook_accepts_valid_legacy_signature_end_to_end(): void
    {
        $record = $this->createRecord();

        $payload = [
            'session_id' => $record->session_id,
            'status' => 'Approved',
            'vendor_data' => $record->vendor_token,
            'timestamp' => time(),
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, 'test-secret');

        $response = $this->postJson('/api/identity/webhook', $payload, ['x-signature' => $signature]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $record->id,
            'status' => 'approved',
        ]);
        $this->assertTrue($this->user->fresh()->is_identity_verified);
    }

    public function test_webhook_rejects_bad_signature_end_to_end(): void
    {
        $record = $this->createRecord();

        $payload = [
            'session_id' => $record->session_id,
            'status' => 'Approved',
            'vendor_data' => $record->vendor_token,
            'timestamp' => time(),
        ];

        $response = $this->postJson('/api/identity/webhook', $payload, ['x-signature' => 'forged-signature']);

        $response->assertStatus(401);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $record->id,
            'status' => 'pending',
        ]);
        $this->assertFalse($this->user->fresh()->is_identity_verified);
    }

    public function test_start_reuses_pending_session_within_ttl(): void
    {
        $record = $this->createRecord([
            'session_id' => 'sess-almost-old',
            'verification_url' => 'https://verify.didit.me/session/sess-almost-old',
        ]);
        $record->created_at = now()->subHours(23);
        $record->save();

        DiditLaravelClient::shouldReceive('createSession')->never();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/identity/verify')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_url', 'https://verify.didit.me/session/sess-almost-old');

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $record->id,
            'status' => 'pending',
        ]);
    }

    public function test_start_expires_stale_pending_session_and_creates_fresh_one(): void
    {
        $stale = $this->createRecord([
            'session_id' => 'sess-stale',
            'verification_url' => 'https://verify.didit.me/session/sess-stale',
        ]);
        $stale->created_at = now()->subHours(30);
        $stale->save();

        DiditLaravelClient::shouldReceive('createSession')
            ->once()
            ->andReturn(['session_id' => 'sess-fresh', 'url' => 'https://verify.didit.me/session/sess-fresh']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/identity/verify');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_url', 'https://verify.didit.me/session/sess-fresh');

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $stale->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('identity_verifications', [
            'session_id' => 'sess-fresh',
            'status' => 'pending',
        ]);
    }

    public function test_start_expires_pending_session_without_url(): void
    {
        $broken = $this->createRecord([
            'session_id' => 'sess-broken',
            'verification_url' => null,
        ]);

        DiditLaravelClient::shouldReceive('createSession')
            ->once()
            ->andReturn(['session_id' => 'sess-replacement', 'url' => 'https://verify.didit.me/session/sess-replacement']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/identity/verify')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $broken->id,
            'status' => 'expired',
        ]);
    }

    public function test_webhook_marks_record_expired_on_terminal_lifecycle_statuses(): void
    {
        foreach (['Expired', 'Abandoned', 'Canceled', 'Cancelled'] as $vendorStatus) {
            $record = $this->createRecord();

            DiditLaravelClient::shouldReceive('processWebhook')
                ->once()
                ->andReturn([
                    'session_id' => $record->session_id,
                    'status' => $vendorStatus,
                    'vendor_data' => $record->vendor_token,
                ]);

            $this->postJson('/api/identity/webhook', ['dummy' => 'body'])
                ->assertStatus(200)
                ->assertJsonPath('success', true);

            $this->assertDatabaseHas('identity_verifications', [
                'id' => $record->id,
                'status' => 'expired',
            ]);
            $this->assertFalse($this->user->fresh()->is_identity_verified);
        }
    }

    public function test_late_declined_webhook_does_not_unverify_user_with_approved_session(): void
    {
        $oldRecord = $this->createRecord(['session_id' => 'sess-old']);
        $this->createRecord(['session_id' => 'sess-approved', 'status' => 'approved']);

        $this->user->is_identity_verified = true;
        $this->user->identity_verified_at = now();
        $this->user->save();

        DiditLaravelClient::shouldReceive('processWebhook')
            ->once()
            ->andReturn([
                'session_id' => $oldRecord->session_id,
                'status' => 'Declined',
                'vendor_data' => $oldRecord->vendor_token,
            ]);

        $this->postJson('/api/identity/webhook', ['dummy' => 'body'])
            ->assertStatus(200);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $oldRecord->id,
            'status' => 'declined',
        ]);
        $this->assertTrue($this->user->fresh()->is_identity_verified);
        $this->assertNotNull($this->user->fresh()->identity_verified_at);
    }
}
