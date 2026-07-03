<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\Order;
use App\Models\User;

test('the report gathers every stream for the chosen range and totals them', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامر']);

    // In range (June).
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 50000, 'status' => 'pending']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-12', 'amount' => 30000, 'status' => 'recieved']);
    // Cancelled — excluded from the orders value.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-13', 'amount' => 99000, 'status' => 'cancelled']);
    // Out of range (May) — excluded everywhere.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-30', 'amount' => 12345, 'status' => 'pending']);

    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 60000, 'payment_date' => '2026-06-15']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 99999, 'payment_date' => '2026-05-15']);

    $employee = Employee::factory()->create();
    EmployeePayment::factory()->create(['employee_id' => $employee->id, 'amount' => 20000, 'payment_date' => '2026-06-20']);

    MaterialPurchase::factory()->create(['amount' => 5000, 'purchase_date' => '2026-06-18']);
    Expense::factory()->create(['category' => 'transport', 'amount' => 2000, 'expense_date' => '2026-06-22']);

    $this->get(route('report.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('report/index')
                ->where('filters.from', '2026-06-01')
                ->where('filters.to', '2026-06-30')
                ->has('orders', 2)            // cancelled + May order excluded
                ->has('payments', 1)
                ->has('salaries', 1)
                ->has('materials', 1)
                ->has('expenses', 1)
                ->where('totals.income', 60000)
                ->where('totals.orders_value', 80000)
                ->where('totals.orders_count', 2)
                ->where('totals.expenses', 27000) // 20000 + 5000 + 2000
                ->where('totals.net', 33000)      // 60000 - 27000
        );
});

test('the report can be scoped to a single day', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. علي']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 10000, 'payment_date' => '2026-07-03']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 55555, 'payment_date' => '2026-07-04']);

    $this->get(route('report.index', ['from' => '2026-07-03', 'to' => '2026-07-03']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('payments', 1)
                ->where('totals.income', 10000)
        );
});

test('the report defaults to the current month when no range is given', function () {
    $this->travelTo('2026-07-15 10:00:00');
    $this->actingAs(User::factory()->create());

    $this->get(route('report.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('report/index')
                ->where('filters.from', '2026-07-01')
                ->where('filters.to', '2026-07-31')
        );
});

test('guests cannot access the report', function () {
    $this->get(route('report.index'))->assertRedirect(route('login'));
});
