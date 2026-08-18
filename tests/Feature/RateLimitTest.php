<?php

namespace Tests\Feature;

use App\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_login_throttled_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'throttle@system.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'throttle@system.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'throttle@system.com',
            'password' => 'correct-password',
        ])->assertStatus(429)
            ->assertJson(['success' => false]);
    }

    public function test_send_otp_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/send-otp', ['email' => 'otp@system.com']);
        }

        $this->postJson('/api/auth/send-otp', ['email' => 'otp@system.com'])
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    }

    public function test_anonymous_api_limiter_returns_429_when_exceeded(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/health')->assertStatus(200);
        }

        $this->getJson('/api/health')
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_api_limiter_allows_more_than_anonymous_limit(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        for ($i = 0; $i < 31; $i++) {
            $this->withToken($token)->getJson('/api/health')->assertStatus(200);
        }
    }

    public function test_authenticated_api_limiter_returns_429_after_120_requests(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        for ($i = 0; $i < 120; $i++) {
            $this->withToken($token)->getJson('/api/health')->assertStatus(200);
        }

        $this->withToken($token)->getJson('/api/health')
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    }
}
