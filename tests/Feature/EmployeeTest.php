<?php

use App\Models\Employee;
use App\Models\User;

test('guests cannot access employees', function () {
    $this->get(route('employees.index'))->assertRedirect(route('login'));
});

test('authenticated users can list employees', function () {
    $this->actingAs(User::factory()->create());
    Employee::factory(3)->create();

    $this->get(route('employees.index'))->assertOk();
});

test('the salaries month filter is honored even on day 31 (no day-overflow into the next month)', function () {
    // Regression: parsing "Y-m" without `!` filled the day from "now" and
    // could overflow the requested month into the next one at month-end.
    $this->travelTo('2026-07-31 10:00:00');
    $this->actingAs(User::factory()->create());

    $employee = Employee::factory()->create();
    \App\Models\EmployeePayment::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 50000,
        'payment_date' => '2026-06-15',
    ]);
    \App\Models\EmployeePayment::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 999,
        'payment_date' => '2026-07-15',
    ]);

    $this->get(route('employees.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('employees/index')
                ->where('month', '2026-06')
                ->where('total', 50000)
                ->has('payments', 1)
        );
});

test('an employee can be created', function () {
    $this->actingAs(User::factory()->create());

    $this->from(route('employees.index'))->post(route('employees.store'), [
        'name' => 'أحمد',
        'role' => 'فني',
        'phone' => '0999',
        'is_active' => true,
    ])->assertRedirect(route('employees.index'));

    $this->assertDatabaseHas('employees', ['name' => 'أحمد', 'role' => 'فني']);
});

test('an employee name is required', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('employees.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('an employee can be updated', function () {
    $this->actingAs(User::factory()->create());
    $employee = Employee::factory()->create(['name' => 'قديم']);

    $this->from(route('employees.index'))->put(route('employees.update', $employee), [
        'name' => 'جديد',
        'is_active' => false,
    ])->assertRedirect(route('employees.index'));

    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'جديد', 'is_active' => false]);
});

test('an employee can be deleted', function () {
    $this->actingAs(User::factory()->create());
    $employee = Employee::factory()->create();

    $this->from(route('employees.index'))->delete(route('employees.destroy', $employee))
        ->assertRedirect(route('employees.index'));

    $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
});
