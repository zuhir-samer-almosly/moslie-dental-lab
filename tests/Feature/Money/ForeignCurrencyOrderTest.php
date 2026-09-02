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

/**
 * The payloads below are copied verbatim from the browser's POST body.
 *
 * The earlier tests in this file hand-built their items and left out the keys
 * they did not care about, which let `nullable` short-circuit rules that the
 * real form trips. An order form that cannot save is not a subtle failure —
 * it was only invisible because the tests were politer than the client.
 */
test('the exact payload the form sends for a lira item saves', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => (string) $dentist->id,
        'status' => 'pending',
        'notes' => '',
        'items' => [[
            'type' => 'جسر',
            'patient_name' => '',
            'quantity' => 1,
            'price' => 250,
            'notes' => '',
            'date' => '2026-08-26',
            'selected_teeth' => [],
            // The form always sends these, zeroed, for a lira line.
            'currency' => 'SYP',
            'original_amount' => 0,
            'rate' => '',
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    expect(Order::with('items')->sole())
        ->amount->toBe(250)
        ->and(Order::with('items')->sole()->items->first())
        ->price->toBe(250)
        ->currency->toBe('SYP')
        ->original_amount->toBeNull()
        ->rate->toBeNull();
});

test('the exact payload the form sends for a dollar item saves', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => (string) $dentist->id,
        'status' => 'pending',
        'notes' => '',
        'items' => [[
            'type' => 'خزف',
            'patient_name' => '',
            'quantity' => 1,
            // A dollar line still carries the derived lira price.
            'price' => 2210,
            'notes' => '',
            'date' => '2026-08-26',
            'selected_teeth' => [],
            'currency' => 'USD',
            'original_amount' => 1700,
            'rate' => '130',
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    expect(Order::with('items')->sole()->items->first())
        ->price->toBe(2210)
        ->original_amount->toBe(1700);
});

test('a price of zero on a lira item is still allowed', function () {
    // A line can legitimately be free — the old rule was min:0 and stays so.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => (string) $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'إصلاح', 'quantity' => 1, 'price' => 0,
            'date' => '2026-08-26', 'selected_teeth' => [],
            'currency' => 'SYP', 'original_amount' => 0, 'rate' => '',
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    expect(Order::sole()->amount)->toBe(0);
});

test('a zero-dollar quoted item is accepted', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => (string) $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'هدية',
            'quantity' => 1,
            'date' => '2026-08-26',
            'selected_teeth' => [],
            'currency' => 'USD',
            'original_amount' => 0,
            'rate' => '13',
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    $item = Order::with('items')->sole()->items->first();

    expect($item)
        ->price->toBe(0)
        ->original_amount->toBe(0)
        ->currency->toBe('USD')
        ->and(Order::sole()->amount)->toBe(0);
});
