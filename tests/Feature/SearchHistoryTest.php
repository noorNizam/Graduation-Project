<?php

namespace Tests\Feature;

use App\Domain\Repositories\UserSearchHistoryRepositoryInterface;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\UserSearchHistory;
use App\Jobs\LogSearchHistoryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_search_with_name_logs_history(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/servings/search', ['name' => 'سباكة']);

        $this->assertDatabaseHas('user_search_history', [
            'user_id' => $user->id,
            'query' => 'سباكة',
        ]);
    }

    public function test_search_without_name_does_not_log_history(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/servings/search', []);

        $this->assertDatabaseCount('user_search_history', 0);
    }

    public function test_anonymous_search_does_not_log_history(): void
    {
        $this->postJson('/api/servings/search', ['name' => 'سباكة']);

        $this->assertDatabaseCount('user_search_history', 0);
    }

    public function test_authenticated_search_logs_history_via_bearer_token(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/servings/search', ['name' => 'كهرباء']);

        $this->assertDatabaseHas('user_search_history', [
            'user_id' => $user->id,
            'query' => 'كهرباء',
        ]);
    }

    public function test_job_creates_history_record_with_trimmed_query(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        LogSearchHistoryJob::dispatchSync($user->id, '  كهرباء  ');

        $this->assertDatabaseHas('user_search_history', [
            'user_id' => $user->id,
            'query' => 'كهرباء',
        ]);
    }

    public function test_job_ignores_blank_query(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        LogSearchHistoryJob::dispatchSync($user->id, '   ');

        $this->assertDatabaseCount('user_search_history', 0);
    }

    public function test_repository_returns_only_searches_within_window(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        UserSearchHistory::create([
            'user_id' => $user->id,
            'query' => 'قديمة',
            'searched_at' => now()->subDays(40),
        ]);
        UserSearchHistory::create([
            'user_id' => $user->id,
            'query' => 'حديثة',
            'searched_at' => now()->subDay(),
        ]);

        $recent = app(UserSearchHistoryRepositoryInterface::class)->findRecentByUserId($user->id);

        $this->assertCount(1, $recent);
        $this->assertEquals('حديثة', $recent->first()->query);
    }
}
