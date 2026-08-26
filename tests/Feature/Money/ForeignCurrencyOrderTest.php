<?php

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\Order;
use App\Models\User;

test('a dollar-quoted item is booked as the lira it converted to', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    // خزف quoted at $17; on this day the rate was 13.
    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'خزف',
            'quantity' => 1,
            'date' => '2026-08-05',
            'currency' => 'USD',
            'original_amount' => 17_00,
            'rate' => '13',
            'selected_teeth' => [],
        ]],
    ])->assertRedirect(route('orders.index'));

    $order = Order::with('items')->sole();

    expect($order->items->first()->price)->toBe(221)
        ->and($order->amount)->toBe(221);
});

test('one order can hold a dollar item and a lira item', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [
            [
                'type' => 'خزف', 'quantity' => 1, 'date' => '2026-08-05',
                'currency' => 'USD', 'original_amount' => 17_00, 'rate' => '13',
                'selected_teeth' => [],
            ],
            [
                'type' => 'زيركون', 'quantity' => 1, 'date' => '2026-08-05',
                'price' => 250, 'selected_teeth' => [],
            ],
        ],
    ])->assertRedirect(route('orders.index'));

    $order = Order::with('items')->sole();

    expect($order->amount)->toBe(471)
        ->and($order->items->firstWhere('type', 'خزف')->price)->toBe(221)
        ->and($order->items->firstWhere('type', 'زيركون')->price)->toBe(250);
});

test('the ledger posts a dollar-quoted order at its lira value', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'خزف', 'quantity' => 2, 'date' => '2026-08-05',
            'currency' => 'USD', 'original_amount' => 17_00, 'rate' => '13',
            'selected_teeth' => [],
        ]],
    ]);

    // 2 x ($17 x 13) = 442
    expect(app(LedgerReports::class)->balance(AccountCode::REVENUE->value))->toBe(442);
});

test('a dollar-quoted item keeps what it was quoted at', function () {
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

    expect(Order::with('items')->sole()->items->first())
        ->currency->toBe('USD')
        ->original_amount->toBe(1700)
        ->rate->toBe('13.000000');
});
