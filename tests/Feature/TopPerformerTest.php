<?php

namespace Tests\Feature;

use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\TopPerformer;
use App\Infrastructure\Models\User;
use App\Models\ServingCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TopPerformerTest extends TestCase
{
    use RefreshDatabase;

    private int $paidTypeId;

    private int $voluntaryTypeId;

    private int $hourUnitId;

    private int $usdUnitId;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->hourUnitId = PaymentUnit::factory()->create(['name' => 'Hour'])->id;
        $this->usdUnitId = PaymentUnit::factory()->create(['name' => 'USD'])->id;

        $this->paidTypeId = ServingType::factory()->create(['name' => 'paid'])->id;
        $this->voluntaryTypeId = ServingType::factory()->create(['name' => 'voluntary'])->id;

        ServingCategory::factory()->create(['name' => 'Test Category']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_calculate_command_ranks_users_by_score(): void
    {
        Carbon::setTestNow('2026-02-28 23:00:00');

        $requester = $this->createProvider();
        $providerA = $this->createProvider(['full_name' => 'Provider A']);
        $providerB = $this->createProvider(['full_name' => 'Provider B']);
        $providerC = $this->createProvider(['full_name' => 'Provider C']);

        $servingA = $this->createServing($providerA, $this->paidTypeId, $this->hourUnitId, 10, 4.0);
        $servingB = $this->createServing($providerB, $this->paidTypeId, $this->hourUnitId, 10, 5.0);
        $servingC = $this->createServing($providerC, $this->paidTypeId, $this->hourUnitId, 10, 3.0);

        $this->completeRequest($servingA, $requester, '2026-02-10 10:00:00');
        $this->completeRequest($servingA, $requester, '2026-02-15 10:00:00');
        $this->completeRequest($servingB, $requester, '2026-02-10 10:00:00');
        $this->completeRequest($servingC, $requester, '2026-02-10 10:00:00');

        $this->artisan('top-performers:calculate')->assertSuccessful();

        $this->assertDatabaseHas('top_performers', ['user_id' => $providerA->id, 'rank' => 1]);
        $this->assertDatabaseHas('top_performers', ['user_id' => $providerB->id, 'rank' => 2]);
        $this->assertDatabaseHas('top_performers', ['user_id' => $providerC->id, 'rank' => 3]);
        $this->assertNotNull(DB::table('top_performers')->value('created_at'));
        $this->assertNotNull(DB::table('top_performers')->value('updated_at'));
    }

    public function test_calculate_command_uses_default_rating_when_user_has_no_ratings(): void
    {
        Carbon::setTestNow('2026-02-28 23:00:00');

        $requester = $this->createProvider();
        $noRatingUser = $this->createProvider();
        $ratedUser = $this->createProvider();

        $servingNoRating = $this->createServing($noRatingUser, $this->paidTypeId, $this->hourUnitId, 10, 0);
        $servingRated = $this->createServing($ratedUser, $this->paidTypeId, $this->hourUnitId, 5, 4.0);

        $this->completeRequest($servingNoRating, $requester, '2026-02-10 10:00:00');
        $this->completeRequest($servingRated, $requester, '2026-02-10 10:00:00');

        $this->artisan('top-performers:calculate')->assertSuccessful();

        $this->assertDatabaseHas('top_performers', ['user_id' => $noRatingUser->id, 'rank' => 1]);
        $this->assertDatabaseHas('top_performers', ['user_id' => $ratedUser->id, 'rank' => 2]);
    }

    public function test_calculate_command_ignores_non_hour_and_non_paid_servings(): void
    {
        Carbon::setTestNow('2026-02-28 23:00:00');

        $requester = $this->createProvider();
        $hourPaidUser = $this->createProvider();
        $usdPaidUser = $this->createProvider();
        $voluntaryUser = $this->createProvider();

        $hourPaid = $this->createServing($hourPaidUser, $this->paidTypeId, $this->hourUnitId, 10, 4.0);
        $usdPaid = $this->createServing($usdPaidUser, $this->paidTypeId, $this->usdUnitId, 50, 4.0);
        $voluntary = $this->createServing($voluntaryUser, $this->voluntaryTypeId, $this->hourUnitId, 0, 4.0);

        $this->completeRequest($hourPaid, $requester, '2026-02-10 10:00:00');
        $this->completeRequest($usdPaid, $requester, '2026-02-10 10:00:00');
        $this->completeRequest($voluntary, $requester, '2026-02-10 10:00:00');

        $this->artisan('top-performers:calculate')->assertSuccessful();

        $this->assertDatabaseHas('top_performers', ['user_id' => $hourPaidUser->id, 'rank' => 1]);
        $this->assertDatabaseMissing('top_performers', ['user_id' => $usdPaidUser->id]);
        $this->assertDatabaseMissing('top_performers', ['user_id' => $voluntaryUser->id]);
    }

    public function test_calculate_command_only_counts_completions_within_the_ranked_month(): void
    {
        Carbon::setTestNow('2026-02-28 23:00:00');

        $requester = $this->createProvider();
        $inMonthUser = $this->createProvider();
        $outMonthUser = $this->createProvider();

        $inServing = $this->createServing($inMonthUser, $this->paidTypeId, $this->hourUnitId, 10, 4.0);
        $outServing = $this->createServing($outMonthUser, $this->paidTypeId, $this->hourUnitId, 10, 4.0);

        $this->completeRequest($inServing, $requester, '2026-02-10 10:00:00');
        $this->completeRequest($outServing, $requester, '2026-01-10 10:00:00');

        $this->artisan('top-performers:calculate')->assertSuccessful();

        $this->assertDatabaseHas('top_performers', ['user_id' => $inMonthUser->id]);
        $this->assertDatabaseMissing('top_performers', ['user_id' => $outMonthUser->id]);
    }

    public function test_calculate_command_keeps_previous_months_rows_when_rerun(): void
    {
        $requester = $this->createProvider();
        $provider = $this->createProvider();
        $serving = $this->createServing($provider, $this->paidTypeId, $this->hourUnitId, 10, 4.0);

        Carbon::setTestNow('2026-02-28 23:00:00');
        $this->completeRequest($serving, $requester, '2026-02-10 10:00:00');
        $this->artisan('top-performers:calculate')->assertSuccessful();

        Carbon::setTestNow('2026-03-31 23:00:00');
        $this->completeRequest($serving, $requester, '2026-03-10 10:00:00');
        $this->artisan('top-performers:calculate')->assertSuccessful();

        $this->assertDatabaseHas('top_performers', [
            'user_id' => $provider->id,
            'rank' => 1,
            'date' => '2026-02-28 23:00:00',
        ]);
        $this->assertDatabaseHas('top_performers', [
            'user_id' => $provider->id,
            'rank' => 1,
            'date' => '2026-03-31 23:00:00',
        ]);

        $this->assertEquals(2, DB::table('top_performers')->count());
    }

    public function test_calculate_command_caps_rankings_at_ten_users(): void
    {
        Carbon::setTestNow('2026-02-28 23:00:00');

        $requester = $this->createProvider();
        $users = [];

        for ($i = 0; $i < 12; $i++) {
            $users[] = $this->createProvider();
        }

        foreach ($users as $user) {
            $serving = $this->createServing($user, $this->paidTypeId, $this->hourUnitId, 10, 1.0);
            $this->completeRequest($serving, $requester, '2026-02-10 10:00:00');
        }

        $this->artisan('top-performers:calculate')->assertSuccessful();

        $this->assertEquals(10, DB::table('top_performers')->count());
        $this->assertDatabaseHas('top_performers', ['rank' => 10]);
        $this->assertDatabaseMissing('top_performers', ['rank' => 11]);
    }

    public function test_top_performers_api_returns_previous_month_by_default(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');

        $userA = $this->createProvider(['full_name' => 'Alpha', 'profile_picture' => 'pic-a.jpg']);
        $userB = $this->createProvider(['full_name' => 'Beta']);

        TopPerformer::create([
            'user_id' => $userA->id,
            'serving_type_id' => $this->paidTypeId,
            'rank' => 1,
            'date' => '2026-02-28 23:00:00',
        ]);
        TopPerformer::create([
            'user_id' => $userB->id,
            'serving_type_id' => $this->paidTypeId,
            'rank' => 2,
            'date' => '2026-02-28 23:00:00',
        ]);

        $response = $this->postJson('/api/servings/top-performers', [
            'serving_type_id' => $this->paidTypeId,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals([1, 2], array_column($data, 'rank'));
        $this->assertEquals('Alpha', $data[0]['full_name']);
        $this->assertEquals('pic-a.jpg', $data[0]['profile_picture']);
        $this->assertEquals($userB->id, $data[1]['user_id']);
        $this->assertStringContainsString('2026-02-28', $data[0]['date']);
    }

    public function test_top_performers_api_returns_performers_for_requested_month(): void
    {
        $user = $this->createProvider();

        TopPerformer::create([
            'user_id' => $user->id,
            'serving_type_id' => $this->paidTypeId,
            'rank' => 1,
            'date' => '2026-02-28 23:00:00',
        ]);

        $response = $this->postJson('/api/servings/top-performers', [
            'serving_type_id' => $this->paidTypeId,
            'month' => '2026-02',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_top_performers_api_returns_empty_data_when_no_rows_for_month(): void
    {
        $user = $this->createProvider();

        TopPerformer::create([
            'user_id' => $user->id,
            'serving_type_id' => $this->paidTypeId,
            'rank' => 1,
            'date' => '2026-02-28 23:00:00',
        ]);

        $response = $this->postJson('/api/servings/top-performers', [
            'serving_type_id' => $this->paidTypeId,
            'month' => '2026-01',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_top_performers_api_requires_serving_type_id(): void
    {
        $this->postJson('/api/servings/top-performers', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_calculate_command_writes_cache_for_the_month(): void
    {
        Carbon::setTestNow('2026-02-28 23:00:00');

        $requester = $this->createProvider();
        $provider = $this->createProvider(['full_name' => 'Cached User']);
        $serving = $this->createServing($provider, $this->paidTypeId, $this->hourUnitId, 10, 4.0);
        $this->completeRequest($serving, $requester, '2026-02-10 10:00:00');

        $this->artisan('top-performers:calculate')->assertSuccessful();

        $this->assertTrue(Cache::has('top_performers:2026-02'));

        $cached = Cache::get('top_performers:2026-02');
        $this->assertCount(1, $cached);
        $this->assertEquals($provider->id, $cached[0]['user_id']);
        $this->assertEquals(1, $cached[0]['rank']);
        $this->assertEquals('Cached User', $cached[0]['full_name']);
    }

    public function test_top_performers_api_reads_from_cache_after_scheduler(): void
    {
        Carbon::setTestNow('2026-02-28 23:00:00');

        $requester = $this->createProvider();
        $provider = $this->createProvider(['full_name' => 'Cached User']);
        $serving = $this->createServing($provider, $this->paidTypeId, $this->hourUnitId, 10, 4.0);
        $this->completeRequest($serving, $requester, '2026-02-10 10:00:00');

        $this->artisan('top-performers:calculate')->assertSuccessful();

        DB::table('top_performers')->delete();

        $response = $this->postJson('/api/servings/top-performers', [
            'serving_type_id' => $this->paidTypeId,
            'month' => '2026-02',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Cached User')
            ->assertJsonPath('data.0.rank', 1);
    }

    public function test_top_performers_api_falls_back_to_database_when_cache_is_missing(): void
    {
        $user = $this->createProvider(['full_name' => 'Db User']);

        TopPerformer::create([
            'user_id' => $user->id,
            'serving_type_id' => $this->paidTypeId,
            'rank' => 1,
            'date' => '2026-02-28 23:00:00',
        ]);

        $response = $this->postJson('/api/servings/top-performers', [
            'serving_type_id' => $this->paidTypeId,
            'month' => '2026-02',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Db User');
    }

    private function createProvider(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'user'], $attributes));
    }

    private function createServing(User $user, int $typeId, int $unitId, float $cost, float $rate): Serving
    {
        return Serving::factory()->create([
            'user_id' => $user->id,
            'serving_type_id' => $typeId,
            'unit_id' => $unitId,
            'cost_amount' => $cost,
            'rate' => $rate,
            'status' => Serving::STATUS_ACTIVE,
        ]);
    }

    private function completeRequest(Serving $serving, User $requester, string $completedAt): void
    {
        ServingRequest::create([
            'serving_id' => $serving->id,
            'requester_id' => $requester->id,
            'status' => ServingRequest::STATUS_COMPLETED,
            'completed_at' => $completedAt,
        ]);
    }
}
