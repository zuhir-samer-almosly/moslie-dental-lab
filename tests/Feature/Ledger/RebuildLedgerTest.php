<?php

use App\Ledger\Ledger;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MaterialPurchase;
use App\Models\Order;

test('rebuild reproduces entries for every existing record', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-11', 'amount' => 900000, 'status' => 'cancelled']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 300000, 'payment_date' => '2026-06-15']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);

    // Wipe as if the ledger had never been written.
    JournalEntry::query()->delete();
    expect(JournalEntry::count())->toBe(0);

    $this->artisan('ledger:rebuild')->assertSuccessful();

    // One order, one payment, one expense. The cancelled order posts nothing.
    expect(JournalEntry::count())->toBe(3);
});

test('rebuild is idempotent and produces byte-identical entries on rerun', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $employee = Employee::factory()->create();
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 300000, 'payment_date' => '2026-06-15']);
    EmployeePayment::create(['employee_id' => $employee->id, 'amount' => 80000, 'payment_date' => '2026-06-05']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);

    $snapshot = function () {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.source_type')
            ->orderBy('journal_entries.source_id')
            ->orderBy('journal_lines.account_id')
            ->orderBy('journal_lines.debit')
            ->orderBy('journal_lines.credit')
            ->get([
                'journal_entries.entry_date',
                'journal_entries.description',
                'journal_entries.source_type',
                'journal_entries.source_id',
                'journal_lines.account_id',
                'journal_lines.dentist_id',
                'journal_lines.debit',
                'journal_lines.credit',
            ])
            ->map(fn ($row) => $row->toArray())
            ->all();
    };

    $this->artisan('ledger:rebuild')->assertSuccessful();
    $firstRun = $snapshot();

    $this->artisan('ledger:rebuild')->assertSuccessful();
    $secondRun = $snapshot();

    // Not just the same count: the same accounts, amounts, dentist
    // attribution and descriptions, row for row. A rule that produced
    // different (but equal-count) entries on rerun would still pass a
    // bare count comparison; this would not.
    expect($secondRun)->toBe($firstRun);
    expect(JournalEntry::count())->toBe(4);
});

test('rebuild leaves the books balanced', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 300000, 'payment_date' => '2026-06-15']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);

    $this->artisan('ledger:rebuild')->assertSuccessful();

    $totals = JournalLine::selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
    expect((int) $totals->d)->toBe((int) $totals->c);
});

test('cash-on-hand posts the difference to owner capital when counted cash is higher', function () {
    // 40,000 out and nothing in leaves the cash box at -40,000.
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);

    $this->artisan('ledger:rebuild', ['--cash-on-hand' => 10000])->assertSuccessful();

    $cash = (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '1000')
        ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as b')
        ->value('b');

    $capital = (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '3000')
        ->selectRaw('COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) as b')
        ->value('b');

    expect($cash)->toBe(10000);
    expect($capital)->toBe(50000);
});

test('cash-on-hand posts the difference to owner capital when counted cash is lower', function () {
    // 300,000 in and nothing out leaves the computed cash box at 300,000,
    // higher than what was actually counted — the opposite direction from
    // the case above, which must debit capital and credit cash instead.
    $dentist = Dentist::create(['name' => 'د. سامي']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 300000, 'payment_date' => '2026-06-15']);

    $this->artisan('ledger:rebuild', ['--cash-on-hand' => 50000])->assertSuccessful();

    $cash = (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '1000')
        ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as b')
        ->value('b');

    $capital = (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '3000')
        ->selectRaw('COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) as b')
        ->value('b');

    expect($cash)->toBe(50000);
    expect($capital)->toBe(-250000);
});

test('rebuild reports receivables matching the old outstanding formula', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-11', 'amount' => 900000, 'status' => 'cancelled']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 300000, 'payment_date' => '2026-06-15']);

    // Old formula: billable orders (500,000; the cancelled 900,000 order is
    // excluded) minus payments (300,000) = 200,000 owed. The ledger's A/R
    // balance for this dentist must print the same figure, not merely some
    // figure — this exercises the actual comparison, not just a successful
    // exit code.
    $this->artisan('ledger:rebuild')
        ->expectsOutputToContain('د. سامي')
        ->expectsOutputToContain('200,000')
        ->assertSuccessful();

    $ledgerReceivable = (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '1100')
        ->where('journal_lines.dentist_id', $dentist->id)
        ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as b')
        ->value('b');

    expect($ledgerReceivable)->toBe(200000);
});

test('rows with no date are skipped, counted and reported instead of posted', function () {
    $employee = Employee::factory()->create();

    EmployeePayment::create(['employee_id' => $employee->id, 'amount' => 80000, 'payment_date' => null]);
    MaterialPurchase::create(['name' => 'أسنان', 'amount' => 30000, 'purchase_date' => null]);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => null]);

    // A dated row of the same kinds, to prove the skip is per-row, not
    // per-model: these must still post normally alongside the null-dated
    // ones above.
    EmployeePayment::create(['employee_id' => $employee->id, 'amount' => 50000, 'payment_date' => '2026-06-05']);

    $this->artisan('ledger:rebuild')
        ->expectsOutputToContain('EmployeePayment: 1')
        ->expectsOutputToContain('MaterialPurchase: 1')
        ->expectsOutputToContain('Expense: 1')
        ->assertSuccessful();

    // The null-dated rows never reached the ledger — that money is real but
    // invisible, matching the existing date-filtered reports.
    expect(JournalEntry::count())->toBe(1);

    $salaries = (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '5000')
        ->selectRaw('COALESCE(SUM(debit),0) as b')
        ->value('b');

    expect($salaries)->toBe(50000);
});

test('rows skipped for a reason other than a missing date are also counted and reported', function () {
    // A zero-amount expense has a real date, so it must not be miscounted as
    // "no date" — but it still fails shouldPost() and must not vanish from
    // every count either.
    Expense::create(['category' => 'rent', 'amount' => 0, 'expense_date' => '2026-06-01']);

    $this->artisan('ledger:rebuild')
        ->expectsOutputToContain('Note: 1 row(s) were also excluded for other reasons')
        ->expectsOutputToContain('Expense: 1')
        ->assertSuccessful();

    expect(JournalEntry::count())->toBe(0);
});

test('the rebuild refuses to run in production without confirmation, and touches nothing', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);

    $before = JournalEntry::count();
    expect($before)->toBeGreaterThan(0);

    $this->app['env'] = 'production';

    $this->artisan('ledger:rebuild')
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    // Declined: not a single entry should have been touched.
    expect(JournalEntry::count())->toBe($before);
});

test('--force bypasses the confirmation prompt in production', function () {
    $this->app['env'] = 'production';

    $this->artisan('ledger:rebuild', ['--force' => true])->assertSuccessful();
});

test('cash-on-hand accepts a plain negative integer', function () {
    $this->artisan('ledger:rebuild', ['--cash-on-hand' => '-500'])->assertSuccessful();

    $cash = (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '1000')
        ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as b')
        ->value('b');

    expect($cash)->toBe(-500);
});

test('cash-on-hand rejects a comma-formatted value instead of silently truncating it', function () {
    // (int) '10,000' === 10 — a three-orders-of-magnitude silent error that
    // would otherwise still report a balanced trial balance and exit 0.
    $this->artisan('ledger:rebuild', ['--cash-on-hand' => '10,000'])
        ->expectsOutputToContain('Invalid --cash-on-hand value')
        ->assertFailed();

    expect(JournalEntry::count())->toBe(0);
});

test('cash-on-hand rejects a decimal value', function () {
    $this->artisan('ledger:rebuild', ['--cash-on-hand' => '10.5'])
        ->expectsOutputToContain('Invalid --cash-on-hand value')
        ->assertFailed();
});

test('cash-on-hand rejects a non-numeric value', function () {
    $this->artisan('ledger:rebuild', ['--cash-on-hand' => 'abc'])
        ->expectsOutputToContain('Invalid --cash-on-hand value')
        ->assertFailed();
});

test('cash-on-hand rejects an empty value', function () {
    $this->artisan('ledger:rebuild', ['--cash-on-hand' => ''])
        ->expectsOutputToContain('Invalid --cash-on-hand value')
        ->assertFailed();
});

test('a failure partway through the rebuild leaves the original ledger completely untouched', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    $poison = MaterialPurchase::create(['name' => 'أسنان', 'amount' => 30000, 'purchase_date' => '2026-06-01']);

    // Created via the observer, ahead of any call to ledger:rebuild.
    expect(JournalEntry::count())->toBe(2);
    $before = JournalEntry::with('lines')->orderBy('id')->get()->toArray();

    // A ledger that throws only once it reaches a specific row, simulating a
    // failure partway through the source loop. Everything up to that point
    // (the JournalEntry::query()->delete() and every row synced before the
    // poisoned one) must be rolled back along with it, because the entire
    // rebuild runs in one transaction.
    $this->app->instance(Ledger::class, new class($poison->id) extends Ledger
    {
        public function __construct(private readonly int $poisonId) {}

        public function sync(\Illuminate\Database\Eloquent\Model $source): void
        {
            if ($source instanceof MaterialPurchase && $source->id === $this->poisonId) {
                throw new \RuntimeException('simulated mid-rebuild failure');
            }

            parent::sync($source);
        }
    });

    // The command doesn't catch this itself (only the real `artisan` CLI
    // entrypoint's exception handler would); the test harness's Kernel::call()
    // doesn't either, so it propagates out of run() rather than becoming a
    // FAILURE exit code here.
    expect(fn () => $this->artisan('ledger:rebuild')->run())
        ->toThrow(RuntimeException::class, 'simulated mid-rebuild failure');

    // Same rows, same ids — a real rollback, not a re-creation that happens
    // to match.
    $after = JournalEntry::with('lines')->orderBy('id')->get()->toArray();
    expect($after)->toBe($before);
});
