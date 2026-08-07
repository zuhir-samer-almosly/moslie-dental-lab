<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;

test('deleting a dentist removes the entries of their orders and payments', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 200000,
        'payment_date' => '2026-06-15',
    ]);

    expect(JournalEntry::count())->toBe(2);

    $dentist->delete();

    expect(JournalEntry::count())->toBe(0);
    expect(JournalLine::count())->toBe(0);
});

test('deleting an employee removes the entries of their salary payments', function () {
    $employee = Employee::factory()->create();
    EmployeePayment::create([
        'employee_id' => $employee->id,
        'amount' => 80000,
        'payment_date' => '2026-06-05',
    ]);

    expect(JournalEntry::count())->toBe(1);

    $employee->delete();

    expect(JournalEntry::count())->toBe(0);
});

test('deleting one dentist leaves another dentist entries alone', function () {
    $keep = Dentist::create(['name' => 'د. باقٍ']);
    $drop = Dentist::create(['name' => 'د. محذوف']);

    Order::create(['dentist_id' => $keep->id, 'due_date' => '2026-06-10', 'amount' => 100, 'status' => 'pending']);
    Order::create(['dentist_id' => $drop->id, 'due_date' => '2026-06-10', 'amount' => 200, 'status' => 'pending']);

    $drop->delete();

    expect(JournalEntry::count())->toBe(1);
});

test('ledger cleanup is atomic with the parent delete', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    expect(JournalEntry::count())->toBe(1);

    // Register a listener that aborts the delete, running after the observer.
    // If cleanup happened in deleting(), entries would be gone even though delete failed.
    // With cleanup in deleted(), entries remain because deleted() never fires.
    Dentist::deleting(fn () => false);

    $result = $dentist->delete();

    expect($result)->toBeFalse();
    expect(Dentist::find($dentist->id))->not->toBeNull();
    expect(JournalEntry::count())->toBe(1);
});
