<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\User;

test('the invoice carries what a dollar payment was handed over as', function () {
    // The dentist reads this page. Without the original amount and the rate,
    // two $100 payments a fortnight apart are just two unequal lira numbers
    // with no reason given, so these props must survive to the frontend.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => 17_00,
        'rate' => '130',
    ]);

    $this->get(route('invoices.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertInertia(fn ($page) => $page
            ->where('payments.0.amount', 2210)
            ->where('payments.0.currency', 'USD')
            ->where('payments.0.original_amount', 1700)
            ->where('payments.0.rate', '130.000000')
        );
});

test('the invoice still carries a lira payment with no conversion', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'amount' => 5000,
    ]);

    $this->get(route('invoices.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertInertia(fn ($page) => $page
            ->where('payments.0.amount', 5000)
            ->where('payments.0.currency', 'SYP')
            ->where('payments.0.original_amount', null)
            ->where('payments.0.rate', null)
        );
});

test('the invoice carries what a dollar-quoted item was quoted at', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'خزف', 'quantity' => 1, 'date' => '2026-08-05',
            'currency' => 'USD', 'original_amount' => 17_00, 'rate' => '13',
            'selected_teeth' => [],
        ]],
    ]);

    $this->get(route('invoices.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertInertia(fn ($page) => $page
            ->where('orders.0.items.0.price', 221)
            ->where('orders.0.items.0.currency', 'USD')
            ->where('orders.0.items.0.original_amount', 1700)
            ->where('orders.0.items.0.rate', '13.000000')
        );
});
