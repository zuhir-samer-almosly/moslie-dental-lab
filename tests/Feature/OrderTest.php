<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;

/**
 * Create an order directly (bypassing the controller) with an explicit
 * created_at so the index ordering (latest first) is deterministic in tests.
 */
function seedOrder(int $dentistId, int $amount, string $dueDate, string $createdAt, string $status = 'pending'): Order
{
    $order = Order::create([
        'dentist_id' => $dentistId,
        'due_date' => $dueDate,
        'amount' => $amount,
        'status' => $status,
    ]);
    $order->forceFill(['created_at' => $createdAt])->save();

    return $order;
}

test('storing an order derives amount and due_date from its items and folds item meta', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'notes' => 'ملاحظة',
        'items' => [
            [
                'type' => 'تلبيسة',
                'quantity' => 2,
                'price' => 1000,
                'date' => '2026-06-10',
                'patient_name' => 'محمد',
                'selected_teeth' => [11, 12],
                'notes' => 'عاجل',
            ],
            [
                'type' => 'جسر',
                'quantity' => 1,
                'price' => 500,
                'date' => '2026-06-05',
                'patient_name' => '',
                'selected_teeth' => [],
                'notes' => '',
            ],
        ],
    ])->assertRedirect(route('orders.index'));

    $order = Order::with('items')->sole();

    // amount = 2*1000 + 1*500; due_date = earliest item date.
    expect($order->amount)->toBe(2500);
    expect($order->due_date->toDateString())->toBe('2026-06-05');
    expect($order->items)->toHaveCount(2);

    $crown = $order->items->firstWhere('type', 'تلبيسة');
    expect($crown->meta['patient_name'])->toBe('محمد');
    expect($crown->meta['date'])->toBe('2026-06-10');
    expect($crown->meta['selected_teeth'])->toBe([11, 12]);
    // patient_name/date/selected_teeth live only in meta, not as columns.
    expect($crown->getAttributes())->not->toHaveKey('patient_name');
});

test('updating an order replaces its items and recomputes amount and due_date', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. علي']);

    $order = seedOrder($dentist->id, 9999, '2026-01-01', '2026-01-01');
    $order->items()->create(['type' => 'قديم', 'quantity' => 1, 'price' => 9999, 'meta' => []]);
    $oldItemId = $order->items()->first()->id;

    $this->put(route('orders.update', $order), [
        'dentist_id' => $dentist->id,
        'status' => 'completed',
        'items' => [
            ['type' => 'جديد', 'quantity' => 3, 'price' => 200, 'date' => '2026-07-15', 'selected_teeth' => []],
        ],
    ])->assertRedirect(route('orders.index'));

    $order->refresh()->load('items');

    expect($order->status)->toBe('completed');
    expect($order->amount)->toBe(600);
    expect($order->due_date->toDateString())->toBe('2026-07-15');
    expect($order->items)->toHaveCount(1);
    // Old item was deleted and a fresh one created, not mutated in place.
    expect(Order::find($order->id)->items->pluck('id'))->not->toContain($oldItemId);
});

test('the orders index carries each order a previous balance, excluding cancelled and same-day orders', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. خالد']);

    // Prior billable order that should carry forward.
    seedOrder($dentist->id, 100000, '2026-06-01', '2026-06-01 09:00:00');
    // Cancelled prior order — must NOT carry forward.
    seedOrder($dentist->id, 99000, '2026-06-05', '2026-06-05 09:00:00', 'cancelled');
    // Same-day order as the "current" one — strict `<` means it must NOT count.
    seedOrder($dentist->id, 7000, '2026-06-12', '2026-06-12 08:00:00');
    // A payment before the cutoff.
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 30000, 'payment_date' => '2026-06-10']);

    // The "current" order, created last so it is index 0 under latest().
    seedOrder($dentist->id, 50000, '2026-06-12', '2026-06-12 12:00:00');

    // previous_balance = 100000 (prior billable) − 30000 (prior payment) = 70000.
    $this->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('orders/index')
                ->has('orders', 4)
                ->where('orders.0.previous_balance', 70000)
        );
});

test('the earliest order has no previous balance', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. ندى']);

    seedOrder($dentist->id, 40000, '2026-03-01', '2026-03-01');

    $this->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('orders', 1)
                ->where('orders.0.previous_balance', 0)
        );
});

test('deleting an order removes it', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. حذف']);
    $order = seedOrder($dentist->id, 1000, '2026-06-01', '2026-06-01');

    $this->delete(route('orders.destroy', $order))->assertRedirect(route('orders.index'));

    expect(Order::find($order->id))->toBeNull();
});

test('guests cannot access the orders page', function () {
    $this->get(route('orders.index'))->assertRedirect(route('login'));
});
