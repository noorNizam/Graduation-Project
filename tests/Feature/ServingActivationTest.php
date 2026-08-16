<?php

namespace Tests\Feature;

use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServingActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherUser;

    private PaymentUnit $hourUnit;

    private Serving $inactiveServing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hourUnit = PaymentUnit::factory()->create(['name' => 'Hour']);

        $this->owner = User::factory()->create(['role' => 'user']);
        $this->otherUser = User::factory()->create(['role' => 'user']);

        $this->inactiveServing = Serving::factory()->create([
            'user_id' => $this->owner->id,
            'unit_id' => $this->hourUnit->id,
            'status' => Serving::STATUS_INACTIVE,
        ]);
    }

    public function test_owner_can_activate_inactive_serving(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/servings/{$this->inactiveServing->id}/activate");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Serving activated');

        $this->assertEquals(Serving::STATUS_ACTIVE, $this->inactiveServing->fresh()->status);
    }

    public function test_activate_forbidden_for_non_owner(): void
    {
        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->postJson("/api/servings/{$this->inactiveServing->id}/activate");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden');

        $this->assertEquals(Serving::STATUS_INACTIVE, $this->inactiveServing->fresh()->status);
    }

    public function test_activate_returns_error_for_missing_serving(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/servings/99999/activate');

        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Serving not found');
    }

    public function test_cannot_activate_serving_that_is_not_inactive(): void
    {
        $activeServing = Serving::factory()->create([
            'user_id' => $this->owner->id,
            'unit_id' => $this->hourUnit->id,
            'status' => Serving::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/servings/{$activeServing->id}/activate");

        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Only inactive servings can be activated');

        $this->assertEquals(Serving::STATUS_ACTIVE, $activeServing->fresh()->status);
    }

    public function test_get_deactivated_servings_lists_inactive_servings(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/servings/my-deactivated');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->inactiveServing->id, $ids);
    }
}
