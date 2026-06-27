<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeePayment>
 */
class EmployeePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'amount' => fake()->numberBetween(50, 1000) * 1000,
            'payment_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
