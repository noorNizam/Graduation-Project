<?php

namespace Tests\Feature;

use App\Domain\Services\RewardServiceInterface;
use App\Infrastructure\Models\RewardModel;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeWallet(int $userId): WalletModel
    {
        return WalletModel::factory()->create(['user_id' => $userId, 'balance' => 0]);
    }

    private function service(): RewardServiceInterface
    {
        return app(RewardServiceInterface::class);
    }

    public function test_increment_service_count_does_not_crash_after_weekly_reset_at_is_set()
    {
        $user = $this->makeUser();

        $this->service()->incrementServiceCount($user->id);
        $this->service()->incrementServiceCount($user->id);

        $user->refresh();
        $this->assertSame(2, $user->services_requested_count);
        $this->assertSame(2, $user->weekly_service_count);
        $this->assertSame(2, $user->monthly_service_count);
    }

    public function test_increment_service_count_resets_weekly_and_monthly_counters_on_new_periods()
    {
        $user = $this->makeUser();

        $this->travelTo(Carbon::create(2026, 8, 17)); // Monday
        $this->service()->incrementServiceCount($user->id);
        $this->service()->incrementServiceCount($user->id);

        $this->travelTo(Carbon::create(2026, 8, 24)); // Next Monday
        $this->service()->incrementServiceCount($user->id);

        $user->refresh();
        $this->assertSame(1, $user->weekly_service_count);
        $this->assertSame(3, $user->monthly_service_count);

        $this->travelTo(Carbon::create(2026, 9, 1)); // Next month
        $this->service()->incrementServiceCount($user->id);

        $user->refresh();
        $this->assertSame(1, $user->weekly_service_count);
        $this->assertSame(1, $user->monthly_service_count);
    }

    public function test_increment_service_count_resets_monthly_counter_across_year_boundary()
    {
        $user = $this->makeUser();

        $this->travelTo(Carbon::create(2025, 12, 15));
        $this->service()->incrementServiceCount($user->id);

        $this->travelTo(Carbon::create(2026, 12, 15)); // Same month number, different year
        $this->service()->incrementServiceCount($user->id);

        $user->refresh();
        $this->assertSame(1, $user->monthly_service_count);
        $this->assertSame(2, $user->services_requested_count);
    }

    public function test_lifetime_reward_applied_once_per_threshold()
    {
        $user = $this->makeUser();
        $this->makeWallet($user->id);

        for ($i = 0; $i < 5; $i++) {
            $this->service()->incrementServiceCount($user->id);
        }

        $result = $this->service()->checkAndApplyRewards($user->id);
        $this->assertTrue($result['success']);
        $this->assertContains('5 service (+2 hour)', $result['data']);

        // 5 services in one week: lifetime (+2) + weekly (+1)
        $wallet = $user->wallets()->first();
        $this->assertSame(3.0, (float) $wallet->balance);
        $this->assertDatabaseCount('rewards', 2);
        $this->assertDatabaseHas('rewards', ['user_id' => $user->id, 'type' => 'lifetime', 'threshold' => 5, 'hours_added' => 2]);

        // Repeated checks must not re-apply
        $result = $this->service()->checkAndApplyRewards($user->id);
        $this->assertSame('There is no new reward', $result['message']);
        $this->assertDatabaseCount('rewards', 2);
        $this->assertSame(3.0, (float) $wallet->refresh()->balance);
    }

    public function test_weekly_reward_applies_once_per_week_and_re_applies_next_week()
    {
        $user = $this->makeUser();
        $this->makeWallet($user->id);

        $this->travelTo(Carbon::create(2026, 8, 17)); // Monday
        for ($i = 0; $i < 3; $i++) {
            $this->service()->incrementServiceCount($user->id);
        }
        $this->service()->checkAndApplyRewards($user->id);
        $this->assertDatabaseCount('rewards', 1);

        $this->travelTo(Carbon::create(2026, 8, 24)); // Next Monday
        for ($i = 0; $i < 3; $i++) {
            $this->service()->incrementServiceCount($user->id);
        }
        $this->service()->checkAndApplyRewards($user->id);

        // Week 2: lifetime (5) + weekly (3) both fire again
        $this->assertDatabaseCount('rewards', 3);
        $this->assertSame(2, RewardModel::where('user_id', $user->id)->where('type', 'weekly')->count());
        $this->assertSame(4.0, (float) $user->wallets()->first()->refresh()->balance);
    }

    public function test_monthly_reward_applies_once_per_month()
    {
        $user = $this->makeUser();
        $this->makeWallet($user->id);

        $this->travelTo(Carbon::create(2026, 8, 17));
        for ($i = 0; $i < 10; $i++) {
            $this->service()->incrementServiceCount($user->id);
        }
        $this->service()->checkAndApplyRewards($user->id);

        // lifetime 5 (+2), lifetime 10 (+3), weekly (+1), monthly (+3)
        $this->assertDatabaseCount('rewards', 4);
        $this->assertSame(1, RewardModel::where('user_id', $user->id)->where('type', 'monthly')->count());
        $this->service()->checkAndApplyRewards($user->id);
        $this->assertDatabaseCount('rewards', 4);
    }

    public function test_get_user_rewards_returns_total_hours()
    {
        $user = $this->makeUser();
        $this->makeWallet($user->id);

        for ($i = 0; $i < 5; $i++) {
            $this->service()->incrementServiceCount($user->id);
        }
        $this->service()->checkAndApplyRewards($user->id);

        $result = $this->service()->getUserRewards($user->id);
        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['total_hours_added']);
        $this->assertCount(2, $result['data']);
    }

    public function test_my_rewards_endpoint_returns_authenticated_users_rewards()
    {
        $user = $this->makeUser();
        $this->makeWallet($user->id);

        for ($i = 0; $i < 5; $i++) {
            $this->service()->incrementServiceCount($user->id);
        }
        $this->service()->checkAndApplyRewards($user->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/my-rewards');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_hours_added', 3);
    }

    public function test_reward_reason_messages_use_weekly_spelling()
    {
        $user = $this->makeUser();
        $this->makeWallet($user->id);

        $this->travelTo(Carbon::create(2026, 8, 17));
        for ($i = 0; $i < 3; $i++) {
            $this->service()->incrementServiceCount($user->id);
        }
        $this->service()->checkAndApplyRewards($user->id);

        $reward = RewardModel::first();
        $this->assertNotNull($reward);
        $this->assertSame('Weekly reward for 3 services (+1 hour)', $reward->reason);
    }
}
