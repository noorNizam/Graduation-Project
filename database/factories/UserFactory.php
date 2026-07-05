<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = \App\Infrastructure\Models\User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('P@ssw0rd'),
            'role' => 'user',
            'is_active' => true,
            'phone_number' => null,
        ];
    }
}
