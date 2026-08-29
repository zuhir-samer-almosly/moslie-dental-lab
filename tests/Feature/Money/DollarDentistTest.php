<?php

// tests/Feature/Money/DollarDentistTest.php

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Account;
use App\Models\ExchangeRate;
use App\Money\Currency;
use Illuminate\Support\Facades\DB;

test('the chart carries a dollar cash, receivable and revenue account', function () {
    expect(Account::chart()->get('1001'))->not->toBeNull()
        ->and(Account::chart()->get('1101'))->not->toBeNull()
        ->and(Account::chart()->get('4001'))->not->toBeNull()
        ->and(Account::currencyFor('1001'))->toBe(Currency::USD)
        ->and(Account::currencyFor('1101'))->toBe(Currency::USD)
        ->and(Account::currencyFor('4001'))->toBe(Currency::USD)
        // The lira chart is untouched.
        ->and(Account::currencyFor('1000'))->toBe(Currency::SYP)
        ->and(Account::currencyFor('1100'))->toBe(Currency::SYP);
});

test('account codes resolve from a currency', function () {
    expect(AccountCode::cashFor(Currency::SYP))->toBe('1000')
        ->and(AccountCode::receivableFor(Currency::SYP))->toBe('1100')
        ->and(AccountCode::revenueFor(Currency::SYP))->toBe('4000')
        ->and(AccountCode::cashFor(Currency::USD))->toBe('1001')
        ->and(AccountCode::receivableFor(Currency::USD))->toBe('1101')
        ->and(AccountCode::revenueFor(Currency::USD))->toBe('4001');
});

use App\Ledger\Ledger;
use App\Ledger\Line;
use App\Ledger\MixedCurrencyEntryException;

test('an entry may not mix a lira account with a dollar account', function () {
    // Balanced as bare integers, but 500 lira and 500 cents are not the same
    // money. The numbers add up; the entry is still nonsense.
    expect(fn () => app(Ledger::class)->post('2026-09-01', 'خلط', [
        Line::debit('1001', 500),
        Line::credit('4000', 500),
    ]))->toThrow(MixedCurrencyEntryException::class);
});

test('a single-currency dollar entry posts fine', function () {
    $entry = app(Ledger::class)->post('2026-09-01', 'دولار', [
        Line::debit('1001', 500),
        Line::credit('4001', 500),
    ]);

    expect($entry->lines()->count())->toBe(2);
});

use App\Models\Dentist;
use App\Models\User;

test('a dentist is lira unless created as a dollar dentist', function () {
    expect(Dentist::create(['name' => 'د. أحمد'])->isDollar())->toBeFalse()
        ->and(Dentist::create(['name' => 'د. سامي', 'currency' => 'USD'])->isDollar())->toBeTrue();
});

test('a dentist with no ledger history can still change currency', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->put(route('dentists.update', $dentist), [
        'name' => 'د. أحمد', 'gender' => 'male', 'currency' => 'USD',
    ])->assertSessionHasNoErrors();

    expect($dentist->fresh()->isDollar())->toBeTrue();
});

test('a dentist with ledger history cannot change currency', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'جسر', 'quantity' => 1, 'price' => 250,
            'date' => '2026-09-01', 'selected_teeth' => [],
        ]],
    ]);

    $this->put(route('dentists.update', $dentist), [
        'name' => 'د. أحمد', 'gender' => 'male', 'currency' => 'USD',
    ])->assertSessionHasErrors('currency');

    expect($dentist->fresh()->isDollar())->toBeFalse();
});

test('billingCurrency throws rather than guess when the column was not selected', function () {
    Dentist::create(['name' => 'د. أحمد']);
    $dentist = Dentist::select('id', 'name')->first();

    expect(fn () => $dentist->billingCurrency())
        ->toThrow(LogicException::class, 'was loaded without its `currency` column');
});

test('an explicit null currency on update is rejected, not passed through to the column', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'جسر', 'quantity' => 1, 'price' => 250,
            'date' => '2026-09-01', 'selected_teeth' => [],
        ]],
    ]);

    $this->put(route('dentists.update', $dentist), [
        'name' => 'د. أحمد', 'gender' => 'male', 'currency' => null,
    ])->assertSessionHasErrors('currency');

    expect($dentist->fresh()->isDollar())->toBeFalse();
});

use App\Models\DentistPayment;

test('a dollar dentist payment is stored as cents with no rate and no lira', function () {
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'currency' => 'USD',
        'original_amount' => 200_00,
        'payment_date' => '2026-09-01',
    ]);

    expect($payment->original_amount)->toBe(20000)
        ->and($payment->rate)->toBeNull()
        // He owes and pays no lira, so the lira column is truthfully zero —
        // which is what keeps every untouched lira SUM() correct.
        ->and((int) $payment->amount)->toBe(0)
        ->and($payment->valueInOwnCurrency())->toBe(20000);
});

test('a dollar dentist payment refuses to carry a rate', function () {
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    expect(fn () => DentistPayment::create([
        'dentist_id' => $dentist->id,
        'currency' => 'USD',
        'original_amount' => 200_00,
        'rate' => '13',
        'payment_date' => '2026-09-01',
    ]))->toThrow(InvalidArgumentException::class);
});

test('a lira dentist paying in dollars still converts, exactly as before', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'currency' => 'USD',
        'original_amount' => 100_00,
        'rate' => '13',
        'payment_date' => '2026-09-01',
    ]);

    expect((int) $payment->amount)->toBe(1300)
        ->and($payment->valueInOwnCurrency())->toBe(1300);
});

use App\Models\Order;

test('a factory-built order defaults to lira, so billingCurrency and valueInOwnCurrency never throw', function () {
    // Order::factory() never sets currency (see OrderFactory::definition()), so
    // this exercises the same path an unsaved/mass-assigned order takes: no
    // `currency` key passed at all. Without Order::$attributes defaulting it
    // to SYP, billingCurrency() throws LogicException here, and so does
    // valueInOwnCurrency(), which calls it — the SYP branch every existing
    // order takes, and the only branch nothing previously asserted.
    $order = Order::factory()->create();

    expect($order->currency)->toBe('SYP')
        ->and($order->billingCurrency())->toBe(Currency::SYP)
        ->and($order->valueInOwnCurrency())->toBe((int) $order->amount);
});

test('a dollar dentist order stores cents, no rate and zero lira', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 2, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 250_00,
            'selected_teeth' => [],
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    $order = Order::with('items')->sole();

    expect($order->amount)->toBe(0)
        ->and($order->original_amount)->toBe(50000)   // 2 x $250
        ->and($order->currency)->toBe('USD')
        ->and($order->valueInOwnCurrency())->toBe(50000)
        ->and($order->items->first()->price)->toBe(0)
        ->and($order->items->first()->original_amount)->toBe(25000)
        ->and($order->items->first()->rate)->toBeNull();
});

test('a dollar dentist order is refused if it carries a rate or a lira price', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 250_00, 'rate' => '13',
            'selected_teeth' => [],
        ]],
    ])->assertSessionHasErrors('items.0.rate');

    // The other half of this order's own name: a lira price is refused too.
    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 250_00, 'price' => 5000,
            'selected_teeth' => [],
        ]],
    ])->assertSessionHasErrors('items.0.price');

    expect(Order::count())->toBe(0);
});

/**
 * Task 4 added OrderItem::nativeCurrency() but only ever exercised it through
 * DentistPayment — these two close that gap on the order path: a dollar
 * dentist's item comes out native, a lira dentist's dollar-quoted item still
 * converts through the rate.
 */
test('a dollar dentist order item comes out native: currency USD, no rate, price zero', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 100_00,
            'selected_teeth' => [],
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    $item = Order::with('items')->sole()->items->first();

    expect($item->isNativeUsd())->toBeTrue()
        ->and($item->currency)->toBe('USD')
        ->and($item->rate)->toBeNull()
        ->and($item->price)->toBe(0)
        ->and($item->original_amount)->toBe(10000);
});

test('a lira dentist USD-quoted item is not native and still converts through the rate', function () {
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
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    $item = Order::with('items')->sole()->items->first();

    expect($item->isNativeUsd())->toBeFalse()
        ->and($item->currency)->toBe('USD')
        ->and($item->rate)->toBe('13.000000')
        ->and($item->price)->toBe(221); // 17 x 13
});

/**
 * update() recomputes both totals from the items exactly as store() does,
 * but nothing previously exercised it for a dollar dentist — the two
 * behaviours below are new in this task and neither had coverage.
 */
test('editing a dollar dentist order shrinks original_amount to match the new items', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 2, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 250_00,
            'selected_teeth' => [],
        ]],
    ])->assertSessionHasNoErrors();

    $order = Order::sole();
    expect($order->original_amount)->toBe(50000);

    $this->put(route('orders.update', $order), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-02',
            'currency' => 'USD', 'original_amount' => 100_00,
            'selected_teeth' => [],
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    $order->refresh();

    expect($order->original_amount)->toBe(10000)
        ->and($order->amount)->toBe(0)
        ->and($order->currency)->toBe('USD')
        ->and($order->valueInOwnCurrency())->toBe(10000);
});

test('moving an order from a dollar dentist to a lira dentist nulls out its original_amount', function () {
    $this->actingAs(User::factory()->create());
    $dollarDentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);
    $liraDentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dollarDentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 250_00,
            'selected_teeth' => [],
        ]],
    ])->assertSessionHasNoErrors();

    $order = Order::sole();
    expect($order->original_amount)->toBe(25000);

    $this->put(route('orders.update', $order), [
        'dentist_id' => $liraDentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'جسر', 'quantity' => 1, 'price' => 250,
            'date' => '2026-09-02', 'selected_teeth' => [],
        ]],
    ])->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();

    $order->refresh();

    expect($order->original_amount)->toBeNull()
        ->and($order->currency)->toBe('SYP')
        ->and($order->amount)->toBe(250)
        ->and($order->valueInOwnCurrency())->toBe(250);
});

test('a dollar order posts to the dollar accounts and nowhere else', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 2, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 250_00,
            'selected_teeth' => [],
        ]],
    ])->assertSessionHasNoErrors();

    $reports = app(LedgerReports::class);

    expect($reports->balance('1101'))->toBe(50000)   // $500 in cents
        ->and($reports->balance('4001'))->toBe(50000)
        // The lira accounts never heard of him.
        ->and($reports->balance('1100'))->toBe(0)
        ->and($reports->balance('4000'))->toBe(0);
});

test('five hundred ordered less two hundred paid is exactly three hundred owed', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 500_00,
            'selected_teeth' => [],
        ]],
    ])->assertSessionHasNoErrors();

    // A month later, at a rate that has moved a long way. It must not matter.
    ExchangeRate::create(['rate_date' => '2026-10-01', 'rate' => '250']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-10-01',
        'currency' => 'USD',
        'original_amount' => '200',
    ])->assertSessionHasNoErrors();

    $reports = app(LedgerReports::class);

    expect($reports->balance('1101'))->toBe(30000)   // exactly $300
        ->and($reports->balance('1001'))->toBe(20000) // $200 in the dollar box
        ->and($reports->balance('1000'))->toBe(0)     // and none in the lira box
        ->and($reports->balance('1100'))->toBe(0);
});

test('a factory-built order posts by its dentist currency even when the row disagrees', function () {
    // OrderFactory always sets currency => 'SYP' (Task 5's default), so a
    // directly-constructed row for a dollar dentist starts out disagreeing
    // with its own dentist. OrderPosting must resolve the account AND the
    // unit from the dentist — never from this stale column — or the entry
    // silently posts a dollar figure into the lira accounts.
    $dollarDentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    Order::factory()->for($dollarDentist)->create([
        'currency' => 'SYP',
        'amount' => 0,
        'original_amount' => 400_00,
    ]);

    $reports = app(LedgerReports::class);

    expect($reports->balance('1101'))->toBe(40000)
        ->and($reports->balance('4001'))->toBe(40000)
        ->and($reports->balance('1100'))->toBe(0)
        ->and($reports->balance('4000'))->toBe(0);
});

test('updating a dollar dentist payment keeps it in dollars and reposts the ledger', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-09-01',
        'currency' => 'USD',
        'original_amount' => 100_00,
    ]);

    $this->put(route('payments.update', $payment), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-09-01',
        'currency' => 'USD',
        'original_amount' => '150',
    ])->assertSessionHasNoErrors();

    expect($payment->fresh())
        ->original_amount->toBe(15000)
        ->amount->toBe(0)
        ->rate->toBeNull();

    // The Rate::remember guard must not fire for a native dollar edit either
    // — there is no rate to remember.
    expect(app(LedgerReports::class)->balance('1001'))->toBe(15000)
        ->and(ExchangeRate::count())->toBe(0);
});

test('editing a payment to move it from a lira dentist to a dollar dentist posts natively', function () {
    $this->actingAs(User::factory()->create());
    $liraDentist = Dentist::create(['name' => 'د. أحمد']);
    $dollarDentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $payment = DentistPayment::create([
        'dentist_id' => $liraDentist->id,
        'payment_date' => '2026-09-01',
        'amount' => 25000,
    ]);

    $this->put(route('payments.update', $payment), [
        'dentist_id' => $dollarDentist->id,
        'payment_date' => '2026-09-01',
        'currency' => 'USD',
        'original_amount' => '200',
    ])->assertSessionHasNoErrors();

    expect($payment->fresh())
        ->dentist_id->toBe($dollarDentist->id)
        ->currency->toBe('USD')
        ->original_amount->toBe(20000)
        ->amount->toBe(0)
        ->rate->toBeNull();

    $reports = app(LedgerReports::class);

    expect($reports->balance('1001'))->toBe(20000)
        // The lira accounts, which held this payment a moment ago, are clean.
        ->and($reports->balance('1000'))->toBe(0)
        ->and($reports->balance('1100'))->toBe(0);
});

test('a malformed dentist_id on a payment fails validation instead of crashing', function () {
    // dentist_id[]=1 makes input('dentist_id') an array; Dentist::find() on
    // an array returns a Collection, and an unguarded ?->isDollar() would
    // throw BadMethodCallException before validation gets a chance to
    // reject it with a 422/302 — the same bug class closed for Dentist's own
    // currency field in 16fba87.
    $this->actingAs(User::factory()->create());

    $this->post(route('payments.store'), [
        'dentist_id' => ['1'],
        'payment_date' => '2026-09-01',
        'amount' => 25000,
    ])->assertSessionHasErrors('dentist_id');

    expect(DentistPayment::count())->toBe(0);
});

test('receivables and statements can be read in dollars', function () {
    $this->actingAs(User::factory()->create());
    $dollar = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);
    $lira = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dollar->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 500_00, 'selected_teeth' => []]],
    ])->assertSessionHasNoErrors();

    $this->post(route('orders.store'), [
        'dentist_id' => $lira->id, 'status' => 'pending',
        'items' => [['type' => 'جسر', 'quantity' => 1, 'date' => '2026-09-01',
            'price' => 400, 'selected_teeth' => []]],
    ])->assertSessionHasNoErrors();

    $reports = app(LedgerReports::class);

    $inLira = $reports->receivablesByDentist(null, Currency::SYP);
    $inDollars = $reports->receivablesByDentist(null, Currency::USD);

    expect($inLira->get($lira->id))->toBe(400)
        ->and($inLira->has($dollar->id))->toBeFalse()
        ->and($inDollars->get($dollar->id))->toBe(50000)
        ->and($inDollars->has($lira->id))->toBeFalse();

    $statement = $reports->dentistStatement($dollar->id, '2026-09-01', '2026-09-30', Currency::USD);

    expect($statement['closing'])->toBe(50000)
        ->and($statement['lines'])->toHaveCount(1);
});

test('the trial balance balances within each currency', function () {
    $this->actingAs(User::factory()->create());
    $dollar = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dollar->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 500_00, 'selected_teeth' => []]],
    ])->assertSessionHasNoErrors();

    $rows = app(LedgerReports::class)->trialBalance()->groupBy('currency');

    foreach ($rows as $currency => $group) {
        expect($group->sum('debit'))->toBe($group->sum('credit'), "{$currency} does not balance");
    }

    expect($rows->keys()->all())->toContain('USD');
});

test('outstanding lists each dentist in his own currency and never sums them', function () {
    $this->actingAs(User::factory()->create());
    $dollar = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);
    $lira = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dollar->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 500_00, 'selected_teeth' => []]],
    ]);
    $this->post(route('orders.store'), [
        'dentist_id' => $lira->id, 'status' => 'pending',
        'items' => [['type' => 'جسر', 'quantity' => 1, 'date' => '2026-09-01',
            'price' => 400, 'selected_teeth' => []]],
    ]);

    // Sorted within currency, not together: SYP sorts before USD, so
    // dentists.0 is the lira dentist and dentists.1 is the dollar one —
    // 50000 > 400 is a coincidence of units, not a meaningful ranking.
    $this->get(route('outstanding.index'))
        ->assertInertia(fn ($page) => $page
            ->where('totalOutstanding', 400)      // lira only
            ->where('totalOutstandingUsd', 50000) // dollars only, never added
            ->where('dentists.1.currency', 'USD')
            ->where('dentists.1.outstanding', 50000)
            ->where('dentists.1.orders_total', 50000)
        );
});

test('the invoice for a dollar dentist is in dollars, with no lira and no rate', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-10',
            'currency' => 'USD', 'original_amount' => 500_00, 'selected_teeth' => []]],
    ]);
    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id, 'payment_date' => '2026-09-20',
        'currency' => 'USD', 'original_amount' => '200',
    ]);

    $this->get(route('invoices.index', [
        'from' => '2026-09-01', 'to' => '2026-09-30', 'dentist_id' => $dentist->id,
    ]))->assertInertia(fn ($page) => $page
        ->where('currency', 'USD')
        ->where('totals.orders', 50000)
        ->where('totals.payments', 20000)
        ->where('totals.balance', 30000)
        ->where('totals.opening', 0)
    );
});

test('the mixed no-dentist-selected invoice keeps totals and totalsUsd each single-currency, even when an order row disagrees with its dentist', function () {
    $this->actingAs(User::factory()->create());
    $lira = Dentist::create(['name' => 'د. أحمد']);
    $dollar = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $lira->id, 'status' => 'pending',
        'items' => [['type' => 'جسر', 'quantity' => 1, 'date' => '2026-09-05',
            'price' => 400, 'selected_teeth' => []]],
    ])->assertSessionHasNoErrors();

    // Directly constructed (not through the form), so the row's own
    // `currency` column disagrees with its dentist — exactly the shape
    // OrderPosting::dentistCurrency() refuses to trust, and the shape
    // InvoiceController::buildReport() must resolve through the dentist too,
    // not through Order::billingCurrency()/valueInOwnCurrency().
    Order::factory()->for($dollar)->create([
        'due_date' => '2026-09-10',
        'currency' => 'SYP',
        'amount' => 0,
        'original_amount' => 500_00,
    ]);

    $this->get(route('invoices.index', [
        'from' => '2026-09-01', 'to' => '2026-09-30',
    ]))->assertInertia(fn ($page) => $page
        ->where('currency', 'SYP')
        // The lira dentist's order only — the dollar dentist's stale-row
        // order must not land here, whatever its own `currency` column says.
        ->where('totals.orders', 400)
        // The dollar dentist's order, valued by his own currency's cents —
        // not silently dropped (old filter) and not blended into the lira
        // bucket (old sum).
        ->where('totalsUsd.orders', 50000)
    );
});

test('the statement page for a dollar dentist reads in dollars, without throwing on the narrowed select', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-10',
            'currency' => 'USD', 'original_amount' => 500_00, 'selected_teeth' => []]],
    ]);

    $this->get(route('ledger.statement', [
        'dentist_id' => $dentist->id, 'from' => '2026-09-01', 'to' => '2026-09-30',
    ]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('currency', 'USD')
        ->where('statement.opening', 0)
        ->where('statement.closing', 50000)
        ->where('statement.lines.0.currency', 'USD')
    );
});

test('rebuilding the ledger reproduces a dollar dentist exactly', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 500_00, 'selected_teeth' => []]],
    ]);
    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id, 'payment_date' => '2026-09-20',
        'currency' => 'USD', 'original_amount' => '200',
    ]);

    $before = app(LedgerReports::class)->balance('1101');

    $this->artisan('ledger:rebuild', ['--force' => true])->assertExitCode(0);

    expect(app(LedgerReports::class)->balance('1101'))->toBe($before)->toBe(30000);
});

test('redenominating never touches a native dollar row', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 1, 'date' => '2026-09-01',
            'currency' => 'USD', 'original_amount' => 500_00, 'selected_teeth' => []]],
    ]);

    $this->artisan('money:redenominate', ['--force' => true])->assertExitCode(0);

    // Cents are not lira. Dividing them by 100 would turn $500 into $5.
    expect(Order::sole()->original_amount)->toBe(50000)
        ->and(Order::sole()->items->first()->original_amount)->toBe(50000);
});

test('the exclusion is a real SQL guard, not just an accident of the lira column already being zero', function () {
    // The app forces a native dollar row's lira column to 0 on write (see
    // App\Concerns\HasForeignCurrency::applyExchangeRate()), so through the
    // normal write path `order_items.price`, `orders.amount` and
    // `dentist_payments.amount` are always 0 already and dividing 0 by
    // anything is a no-op — which is why the test above would keep passing
    // even with the exclusion deleted. money:redenominate works in raw SQL
    // with no model in the loop, so its guard must hold even for a row that
    // violates that invariant (bad legacy data, a bug upstream). These rows
    // are inserted directly, bypassing HasForeignCurrency's saving hook, to
    // prove the command's own WHERE clauses — not the model layer — are
    // what keep native dollar cents away from the divisor.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $orderId = DB::table('orders')->insertGetId([
        'dentist_id' => $dentist->id, 'due_date' => '2026-09-01', 'status' => 'pending',
        'amount' => 500, 'currency' => 'USD', 'original_amount' => 50000,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('order_items')->insert([
        'order_id' => $orderId, 'type' => 'زيركون', 'quantity' => 1,
        'price' => 500, 'currency' => 'USD', 'original_amount' => 50000, 'rate' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('dentist_payments')->insert([
        'dentist_id' => $dentist->id, 'amount' => 200,
        'currency' => 'USD', 'original_amount' => 20000, 'rate' => null,
        'payment_date' => '2026-09-20', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('money:redenominate', ['--force' => true])->assertExitCode(0);

    expect(DB::table('order_items')->where('order_id', $orderId)->value('price'))->toBe(500)
        ->and(DB::table('orders')->where('id', $orderId)->value('amount'))->toBe(500)
        ->and(DB::table('dentist_payments')->where('dentist_id', $dentist->id)->value('amount'))->toBe(200);
});

test('an item-less native dollar order survives redenomination even though its amount was never derived from items', function () {
    // Same reasoning as above, aimed squarely at divideOrders()'s item-less
    // branch and findIndivisible()'s itemlessOrders() scan — the one place
    // an order's own amount is divided directly rather than recomputed from
    // (already-protected) items.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    $orderId = DB::table('orders')->insertGetId([
        'dentist_id' => $dentist->id, 'due_date' => '2026-09-01', 'status' => 'pending',
        'amount' => 500, 'currency' => 'USD', 'original_amount' => 50000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('money:redenominate', ['--force' => true])->assertExitCode(0);

    expect(DB::table('orders')->where('id', $orderId)->value('amount'))->toBe(500);
});

test('a converted dollar row — currency USD with a rate set — still gets redenominated, not swallowed by the native exclusion', function () {
    // The exclusion predicate is `currency != 'USD' OR rate IS NOT NULL`,
    // which is designed to let a converted (state-2) row through: its `rate`
    // is set, so the second disjunct is true regardless of currency. Nothing
    // in this diff or in RedenominateMoneyTest previously ran that path —
    // De Morgan says it is correct, but no assertion backed that up. This
    // pins it down: if `OR rate IS NOT NULL` is ever dropped, this fails.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $orderId = DB::table('orders')->insertGetId([
        'dentist_id' => $dentist->id, 'due_date' => '2026-09-01', 'status' => 'pending',
        'amount' => 5000000, 'currency' => 'SYP', 'original_amount' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('order_items')->insert([
        'order_id' => $orderId, 'type' => 'تاج', 'quantity' => 1,
        // A USD-quoted item for a lira dentist: converted once, at write
        // time, to a real lira figure — exactly what state 2 looks like.
        'price' => 5000000, 'currency' => 'USD', 'original_amount' => 100000,
        'rate' => '5000.000000',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('money:redenominate', ['--force' => true])->assertExitCode(0);

    // Lira, so it must divide like any other lira row — 5,000,000 / 100.
    expect(DB::table('order_items')->where('order_id', $orderId)->value('price'))->toBe(50000);
});
