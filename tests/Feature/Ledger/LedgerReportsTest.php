<?php

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\Order;

beforeEach(function () {
    $this->reports = app(LedgerReports::class);

    $this->dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-05-10', 'amount' => 100000, 'status' => 'pending']);
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-11', 'amount' => 900000, 'status' => 'cancelled']);
    DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 300000, 'payment_date' => '2026-06-15']);

    EmployeePayment::create(['employee_id' => Employee::factory()->create()->id, 'amount' => 80000, 'payment_date' => '2026-06-05']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);
});

test('balance is signed by account type', function () {
    // Cash: 300,000 in, 120,000 out.
    expect($this->reports->balance('1000'))->toBe(180000);
    // Receivables: 600,000 billable orders less 300,000 paid.
    expect($this->reports->balance('1100'))->toBe(300000);
    // Revenue is credit-positive.
    expect($this->reports->balance('4000'))->toBe(600000);
    // An account nothing ever posted to nets to zero, not a stray sign.
    expect($this->reports->balance('5100'))->toBe(0);
});

test('balance respects an as-of date', function () {
    expect($this->reports->balance('1100', '2026-05-31'))->toBe(100000);
    // Before either order existed, there is nothing to owe yet.
    expect($this->reports->balance('1100', '2026-04-30'))->toBe(0);
});

test('receivables are grouped by dentist', function () {
    expect($this->reports->receivablesByDentist()->all())
        ->toBe([$this->dentist->id => 300000]);
});

test('receivables by dentist keep two dentists apart', function () {
    $other = Dentist::create(['name' => 'د. ليلى']);
    Order::create(['dentist_id' => $other->id, 'due_date' => '2026-06-12', 'amount' => 70000, 'status' => 'pending']);

    $balances = $this->reports->receivablesByDentist();

    // Each dentist keeps their own balance; nothing bleeds into the other's total.
    expect($balances[$this->dentist->id])->toBe(300000);
    expect($balances[$other->id])->toBe(70000);
    expect($balances)->toHaveCount(2);
});

test('cash receipts count only money in from dentists', function () {
    expect($this->reports->cashReceipts('2026-06-01', '2026-06-30'))->toBe(300000);
    expect($this->reports->cashReceipts('2026-05-01', '2026-05-31'))->toBe(0);
});

test('revenue counts orders by their due date', function () {
    expect($this->reports->revenue('2026-06-01', '2026-06-30'))->toBe(500000);
    expect($this->reports->revenue('2026-05-01', '2026-05-31'))->toBe(100000);
});

test('the expense breakdown lists one row per account with movement', function () {
    $rows = $this->reports->expenseBreakdown('2026-06-01', '2026-06-30');

    expect($rows->pluck('total', 'code')->all())->toBe([
        '5000' => 80000,   // الرواتب
        '5220' => 40000,   // إيجار
    ]);
    expect($rows->firstWhere('code', '5220')['name'])->toBe('إيجار');
    expect($this->reports->expensesTotal('2026-06-01', '2026-06-30'))->toBe(120000);
});

test('the trial balance is balanced and covers every account with movement', function () {
    $rows = $this->reports->trialBalance();

    expect($rows->sum('debit'))->toBe($rows->sum('credit'));
    expect($rows->pluck('code')->all())->toContain('1000', '1100', '4000', '5000', '5220');

    // Gross debit/credit, not just the net, so a swapped contra account or a
    // flipped sign can't hide behind a balanced total.
    $cash = $rows->firstWhere('code', '1000');
    expect($cash)->toMatchArray(['type' => 'asset', 'debit' => 300000, 'credit' => 120000]);

    $revenue = $rows->firstWhere('code', '4000');
    expect($revenue)->toMatchArray(['type' => 'revenue', 'debit' => 0, 'credit' => 600000]);
});

test('a dentist statement carries an opening balance and a running list', function () {
    $statement = $this->reports->dentistStatement($this->dentist->id, '2026-06-01', '2026-06-30');

    expect($statement['opening'])->toBe(100000);     // the May order
    expect($statement['lines'])->toHaveCount(2);     // June order + June payment
    expect($statement['closing'])->toBe(300000);

    // The individual lines and the running balance, not just the final
    // total, so a wrong debit/credit or a broken running sum shows up.
    [$first, $second] = $statement['lines']->all();
    expect($first)->toMatchArray(['date' => '2026-06-10 00:00:00', 'debit' => 500000, 'credit' => 0, 'balance' => 600000]);
    expect($second)->toMatchArray(['date' => '2026-06-15 00:00:00', 'debit' => 0, 'credit' => 300000, 'balance' => 300000]);
});

test('a dentist statement is not polluted by another dentist sharing the period', function () {
    $other = Dentist::create(['name' => 'د. ليلى']);
    Order::create(['dentist_id' => $other->id, 'due_date' => '2026-06-20', 'amount' => 999999, 'status' => 'pending']);

    $statement = $this->reports->dentistStatement($this->dentist->id, '2026-06-01', '2026-06-30');

    expect($statement['lines'])->toHaveCount(2);
    expect($statement['closing'])->toBe(300000);
});

test('account lines are newest first and stay on their own account', function () {
    $lines = $this->reports->accountLines('1000');

    // Cash: dentist payment in (06-15), salary out (06-05), rent out (06-01).
    expect($lines)->toHaveCount(3);

    expect($lines->first())->toMatchArray(['date' => '2026-06-15 00:00:00', 'debit' => 300000, 'credit' => 0]);
    expect($lines->last())->toMatchArray(['date' => '2026-06-01 00:00:00', 'debit' => 0, 'credit' => 40000]);

    // None of these lines belong to receivables — a join bug that pulled in
    // the wrong account would inflate the count or the totals below.
    expect($lines->sum('debit') - $lines->sum('credit'))->toBe(180000);
});

test('account lines respect a date range', function () {
    // Strictly inside the range on both ends, clear of the rent line (06-01)
    // and the payment line (06-15).
    $lines = $this->reports->accountLines('1000', '2026-06-03', '2026-06-10');

    expect($lines)->toHaveCount(1);
    expect($lines->first())->toMatchArray(['date' => '2026-06-05 00:00:00', 'debit' => 0, 'credit' => 80000]);
});
