<?php

use App\Ledger\LedgerReports;
use App\Mcp\Servers\DentalLabServer;
use App\Mcp\Tools\DeleteRecord;
use App\Mcp\Tools\ListDentists;
use App\Mcp\Tools\ListRecords;
use App\Mcp\Tools\ReferenceData;
use App\Mcp\Tools\SaveDentist;
use App\Mcp\Tools\SaveDentistPayment;
use App\Mcp\Tools\SaveEmployee;
use App\Mcp\Tools\SaveEmployeePayment;
use App\Mcp\Tools\SaveExpense;
use App\Mcp\Tools\SaveMaterialPurchase;
use App\Mcp\Tools\SaveOrder;
use App\Mcp\Tools\SetExchangeRate;
use App\Models\Account;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\Order;
use App\Money\Rate;

function owedBy(Dentist $dentist): int
{
    return (int) (app(LedgerReports::class)
        ->receivablesByDentist(null, $dentist->billingCurrency())[$dentist->id] ?? 0);
}

test('an order is saved through the order form and posts to the ledger', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male']);

    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'notes' => 'مستعجل',
        'items' => [
            ['type' => 'زيركون', 'price' => 1000, 'date' => '2026-06-10', 'patient_name' => 'محمد', 'selected_teeth' => [11, 12]],
            ['type' => 'تلبيسة', 'price' => 500, 'quantity' => 3, 'date' => '2026-06-12'],
        ],
    ])->assertOk()->assertSee(['"total":3500', 'محمد']);

    $order = Order::with('items')->sole();

    expect($order->amount)->toBe(3500)
        ->and($order->status)->toBe('pending')
        ->and($order->due_date->toDateString())->toBe('2026-06-10')
        // Teeth set the quantity, as they do in the form.
        ->and($order->items[0]->quantity)->toBe(2)
        ->and($order->items[0]->meta['selected_teeth'])->toBe([11, 12])
        ->and(owedBy($dentist))->toBe(3500);
});

test('a line with no price is priced from the dentist\'s list, under its existing spelling', function () {
    $dentist = Dentist::create([
        'name' => 'د. ريم',
        'gender' => 'female',
        'price_list' => ['إعادة' => ['price' => 700, 'currency' => 'SYP']],
    ]);

    // Typed without the hamza: still the same product, still its price.
    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'items' => [['type' => 'اعادة', 'quantity' => 2, 'date' => '2026-06-10']],
    ])->assertOk();

    $item = Order::sole()->items()->sole();

    expect($item->type)->toBe('إعادة')->and($item->price)->toBe(700)->and(owedBy($dentist))->toBe(1400);
});

test('a line with no price and no listed price is refused, and nothing is saved', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male']);

    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'items' => [['type' => 'زيركون', 'date' => '2026-06-10']],
    ])->assertHasErrors(['price list']);

    expect(Order::count())->toBe(0);
});

test('a dollar quote to a lira dentist converts at the rate of its day', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male']);
    Rate::set('2026-06-01', '10000');

    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'items' => [['type' => 'زيركون', 'usd_price' => 12.5, 'quantity' => 2, 'date' => '2026-06-10']],
    ])->assertOk();

    $item = Order::sole()->items()->sole();

    expect($item->original_amount)->toBe(1250)
        ->and($item->price)->toBe(125000)
        ->and(owedBy($dentist))->toBe(250000);
});

test('a dollar dentist is billed and pays in dollars only', function () {
    $dentist = Dentist::create(['name' => 'د. جون', 'gender' => 'male', 'currency' => 'USD']);

    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'items' => [['type' => 'زيركون', 'price' => 1000, 'date' => '2026-06-10']],
    ])->assertHasErrors(['billed in dollars']);

    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'items' => [['type' => 'زيركون', 'usd_price' => 40, 'quantity' => 2, 'date' => '2026-06-10']],
    ])->assertOk()->assertSee('"total":80');

    DentalLabServer::tool(SaveDentistPayment::class, [
        'dentist_id' => $dentist->id, 'amount' => 5000, 'payment_date' => '2026-06-11',
    ])->assertHasErrors(['billed in dollars']);

    DentalLabServer::tool(SaveDentistPayment::class, [
        'dentist_id' => $dentist->id, 'usd_amount' => 30, 'payment_date' => '2026-06-11',
    ])->assertOk();

    // Cents: 80.00 billed, 30.00 paid.
    expect(owedBy($dentist))->toBe(5000);
});

test('saving an order with an id replaces its items', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male']);
    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'items' => [['type' => 'زيركون', 'price' => 1000, 'date' => '2026-06-10']],
    ])->assertOk();

    DentalLabServer::tool(SaveOrder::class, [
        'id' => Order::sole()->id,
        'status' => 'completed',
        'items' => [['type' => 'تلبيسة', 'price' => 400, 'quantity' => 2, 'date' => '2026-06-10']],
    ])->assertOk();

    $order = Order::with('items')->sole();

    expect($order->status)->toBe('completed')
        ->and($order->items)->toHaveCount(1)
        ->and(owedBy($dentist))->toBe(800);
});

test('a payment is recorded, corrected and deleted, and the ledger follows each time', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male']);

    DentalLabServer::tool(SaveDentistPayment::class, [
        'dentist_id' => $dentist->id, 'amount' => 5000, 'payment_date' => '2026-06-11',
    ])->assertOk();
    $payment = DentistPayment::sole();
    expect(owedBy($dentist))->toBe(-5000);

    DentalLabServer::tool(SaveDentistPayment::class, [
        'id' => $payment->id, 'dentist_id' => $dentist->id, 'amount' => 3000, 'payment_date' => '2026-06-11',
    ])->assertOk();
    expect(owedBy($dentist))->toBe(-3000);

    DentalLabServer::tool(DeleteRecord::class, ['type' => 'dentist_payment', 'id' => $payment->id])->assertOk();
    expect(DentistPayment::count())->toBe(0)->and(owedBy($dentist))->toBe(0);
});

test('a dollar payment with no rate uses the rate in effect, and fails without one', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male']);

    DentalLabServer::tool(SaveDentistPayment::class, [
        'dentist_id' => $dentist->id, 'usd_amount' => 10, 'payment_date' => '2026-06-11',
    ])->assertHasErrors(['No exchange rate']);

    Rate::set('2026-06-01', '10000');

    DentalLabServer::tool(SaveDentistPayment::class, [
        'dentist_id' => $dentist->id, 'usd_amount' => 10, 'payment_date' => '2026-06-11',
    ])->assertOk();

    expect(DentistPayment::sole()->amount)->toBe(100000);
});

test('expenses, material purchases and salaries are recorded', function () {
    $category = Account::expenseCategories()->first()->category_key;
    $before = app(LedgerReports::class)->expensesTotal('2026-06-01', '2026-06-30');

    DentalLabServer::tool(SaveExpense::class, [
        'category' => $category, 'description' => 'فاتورة', 'amount' => 2000, 'expense_date' => '2026-06-05',
    ])->assertOk();

    DentalLabServer::tool(SaveMaterialPurchase::class, [
        'name' => 'زيركون خام', 'quantity' => '3 علب', 'amount' => 9000, 'purchase_date' => '2026-06-06',
    ])->assertOk();

    DentalLabServer::tool(SaveEmployee::class, ['name' => 'أحمد', 'role' => 'فني'])->assertOk();
    $employee = Employee::sole();

    DentalLabServer::tool(SaveEmployeePayment::class, [
        'employee_id' => $employee->id, 'amount' => 4000, 'payment_date' => '2026-06-07',
    ])->assertOk();

    expect(Expense::sole()->amount)->toBe(2000)
        ->and(MaterialPurchase::sole()->amount)->toBe(9000)
        ->and(EmployeePayment::sole()->amount)->toBe(4000)
        ->and(app(LedgerReports::class)->expensesTotal('2026-06-01', '2026-06-30') - $before)->toBe(15000);

    DentalLabServer::tool(SaveExpense::class, [
        'category' => 'no-such-category', 'amount' => 1, 'expense_date' => '2026-06-05',
    ])->assertHasErrors();

    // Retiring an employee is an edit; fields left out keep their value.
    DentalLabServer::tool(SaveEmployee::class, ['id' => $employee->id, 'is_active' => false])->assertOk();
    expect($employee->refresh()->is_active)->toBeFalse()->and($employee->role)->toBe('فني');
});

test('a dentist is added with a price list in dollars and lira, then edited', function () {
    DentalLabServer::tool(SaveDentist::class, [
        'name' => 'د. هدى',
        'gender' => 'female',
        'phone' => '0999',
        'price_list' => [
            ['type' => 'زيركون', 'price' => 1000],
            ['type' => 'فينير', 'currency' => 'USD', 'price' => 12.5],
        ],
    ])->assertOk();

    $dentist = Dentist::sole();

    expect($dentist->price_list)->toBe([
        'زيركون' => ['price' => 1000, 'currency' => 'SYP'],
        'فينير' => ['price' => 1250, 'currency' => 'USD'],
    ]);

    // Her own phone must not trip the uniqueness rule on edit.
    DentalLabServer::tool(SaveDentist::class, ['id' => $dentist->id, 'address' => 'دمشق'])->assertOk();

    expect($dentist->refresh()->address)->toBe('دمشق')
        ->and($dentist->name)->toBe('د. هدى')
        ->and($dentist->price_list)->toHaveCount(2);

    DentalLabServer::tool(SaveDentist::class, ['name' => 'د. آخر', 'gender' => 'male', 'phone' => '0999'])
        ->assertHasErrors();
});

test('a dentist with history cannot be deleted, and the refusal does not stick to later calls', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male']);
    $spare = Dentist::create(['name' => 'د. فارغ', 'gender' => 'male']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 100, 'status' => 'pending']);

    DentalLabServer::tool(DeleteRecord::class, ['type' => 'dentist', 'id' => $dentist->id])->assertHasErrors();
    DentalLabServer::tool(DeleteRecord::class, ['type' => 'dentist', 'id' => $spare->id])->assertOk();

    expect(Dentist::pluck('id')->all())->toBe([$dentist->id]);
});

test('the lookups return what entry needs', function () {
    $dentist = Dentist::create(['name' => 'د. سامر', 'gender' => 'male', 'phone' => '0933']);
    Dentist::create(['name' => 'د. ريم', 'gender' => 'female']);
    Employee::create(['name' => 'أحمد']);
    DentalLabServer::tool(SetExchangeRate::class, ['rate' => 11500])->assertOk();
    DentalLabServer::tool(SaveOrder::class, [
        'dentist_id' => $dentist->id,
        'items' => [['type' => 'زيركون', 'price' => 1000, 'date' => '2026-06-10']],
    ])->assertOk();

    DentalLabServer::tool(ListDentists::class, ['search' => 'سامر'])
        ->assertOk()->assertSee(['د. سامر', '"balance_owed":1000', 'زيركون'])->assertDontSee('د. ريم');

    DentalLabServer::tool(ReferenceData::class)
        ->assertOk()->assertSee(['أحمد', '11500', Account::expenseCategories()->first()->category_key]);

    DentalLabServer::tool(ListRecords::class, ['type' => 'orders', 'from' => '2026-06-01', 'to' => '2026-06-30'])
        ->assertOk()->assertSee(['د. سامر', 'زيركون']);

    DentalLabServer::tool(ListRecords::class, ['type' => 'orders', 'from' => '2026-07-01'])
        ->assertOk()->assertDontSee('زيركون');
});
