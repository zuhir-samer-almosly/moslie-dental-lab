<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;

function makeOrder(int $dentistId, int $amount, string $createdAt): void
{
    $order = Order::create([
        'dentist_id' => $dentistId,
        'due_date' => $createdAt,
        'amount' => $amount,
        'status' => 'pending',
    ]);
    $order->forceFill(['created_at' => $createdAt])->save();
}

function makePayment(int $dentistId, int $amount, string $createdAt): void
{
    $payment = DentistPayment::create([
        'dentist_id' => $dentistId,
        'amount' => $amount,
        'payment_date' => $createdAt,
    ]);
    $payment->forceFill(['created_at' => $createdAt])->save();
}

test('the invoice carries forward an unpaid balance from earlier months', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامر']);

    // Last month: ordered 100,000, only paid 40,000 → 60,000 still owed.
    makeOrder($dentist->id, 100000, '2026-05-10');
    makePayment($dentist->id, 40000, '2026-05-20');

    // This month: new order 50,000, paid 20,000.
    makeOrder($dentist->id, 50000, '2026-06-12');
    makePayment($dentist->id, 20000, '2026-06-25');

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('invoices/index')
                ->where('totals.opening', 60000)
                ->where('totals.orders', 50000)
                ->where('totals.payments', 20000)
                ->where('totals.balance', 90000)
                ->where("openingByDentist.{$dentist->id}", 60000)
        );
});

test('a dentist with a fully settled history has no opening balance', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. علي']);
    makeOrder($dentist->id, 30000, '2026-05-10');
    makePayment($dentist->id, 30000, '2026-05-15');

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('totals.opening', 0)
                ->where('openingByDentist', [])
        );
});

test('cancelled orders are excluded from invoice totals and opening balance', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. ملغى']);

    // Prior month: a real order and a cancelled one — only the real 30,000
    // should carry forward as opening balance.
    makeOrder($dentist->id, 30000, '2026-05-10');
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-11', 'amount' => 99000, 'status' => 'cancelled']);

    // This month: a real order and a cancelled one — totals.orders is 20,000.
    makeOrder($dentist->id, 20000, '2026-06-12');
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-13', 'amount' => 77000, 'status' => 'cancelled']);

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('totals.opening', 30000)
                ->where('totals.orders', 20000)
                ->where('totals.balance', 50000)
        );
});

test('guests cannot access invoices', function () {
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
});
