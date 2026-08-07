<?php

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MaterialPurchase;

function accountBalance(string $code): int
{
    return (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', $code)
        ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
        ->value('balance');
}

test('a salary payment debits salaries and credits cash', function () {
    $employee = Employee::factory()->create();

    EmployeePayment::create([
        'employee_id' => $employee->id,
        'amount' => 80000,
        'payment_date' => '2026-06-05',
    ]);

    expect(accountBalance('5000'))->toBe(80000);
    expect(accountBalance('1000'))->toBe(-80000);
    expect(JournalEntry::sole()->entry_date->toDateString())->toBe('2026-06-05');
});

test('a material purchase debits materials and credits cash', function () {
    MaterialPurchase::create([
        'name' => 'خزف',
        'amount' => 25000,
        'purchase_date' => '2026-06-08',
    ]);

    expect(accountBalance('5100'))->toBe(25000);
    expect(accountBalance('1000'))->toBe(-25000);
});

test('a general expense debits the account matching its category', function () {
    Expense::create([
        'category' => 'rent',
        'amount' => 40000,
        'expense_date' => '2026-06-01',
    ]);

    expect(accountBalance('5220'))->toBe(40000);   // إيجار
    expect(accountBalance('1000'))->toBe(-40000);
});

test('an unrecognised expense category falls back to other', function () {
    $expense = Expense::create([
        'category' => 'rent',
        'amount' => 40000,
        'expense_date' => '2026-06-01',
    ]);

    // Bypass validation the way a bad import would.
    $expense->forceFill(['category' => 'nonsense'])->save();

    expect(accountBalance('5290'))->toBe(40000);   // أخرى
    expect(accountBalance('5220'))->toBe(0);
});

test('every posted entry balances', function () {
    Expense::create(['category' => 'taxes', 'amount' => 1234, 'expense_date' => '2026-06-01']);
    MaterialPurchase::create(['name' => 'جبس', 'amount' => 5678, 'purchase_date' => '2026-06-02']);

    $totals = JournalLine::selectRaw('SUM(debit) as d, SUM(credit) as c')->first();

    expect((int) $totals->d)->toBe((int) $totals->c);
});

test('a salary payment with null date does not post', function () {
    $employee = Employee::factory()->create();

    EmployeePayment::create([
        'employee_id' => $employee->id,
        'amount' => 80000,
        'payment_date' => null,  // Invisible to existing reports
    ]);

    expect(JournalEntry::count())->toBe(0);
    expect(accountBalance('5000'))->toBe(0);
});

test('a material purchase with null date does not post', function () {
    MaterialPurchase::create([
        'name' => 'خزف',
        'amount' => 25000,
        'purchase_date' => null,  // Invisible to existing reports
    ]);

    expect(JournalEntry::count())->toBe(0);
    expect(accountBalance('5100'))->toBe(0);
});

test('a general expense with null date does not post', function () {
    Expense::create([
        'category' => 'rent',
        'amount' => 40000,
        'expense_date' => null,  // Invisible to existing reports
    ]);

    expect(JournalEntry::count())->toBe(0);
    expect(accountBalance('5220'))->toBe(0);
});

test('an expense whose category maps to a deactivated account still posts to that account', function () {
    // Find or create the rent account (5220)
    $rentAccount = Account::chart()->get('5220');
    if ($rentAccount) {
        $rentAccount->update(['is_active' => false]);
    }

    Expense::create([
        'category' => 'rent',
        'amount' => 40000,
        'expense_date' => '2026-06-01',
    ]);

    // Should debit 5220 (even though inactive), not 5290
    expect(accountBalance('5220'))->toBe(40000);
    expect(accountBalance('5290'))->toBe(0);
});
