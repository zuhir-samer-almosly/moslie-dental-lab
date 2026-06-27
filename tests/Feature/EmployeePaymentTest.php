<?php

use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\User;

test('a salary payment can be recorded', function () {
    $this->actingAs(User::factory()->create());
    $employee = Employee::factory()->create();

    $this->post(route('employee-payments.store'), [
        'employee_id' => $employee->id,
        'amount' => 30000,
        'payment_date' => '2026-06-10',
    ])->assertRedirect(route('employee-payments.index'));

    $this->assertDatabaseHas('employee_payments', [
        'employee_id' => $employee->id,
        'amount' => 30000,
    ]);
});

test('the salary index filters by month', function () {
    $this->actingAs(User::factory()->create());
    $employee = Employee::factory()->create();

    EmployeePayment::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 10000,
        'payment_date' => '2026-06-05',
    ]);
    EmployeePayment::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 5000,
        'payment_date' => '2026-05-05',
    ]);

    $this->get(route('employee-payments.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('employee-payments/index')
                ->where('total', 10000)
                ->has('payments', 1)
        );
});

test('a salary payment requires an existing employee', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('employee-payments.store'), [
        'employee_id' => 999,
        'amount' => 100,
        'payment_date' => '2026-06-10',
    ])->assertSessionHasErrors('employee_id');
});
