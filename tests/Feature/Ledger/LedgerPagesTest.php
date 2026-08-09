<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->dentist = Dentist::create(['name' => 'د. سامي']);
    $this->order = Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    $this->payment = DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
    $this->expense = Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);
});

test('the trial balance page lists accounts and balances', function () {
    $this->get(route('ledger.trial-balance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/trial-balance')
            ->has('accounts', 4)
            // Pin each exercised account's code and which side its movement
            // landed on — the aggregate totals below stay equal even if every
            // account's debit and credit were swapped, so this is what
            // actually proves the columns are not crossed.
            ->where('accounts.0.code', '1000')
            ->where('accounts.0.name', 'الصندوق')
            ->where('accounts.0.debit', 200000)
            ->where('accounts.0.credit', 40000)
            ->where('accounts.1.code', '1100')
            ->where('accounts.1.name', 'الذمم المدينة')
            ->where('accounts.1.debit', 500000)
            ->where('accounts.1.credit', 200000)
            ->where('accounts.2.code', '4000')
            ->where('accounts.2.name', 'إيرادات الأعمال')
            ->where('accounts.2.debit', 0)
            ->where('accounts.2.credit', 500000)
            ->where('accounts.3.code', '5220')
            ->where('accounts.3.name', 'إيجار')
            ->where('accounts.3.debit', 40000)
            ->where('accounts.3.credit', 0)
            ->where('totals.debit', 740000)
            ->where('totals.credit', 740000)
            ->where('balanced', true)
        );
});

test('the cash page shows the balance and its movements', function () {
    $this->get(route('ledger.cash'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/cash')
            ->where('balance', 160000)
            // Pin the ordered contents (newest first), not just a count — a
            // six-bucket-style bug that dropped or duplicated a line, or
            // landed an amount on the wrong side, would still pass ->has(2).
            ->has('lines', 2)
            ->where('lines.0.date', '2026-06-15')
            ->where('lines.0.description', "دفعة #{$this->payment->id}")
            ->where('lines.0.debit', 200000)
            ->where('lines.0.credit', 0)
            ->where('lines.1.date', '2026-06-01')
            ->where('lines.1.description', 'مصروف عام')
            ->where('lines.1.debit', 0)
            ->where('lines.1.credit', 40000)
        );
});

test('the trial balance page reports zero accounts as balanced with no rows, not a lone total', function () {
    // With every entry filtered out by an as_of before anything was posted,
    // the page must fall back to the empty state, not render a lone
    // "الإجمالي 0 / 0" row under a "متوازنة" badge — the component only
    // knows to do that if `accounts` is empty AND it branches on that.
    $this->get(route('ledger.trial-balance', ['as_of' => '2026-01-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/trial-balance')
            ->has('accounts', 0)
            ->where('totals.debit', 0)
            ->where('totals.credit', 0)
            ->where('balanced', true)
        );
});

test('guests cannot reach the ledger pages', function () {
    auth()->logout();

    $this->get(route('ledger.trial-balance'))->assertRedirect(route('login'));
    $this->get(route('ledger.cash'))->assertRedirect(route('login'));
});

test('the trial balance as_of filter excludes movements after the cutoff', function () {
    // Posted a month after the fixture's window — must disappear once as_of
    // cuts it off, and reappear once it doesn't.
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-07-05', 'amount' => 60000, 'status' => 'pending']);

    $this->get(route('ledger.trial-balance', ['as_of' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts', 4)
            ->where('totals.debit', 740000)
            ->where('totals.credit', 740000)
        );

    $this->get(route('ledger.trial-balance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.debit', 800000)
            ->where('totals.credit', 800000)
        );
});

test('the cash page from and to filters exclude out-of-range movements', function () {
    // A July receipt — outside the June window the other assertions use.
    DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 90000, 'payment_date' => '2026-07-01']);

    // `to` bounds both the balance (as-of) and the line list.
    $this->get(route('ledger.cash', ['to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('balance', 160000)
            ->has('lines', 2)
        );

    // `from` excludes the June 1st rent payout. The July receipt is still in
    // range (no `to` bound this time), leaving the two dentist payments,
    // newest first.
    $this->get(route('ledger.cash', ['from' => '2026-06-10']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('lines', 2)
            ->where('lines.0.date', '2026-07-01')
            ->where('lines.1.date', '2026-06-15')
        );
});

test('a malformed date query param collapses to no filter rather than erroring', function () {
    // `filters.*` is the direct proof the value never reached the query — a
    // parseDate that leaked the raw string would let SQLite compare
    // `entry_date <= 'not-a-date'` lexically, include every row, and still
    // match the same totals as "no filter" by coincidence.
    $this->get(route('ledger.trial-balance', ['as_of' => 'not-a-date']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.as_of', null)
            ->where('totals.debit', 740000)
            ->where('totals.credit', 740000)
        );

    $this->get(route('ledger.cash', ['from' => 'not-a-date', 'to' => 'also-bad']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.from', null)
            ->where('filters.to', null)
            ->where('balance', 160000)
            ->has('lines', 2)
        );
});

test('an overflow date is rejected rather than silently rolled over', function () {
    // Carbon::createFromFormat only throws on an outright parse error, not
    // on out-of-range components, so a naive implementation would accept
    // 2026-02-31 by silently rolling it over to 2026-03-03 — a real date,
    // just not the one that was asked for, and one that lands after every
    // fixture entry, which would visibly empty out the filtered results
    // instead of falling back to "no filter".
    $this->get(route('ledger.trial-balance', ['as_of' => '2026-02-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.as_of', null)
            ->where('totals.debit', 740000)
            ->where('totals.credit', 740000)
        );

    $this->get(route('ledger.cash', ['to' => '2026-02-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.to', null)
            ->where('balance', 160000)
        );
});
