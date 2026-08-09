<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => \App\Models\Order::factory(),
            'type' => fake()->randomElement(['زيركون', 'خزف', 'طقم كامل', 'جسر']),
            'quantity' => fake()->numberBetween(1, 6),
            'price' => fake()->numberBetween(50, 500) * 1000,
            'notes' => null,
            'meta' => ['selected_teeth' => [], 'patient_name' => '', 'date' => null],
        ];
    }
}
