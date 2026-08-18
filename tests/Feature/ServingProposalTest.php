<?php

namespace Tests\Feature;

use App\Application\Services\ServingProposalService;
use App\Domain\Services\ServingProposalServiceInterface;
use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\UserSearchHistory;
use App\Models\ServingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServingProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $user;

    private int $paidTypeId;

    private int $hourUnitId;

    protected function setUp(): void
    {
        parent::setUp();

        $hourUnit = PaymentUnit::factory()->create(['name' => 'Hour']);
        $this->hourUnitId = $hourUnit->id;
        $this->paidTypeId = ServingType::factory()->create(['name' => 'paid'])->id;
        ServingCategory::factory()->create(['name' => 'Test Category']);

        $this->owner = User::factory()->create(['role' => 'user']);
        $this->user = User::factory()->create(['role' => 'user']);
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path(ServingProposalService::INDEX_STORAGE).'/*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_proposed_servings_from_arabic_search_history(): void
    {
        $serving = $this->createServing($this->owner, 'تركيب مواسير السباكة للمنازل');
        $this->createServing($this->owner, 'دهان وطلاء الجدران');
        $this->addHistory($this->user, 'سباكة');
        $this->rebuildIndex();

        $result = $this->proposed($this->user->id);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']);
        $this->assertEquals($serving->id, $result['data'][0]['id']);
        $this->assertEquals('based_on_searches', $result['data'][0]['reason']);
        $this->assertGreaterThan(0, $result['data'][0]['score']);
    }

    public function test_proposed_servings_from_english_search_history(): void
    {
        $serving = $this->createServing($this->owner, 'Plumbing services for homes and offices');
        $this->addHistory($this->user, 'plumbing');
        $this->rebuildIndex();

        $result = $this->proposed($this->user->id);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']);
        $this->assertEquals($serving->id, $result['data'][0]['id']);
    }

    public function test_proposed_excludes_own_servings(): void
    {
        $this->createServing($this->owner, 'تركيب السباكة للمنازل');
        $this->createServing($this->user, 'خدمة السباكة الخاصة بي');
        $this->addHistory($this->user, 'سباكة');
        $this->rebuildIndex();

        $result = $this->proposed($this->user->id);

        $this->assertCount(1, $result['data']);
        $this->assertEquals($this->owner->id, $result['data'][0]['user_id']);
    }

    public function test_proposed_excludes_requested_servings(): void
    {
        $requested = $this->createServing($this->owner, 'تركيب السباكة للمنازل');
        $available = $this->createServing($this->owner, 'تصليح أعطال السباكة');
        $this->addHistory($this->user, 'سباكة');

        ServingRequest::create([
            'serving_id' => $requested->id,
            'requester_id' => $this->user->id,
        ]);

        $this->rebuildIndex();

        $result = $this->proposed($this->user->id);

        $this->assertCount(1, $result['data']);
        $this->assertEquals($available->id, $result['data'][0]['id']);
    }

    public function test_empty_history_falls_back_to_trending(): void
    {
        $other = User::factory()->create(['role' => 'user']);

        $popular = $this->createServing($this->owner, 'خدمة السباكة الشائعة');
        $quiet = $this->createServing($this->owner, 'خدمة نادرة');

        ServingRequest::create([
            'serving_id' => $popular->id,
            'requester_id' => $other->id,
        ]);

        $result = $this->proposed($this->user->id);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals('trending', $result['data'][0]['reason']);
        $this->assertEquals($popular->id, $result['data'][0]['id']);
        $this->assertNull($result['data'][0]['score']);
    }

    public function test_missing_index_falls_back_to_trending(): void
    {
        $this->createServing($this->owner, 'خدمة السباكة للمنازل');
        $this->addHistory($this->user, 'سباكة');
        $this->rebuildIndex();

        foreach (glob(storage_path(ServingProposalService::INDEX_STORAGE).'/*') ?: [] as $file) {
            @unlink($file);
        }

        $result = $this->proposed($this->user->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('trending', $result['data'][0]['reason']);
    }

    public function test_inactive_servings_are_not_indexed(): void
    {
        $this->createServing($this->owner, 'تركيب مواسير السباكة', '', Serving::STATUS_ACTIVE);
        $this->createServing($this->owner, 'خدمة الكهرباء', '', Serving::STATUS_INACTIVE);

        $this->addHistory($this->user, 'كهرباء');
        $this->rebuildIndex();

        $electric = $this->proposed($this->user->id);
        $this->assertTrue($electric['success']);
        $this->assertTrue($electric['data']->isNotEmpty());
        $this->assertEquals('trending', $electric['data'][0]['reason']);
        $this->assertStringNotContainsString('كهرباء', $electric['data'][0]['title']);

        $this->addHistory($this->user, 'سباكة');
        $plumbing = $this->proposed($this->user->id);
        $this->assertCount(1, $plumbing['data']);
        $this->assertStringContainsString('سباك', $plumbing['data'][0]['title']);
    }

    public function test_proposed_uses_cross_lingual_synonyms(): void
    {
        $serving = $this->createServing($this->owner, 'Plumbing services for homes and offices');
        $this->createServing($this->owner, 'خدمة دهان الجدران');
        $this->importSampleSynonyms();
        $this->addHistory($this->user, 'سباكة');
        $this->rebuildIndex();

        $result = $this->proposed($this->user->id);

        $this->assertCount(1, $result['data']);
        $this->assertEquals($serving->id, $result['data'][0]['id']);
        $this->assertEquals('based_on_searches', $result['data'][0]['reason']);
    }

    public function test_proposed_endpoint_returns_recommendations(): void
    {
        $this->createServing($this->owner, 'تركيب مواسير السباكة للمنازل');
        $this->addHistory($this->user, 'سباكة');
        $this->rebuildIndex();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/servings/proposed');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_proposed_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/servings/proposed')->assertUnauthorized();
    }

    public function test_proposed_endpoint_validates_take_limit(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/servings/proposed?take=51')
            ->assertStatus(422);
    }

    public function test_rebuild_index_command_succeeds(): void
    {
        $this->createServing($this->owner, 'خدمة السباكة للمنازل');

        $this->artisan('serving-index:rebuild')->assertExitCode(0);

        $this->assertFileExists(storage_path(ServingProposalService::INDEX_STORAGE).'/'.ServingProposalService::INDEX_NAME);
    }

    public function test_proposed_matches_queries_with_punctuation(): void
    {
        $serving = $this->createServing($this->owner, 'تركيب مواسير السباكة للمنازل');
        $this->addHistory($this->user, 'سباكة؟');
        $this->rebuildIndex();

        $result = $this->proposed($this->user->id);

        $this->assertCount(1, $result['data']);
        $this->assertEquals($serving->id, $result['data'][0]['id']);
    }

    public function test_proposed_honors_skip_and_take(): void
    {
        foreach (['تركيب مواسير السباكة', 'تنظيف مواسير السباكة', 'تسليك مواسير السباكة', 'تبديل مواسير السباكة'] as $title) {
            $this->createServing($this->owner, $title);
        }
        $this->addHistory($this->user, 'سباكة');
        $this->rebuildIndex();

        $page1 = $this->proposed($this->user->id, 2, 0);
        $page2 = $this->proposed($this->user->id, 2, 2);

        $this->assertCount(2, $page1['data']);
        $this->assertCount(2, $page2['data']);

        $ids1 = $page1['data']->pluck('id')->all();
        $ids2 = $page2['data']->pluck('id')->all();
        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    public function test_proposed_falls_back_to_trending_when_all_matches_are_own(): void
    {
        $this->createServing($this->user, 'تسليك مجاري المنازل');
        $other = User::factory()->create(['role' => 'user']);
        $popular = $this->createServing($this->owner, 'خدمة السباكة الشائعة');
        $this->addHistory($this->user, 'تسليك');
        $this->rebuildIndex();

        ServingRequest::create([
            'serving_id' => $popular->id,
            'requester_id' => $other->id,
        ]);

        $result = $this->proposed($this->user->id);

        $this->assertEquals('trending', $result['data'][0]['reason']);
        $this->assertEquals($popular->id, $result['data'][0]['id']);
    }

    public function test_index_status_reports_missing_index(): void
    {
        $this->createServing($this->owner, 'تركيب مواسير السباكة');

        $status = app(ServingProposalServiceInterface::class)->indexStatus();

        $this->assertTrue($status['success']);
        $this->assertFalse($status['data']['exists']);
        $this->assertSame(1, $status['data']['active_servings']);
        $this->assertNull($status['data']['indexed_docs']);
    }

    public function test_index_status_reports_built_index(): void
    {
        $this->createServing($this->owner, 'تركيب مواسير السباكة');
        $this->createServing($this->owner, 'دهان وطلاء الجدران');
        $this->rebuildIndex();

        $status = app(ServingProposalServiceInterface::class)->indexStatus();

        $this->assertTrue($status['success']);
        $this->assertTrue($status['data']['exists']);
        $this->assertSame(2, $status['data']['indexed_docs']);
        $this->assertSame(2, $status['data']['active_servings']);
        $this->assertTrue($status['data']['up_to_date']);
        $this->assertFileExists($status['data']['path']);
    }

    private function createServing(User $user, string $title, string $description = '', string $status = Serving::STATUS_ACTIVE): Serving
    {
        return Serving::factory()->create([
            'user_id' => $user->id,
            'serving_type_id' => $this->paidTypeId,
            'unit_id' => $this->hourUnitId,
            'title' => $title,
            'description' => $description !== '' ? $description : $title,
            'status' => $status,
        ]);
    }

    private function addHistory(User $user, string $query): void
    {
        UserSearchHistory::create([
            'user_id' => $user->id,
            'query' => $query,
            'searched_at' => now(),
        ]);
    }

    private function rebuildIndex(): void
    {
        $result = app(ServingProposalServiceInterface::class)->rebuildIndex();
        $this->assertTrue($result['success']);
    }

    private function importSampleSynonyms(): void
    {
        $this->artisan('wordnet:import', ['directory' => __DIR__.'/../fixtures/wordnet'])
            ->assertExitCode(0);
    }

    private function proposed(int $userId, int $take = 10, int $skip = 0): array
    {
        return app(ServingProposalServiceInterface::class)->getProposedServings($userId, $skip, $take);
    }
}
