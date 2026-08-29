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
