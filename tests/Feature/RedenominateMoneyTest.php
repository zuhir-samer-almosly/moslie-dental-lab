<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * The currency redenomination: every stored money figure is divided by 100.
 *
 * The columns are all `integer`, so the command's real job is refusing to
 * lose money quietly — a value that does not divide evenly must stop the
 * whole run, not get rounded behind the user's back.
 */
it('divides every money column by 100', function () {
    $dentist = Dentist::factory()->create([
        'price_list' => ['زيركون' => 80_000, 'خزف' => 125_000],
    ]);

    $order = Order::factory()->for($dentist)->create(['amount' => 3_200_000]);
    OrderItem::factory()->for($order)->create(['quantity' => 2, 'price' => 500_000]);
    OrderItem::factory()->for($order)->create(['quantity' => 1, 'price' => 2_200_000]);

    $payment = DentistPayment::factory()->for($dentist)->create(['amount' => 500_000]);
    $salary = EmployeePayment::factory()->for(Employee::factory())->create(['amount' => 150_000]);
    $material = MaterialPurchase::factory()->create(['amount' => 45_000]);
    $expense = Expense::factory()->create(['amount' => 12_300]);

    $this->artisan('money:redenominate --force')->assertSuccessful();

    expect($order->fresh()->amount)->toBe(32_000)
        ->and($payment->fresh()->amount)->toBe(5_000)
        ->and($salary->fresh()->amount)->toBe(1_500)
        ->and($material->fresh()->amount)->toBe(450)
        ->and($expense->fresh()->amount)->toBe(123)
        ->and($dentist->fresh()->price_list)->toBe(['زيركون' => 800, 'خزف' => 1_250]);
});

it('keeps an order amount equal to the sum of its divided items', function () {
    $order = Order::factory()->create(['amount' => 700_000]);
    OrderItem::factory()->for($order)->create(['quantity' => 3, 'price' => 100_000]);
    OrderItem::factory()->for($order)->create(['quantity' => 2, 'price' => 200_000]);

    $this->artisan('money:redenominate --force')->assertSuccessful();

    $order->refresh();
    $sumOfItems = $order->items()->get()->sum(fn (OrderItem $i) => $i->quantity * $i->price);

    expect($order->amount)->toBe(7_000)->and($order->amount)->toBe($sumOfItems);
});

it('aborts without changing anything when a value does not divide evenly', function () {
    $expense = Expense::factory()->create(['amount' => 4_375]);
    $payment = DentistPayment::factory()->create(['amount' => 500_000]);

    $this->artisan('money:redenominate --force')
        ->expectsOutputToContain('4,375')
        ->assertFailed();

    expect($expense->fresh()->amount)->toBe(4_375)
        ->and($payment->fresh()->amount)->toBe(500_000);
});

it('reports the changes without writing them on a dry run', function () {
    $payment = DentistPayment::factory()->create(['amount' => 500_000]);

    $this->artisan('money:redenominate --dry-run --force')->assertSuccessful();

    expect($payment->fresh()->amount)->toBe(500_000);
});

it('rounds half-up only when explicitly asked', function () {
    $expense = Expense::factory()->create(['amount' => 4_375]);

    $this->artisan('money:redenominate --round --force')->assertSuccessful();

    expect($expense->fresh()->amount)->toBe(44);
});

it('divides an order with no items by its own amount', function () {
    $order = Order::factory()->create(['amount' => 90_000]);

    $this->artisan('money:redenominate --force')->assertSuccessful();

    expect($order->fresh()->amount)->toBe(900);
});

it('leaves the ledger balanced after a rebuild', function () {
    $dentist = Dentist::factory()->create();
    $order = Order::factory()->for($dentist)->create(['amount' => 3_200_000, 'status' => 'completed']);
    OrderItem::factory()->for($order)->create(['quantity' => 1, 'price' => 3_200_000]);
    DentistPayment::factory()->for($dentist)->create(['amount' => 500_000]);

    $this->artisan('money:redenominate --force')->assertSuccessful();
    $this->artisan('ledger:rebuild --force')->assertSuccessful();

    $lines = App\Models\JournalLine::query();

    expect((int) $lines->clone()->sum('debit'))->toBe((int) $lines->clone()->sum('credit'))
        ->and((int) $lines->clone()->sum('debit'))->toBe(37_000);
});
