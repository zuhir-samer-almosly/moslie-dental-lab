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

test('an employee can be created', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('employees.store'), [
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

    $this->put(route('employees.update', $employee), [
        'name' => 'جديد',
        'is_active' => false,
    ])->assertRedirect(route('employees.index'));

    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'جديد', 'is_active' => false]);
});

test('an employee can be deleted', function () {
    $this->actingAs(User::factory()->create());
    $employee = Employee::factory()->create();

    $this->delete(route('employees.destroy', $employee))
        ->assertRedirect(route('employees.index'));

    $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
});
