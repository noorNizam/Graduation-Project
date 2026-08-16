<?php

namespace Tests\Feature;

use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Models\ServingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServingTypeDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private ServingType $paidType;

    private ServingType $voluntaryType;

    private PaymentUnit $hourUnit;

    private PaymentUnit $sypUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'user']);

        $this->paidType = ServingType::factory()->create(['name' => 'paid']);
        $this->voluntaryType = ServingType::factory()->create(['name' => 'voluntary']);

        $this->hourUnit = PaymentUnit::factory()->create(['name' => 'Hour']);
        $this->sypUnit = PaymentUnit::factory()->create(['name' => 'SYP']);

        ServingCategory::factory()->create(['name' => 'Test Category']);
    }

    private function createServing(ServingType $type, PaymentUnit $unit): Serving
    {
        return Serving::factory()->create([
            'user_id' => $this->owner->id,
            'serving_type_id' => $type->id,
            'unit_id' => $unit->id,
            'cost_amount' => 50,
            'status' => Serving::STATUS_ACTIVE,
        ]);
    }

    public function test_paid_hour_serving_is_returned_as_exchanged(): void
    {
        $serving = $this->createServing($this->paidType, $this->hourUnit);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/servings/{$serving->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.serving_type_name', 'exchanged');
    }

    public function test_paid_serving_priced_in_syp_is_returned_as_paid(): void
    {
        $serving = $this->createServing($this->paidType, $this->sypUnit);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/servings/{$serving->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.serving_type_name', 'paid');
    }

    public function test_voluntary_serving_is_returned_as_voluntary(): void
    {
        $serving = $this->createServing($this->voluntaryType, $this->hourUnit);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/servings/{$serving->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.serving_type_name', 'voluntary');
    }

    public function test_nested_serving_in_request_responses_exposes_display_type(): void
    {
        $serving = $this->createServing($this->paidType, $this->hourUnit);

        $requester = User::factory()->create(['role' => 'user']);
        WalletModel::factory()->create([
            'user_id' => $requester->id,
            'unit_id' => $this->hourUnit->id,
            'balance' => 100,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson('/api/servings/requests', [
                'serving_id' => $serving->id,
                'automatically_cancel_after' => 14,
            ])
            ->assertStatus(201);

        $this->actingAs($requester, 'sanctum')
            ->getJson('/api/servings/requests/my')
            ->assertStatus(200)
            ->assertJsonPath('data.0.serving.display_type', 'exchanged');

        $this->actingAs($requester, 'sanctum')
            ->getJson("/api/servings/requests/serving/{$serving->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.0.serving.display_type', 'exchanged');
    }
}
