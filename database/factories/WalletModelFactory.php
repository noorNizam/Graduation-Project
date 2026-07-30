<?php

namespace Database\Factories;

use App\Infrastructure\Models\PaymentUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Models\WalletModel>
 */
class WalletModelFactory extends Factory
{
    protected $model = \App\Infrastructure\Models\WalletModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->word()."'s Wallet",
            'balance' => 0,
            'unit_id' => PaymentUnit::factory(),
        ];
    }
}
