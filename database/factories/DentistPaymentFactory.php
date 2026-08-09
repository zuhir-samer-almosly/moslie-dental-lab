<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DentistPayment>
 */
class DentistPaymentFactory extends Factory
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
            'amount' => fake()->numberBetween(50, 1000) * 1000,
            'payment_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
        ];
    }
}
