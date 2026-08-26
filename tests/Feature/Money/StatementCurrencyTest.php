<?php

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;

test('a statement line carries what a dollar payment was handed over as', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => 100_00,
        'rate' => '13',
    ]);

    $line = app(LedgerReports::class)->dentistStatement($dentist->id)['lines']->first();

    expect($line['credit'])->toBe(1300)
        ->and($line['currency'])->toBe('USD')
        ->and($line['original_amount'])->toBe(10000)
        ->and($line['rate'])->toBe('13.000000');
});

test('a statement line for a lira payment claims no conversion', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'amount' => 5000,
    ]);

    $line = app(LedgerReports::class)->dentistStatement($dentist->id)['lines']->first();

    expect($line['currency'])->toBe('SYP')
        ->and($line['original_amount'])->toBeNull()
        ->and($line['rate'])->toBeNull();
});

test('a statement line for an order claims no conversion', function () {
    // An order entry aggregates its items, which may be quoted in different
    // currencies, so no single pair of dollars-and-rate describes the line.
    $dentist = Dentist::create(['name' => 'د. أحمد']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-08-05',
        'amount' => 221,
        'status' => 'pending',
    ]);
    $order->items()->create([
        'type' => 'خزف', 'quantity' => 1,
        'currency' => 'USD', 'original_amount' => 17_00, 'rate' => '13',
        'meta' => ['date' => '2026-08-05'],
    ]);

    $line = app(LedgerReports::class)->dentistStatement($dentist->id)['lines']->first();

    expect($line['debit'])->toBe(221)
        ->and($line['currency'])->toBe('SYP');
});
