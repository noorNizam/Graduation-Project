<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Models\Serving>
 */
class ServingFactory extends Factory
{
    protected $model = \App\Infrastructure\Models\Serving::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'cost_amount' => fake()->randomFloat(2, 10, 100),
            'meeting_type' => 'online',
            'status' => 'active',
            'category_id' => \App\Models\ServingCategory::factory(),
        ];
    }
}
