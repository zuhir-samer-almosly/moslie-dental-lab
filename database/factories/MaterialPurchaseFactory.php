<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaterialPurchase>
 */
class MaterialPurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['خزف', 'جبس', 'زركون', 'شمع', 'أكريل']),
            'supplier' => fake()->company(),
            'quantity' => fake()->numberBetween(1, 20).' كغ',
            'amount' => fake()->numberBetween(10, 500) * 1000,
            'purchase_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
