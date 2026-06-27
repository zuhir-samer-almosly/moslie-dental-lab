<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\User;

test('finance summary computes net = income - expenses for the month', function () {
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

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('finance/index')
                ->where('income', 60000)
                ->where('expenses', 30000)
                ->where('net', 30000)
                ->where('month', '2026-06')
                ->has('trend', 6)
        );
});

test('guests cannot access the finance page', function () {
    $this->get(route('finance.index'))->assertRedirect(route('login'));
});
