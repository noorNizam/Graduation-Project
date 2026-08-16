<?php

namespace Tests\Feature;

use App\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $token = $user->createToken('auth-token')->plainTextToken;
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }
}
