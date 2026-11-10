<?php

use App\Models\Dentist;
use App\Models\Order;
use App\Models\User;

function searchOrder(Dentist $dentist, array $attributes = []): Order
{
    return Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-15',
        'amount' => 1000,
        'status' => 'pending',
        ...$attributes,
    ]);
}

test('guests cannot search', function () {
    $this->get(route('search', ['q' => 'سامر']))->assertRedirect(route('login'));
});

test('a dentist is found by name or phone, with his orders', function () {
    $this->actingAs(User::factory()->create());
    $samer = Dentist::create(['name' => 'د. سامر', 'phone' => '0944123456']);
    $other = Dentist::create(['name' => 'د. ليلى']);
    $order = searchOrder($samer);
    searchOrder($other);

    $this->getJson(route('search', ['q' => 'سامر']))
        ->assertOk()
        ->assertJsonCount(1, 'dentists')
        ->assertJsonPath('dentists.0.id', $samer->id)
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.id', $order->id)
        ->assertJsonPath('orders.0.dentist', 'د. سامر')
        ->assertJsonPath('orders.0.due_date', '2026-06-15')
        ->assertJsonPath('orders.0.amount', 1000)
        ->assertJsonPath('orders.0.currency', 'SYP');

    $this->getJson(route('search', ['q' => '0944']))
        ->assertJsonCount(1, 'dentists')
        ->assertJsonPath('dentists.0.id', $samer->id);
});

test('an order is found by its number, listed first', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);
    $first = searchOrder($dentist);
    searchOrder($dentist, ['notes' => 'ملاحظة '.$first->id]);

    $this->getJson(route('search', ['q' => '#'.$first->id]))
        ->assertOk()
        ->assertJsonPath('orders.0.id', $first->id);
});

test('an order is found by its notes or item type', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);
    $noted = searchOrder($dentist, ['notes' => 'مستعجل جداً']);
    $crown = searchOrder($dentist);
    $crown->items()->create(['type' => 'زيركون', 'quantity' => 1, 'price' => 1000]);

    $this->getJson(route('search', ['q' => 'مستعجل']))
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.id', $noted->id);

    $this->getJson(route('search', ['q' => 'زيركون']))
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.id', $crown->id)
        ->assertJsonPath('orders.0.items', ['زيركون']);
});

test('a single letter searches nothing', function () {
    $this->actingAs(User::factory()->create());
    searchOrder(Dentist::create(['name' => 'د. سامر']));

    $this->getJson(route('search', ['q' => 'س']))
        ->assertOk()
        ->assertExactJson(['dentists' => [], 'orders' => []]);
});
