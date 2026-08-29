<?php

// tests/Feature/Money/DollarDentistTest.php

use App\Ledger\AccountCode;
use App\Models\Account;
use App\Money\Currency;

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
