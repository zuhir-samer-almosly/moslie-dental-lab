<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(
                Account::allExpenseCategories()->pluck('category_key')->all()
            ),
            'description' => fake()->optional()->sentence(3),
            'amount' => fake()->numberBetween(10, 500) * 1000,
            'expense_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
