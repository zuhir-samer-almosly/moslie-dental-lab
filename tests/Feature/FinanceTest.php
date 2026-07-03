<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\User;

test('finance summary computes net = income - (salaries + materials + expenses) for the month', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامر']);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 60000,
        'payment_date' => '2026-06-15',
    ]);
    // Different month — must be excluded.
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 99999,
        'payment_date' => '2026-05-15',
    ]);

    $employee = Employee::factory()->create();
    EmployeePayment::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 30000,
        'payment_date' => '2026-06-20',
    ]);

    MaterialPurchase::factory()->create([
        'name' => 'خزف',
        'amount' => 5000,
        'purchase_date' => '2026-06-18',
    ]);
    // Different month — must be excluded.
    MaterialPurchase::factory()->create([
        'amount' => 7777,
        'purchase_date' => '2026-05-18',
    ]);

    Expense::factory()->create([
        'category' => 'transport',
        'amount' => 2000,
        'expense_date' => '2026-06-22',
    ]);
    // Different month — must be excluded.
    Expense::factory()->create([
        'amount' => 3333,
        'expense_date' => '2026-05-22',
    ]);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('finance/index')
                ->where('income', 60000)
                ->where('expenses', 37000)
                ->where('net', 23000)
                ->where('month', '2026-06')
                ->where('expensesByMaterial.0.name', 'خزف')
                ->where('expensesByMaterial.0.total', 5000)
                ->where('expensesByCategory.0.name', 'مواصلات وسفر')
                ->where('expensesByCategory.0.total', 2000)
                ->has('trend', 6)
        );
});

test('requested month is honored even on day 31 (no day-overflow into the next month)', function () {
    // Regression: Carbon::createFromFormat('Y-m', ...) fills the missing day
    // from "now", so parsing "2026-06" on July 31 overflowed to July 1 and
    // the report silently showed the wrong month's numbers.
    $this->travelTo('2026-07-31 10:00:00');
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامر']);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 40000,
        'payment_date' => '2026-06-10',
    ]);
    // July payment — must NOT leak into June's report.
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 11111,
        'payment_date' => '2026-07-10',
    ]);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('finance/index')
                ->where('month', '2026-06')
                ->where('income', 40000)
        );
});

test('guests cannot access the finance page', function () {
    $this->get(route('finance.index'))->assertRedirect(route('login'));
});
