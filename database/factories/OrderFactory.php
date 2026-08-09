<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dentist_id' => \App\Models\Dentist::factory(),
            'due_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            'amount' => fake()->numberBetween(50, 3000) * 1000,
            'status' => 'pending',
            'notes' => null,
            'meta' => null,
        ];
    }
}
