# Double-Entry Ledger Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single-entry money layer with a double-entry ledger that becomes the source of truth for every money figure the app reports.

**Architecture:** Three new tables (`accounts`, `journal_entries`, `journal_lines`) hold the ledger. The five existing money models keep their rows and columns, but a model observer posts a balanced journal entry on every save and removes it on delete. A `LedgerReports` query service exposes account balances and movements; every reporting controller switches from summing domain tables to calling it. A rerunnable `ledger:rebuild` artisan command backfills the whole history.

**Tech Stack:** Laravel 12, Pest 4, Inertia 2 + React 19 + TypeScript, Tailwind 4, MySQL (prod) / SQLite (tests).

**Spec:** `docs/superpowers/specs/2026-08-07-double-entry-ledger-design.md`

## Global Constraints

- **Money is integers.** No floats, no decimals, no money library. Every amount column is `integer`, matching the existing schema.
- **Single currency.** No currency column, no conversion.
- **Arabic / RTL.** All user-facing strings are Arabic. Pages render inside `AppLayout`, which is already RTL. Numbers format with `toLocaleString('en-US')`, matching every existing page.
- **Dates are `Y-m-d` strings** at service boundaries. `Date::use(CarbonImmutable::class)` is set in `AppServiceProvider`, so Carbon instances are immutable — `->copy()` is harmless but unnecessary.
- **Queries must work on both SQLite and MySQL.** No driver-specific date functions (`MONTH()`, `DATE_FORMAT`). Follow the existing pattern in `FinanceController::trend` — query a whole range, bucket in PHP.
- **Tests live in `tests/Feature/`.** `tests/Pest.php` applies `RefreshDatabase` only to `Feature`; `tests/Unit/` is empty and has no database. Put ledger tests in `tests/Feature/Ledger/`.
- **Run checks via the `run-checks` skill.** Bare `php artisan test` fails on this machine (read-only PHP 8.3 container vs 8.4 host). The skill encodes the storage/cache env redirects. `npm run types` and `composer lint` run normally.
- **Never break the trial balance.** Any code path that writes to the ledger must produce entries where debits equal credits.
- **Order status `recieved` is a known misspelling** baked into the DB enum and validation rules. Do not fix it. Statuses are `pending`, `completed`, `cancelled`, `recieved`.
- **Commit after every task.** Work on branch `double-entry-ledger`.

---

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `database/migrations/*_create_accounts_table.php` | Accounts table + seeds the chart of accounts |
| `database/migrations/*_create_journal_entries_table.php` | Journal entry header |
| `database/migrations/*_create_journal_lines_table.php` | Journal entry lines |
| `app/Models/Account.php` | Account row + code→id lookup |
| `app/Models/JournalEntry.php` | Entry header, `lines()` relation |
| `app/Models/JournalLine.php` | Single debit or credit line |
| `app/Ledger/AccountCode.php` | Enum of structural account codes |
| `app/Ledger/Line.php` | Immutable debit/credit line value object |
| `app/Ledger/Posting.php` | Interface: `shouldPost()`, `date()`, `description()`, `lines()` |
| `app/Ledger/Ledger.php` | `post()` / `sync()` / `forget()`; enforces balance |
| `app/Ledger/UnbalancedEntryException.php` | Thrown when debits ≠ credits |
| `app/Ledger/LedgerReports.php` | All read queries: balances, movements, breakdowns |
| `app/Ledger/Postings/*.php` | One posting rule per source model (5 files) |
| `app/Observers/LedgerObserver.php` | `saved`/`deleted` → `Ledger` |
| `app/Console/Commands/RebuildLedger.php` | `ledger:rebuild` backfill + verification |
| `app/Http/Controllers/LedgerController.php` | The four read-only ledger pages |
| `resources/js/pages/ledger/*.tsx` | Trial balance, cash, journal, statement, statement print |

**Modified:** `OutstandingController`, `FinanceController`, `DashboardController`, `ReportController`, `InvoiceController`, `ExpenseController`, `StoreExpenseRequest`, `UpdateExpenseRequest`, `Expense`, `HandleInertiaRequests`, `app-sidebar.tsx`, `types/models.ts`, `routes/web.php`, `CLAUDE.md`.

---

## Task 1: Ledger schema and models

**Files:**
- Create: `database/migrations/2026_08_07_000001_create_accounts_table.php`
- Create: `database/migrations/2026_08_07_000002_create_journal_entries_table.php`
- Create: `database/migrations/2026_08_07_000003_create_journal_lines_table.php`
- Create: `app/Models/Account.php`, `app/Models/JournalEntry.php`, `app/Models/JournalLine.php`
- Test: `tests/Feature/Ledger/ChartOfAccountsTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `Account::idFor(string $code): int`, `Account::typeFor(string $code): string`, `Account::expenseCategories(): Collection`, models `JournalEntry` (`lines` relation, fillable `entry_date`, `description`, `source_type`, `source_id`) and `JournalLine` (fillable `account_id`, `dentist_id`, `debit`, `credit`)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/ChartOfAccountsTest.php`:

```php
<?php

use App\Models\Account;

test('the chart of accounts is seeded by migration', function () {
    expect(Account::count())->toBe(13);
    expect(Account::where('code', '1000')->value('name'))->toBe('الصندوق');
    expect(Account::where('code', '1100')->value('type'))->toBe('asset');
    expect(Account::where('code', '4000')->value('type'))->toBe('revenue');
});

test('expense categories come from accounts that carry a category key', function () {
    $keys = Account::expenseCategories()->pluck('name', 'category_key')->all();

    expect($keys)->toBe([
        'transport' => 'مواصلات وسفر',
        'taxes' => 'ضرائب',
        'rent' => 'إيجار',
        'utilities' => 'كهرباء وماء',
        'maintenance' => 'صيانة',
        'other' => 'أخرى',
    ]);
});

test('idFor resolves a code to its primary key and caches it', function () {
    $id = Account::idFor('1000');

    expect($id)->toBe(Account::where('code', '1000')->value('id'));
    expect(Account::typeFor('5000'))->toBe('expense');
});

test('idFor throws for an unknown code', function () {
    Account::idFor('9999');
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run test to verify it fails**

Use the `run-checks` skill, or replicate its env redirects, filtering to this file:

```
php artisan test --filter=ChartOfAccounts
```

Expected: FAIL — `Class "App\Models\Account" not found`.

- [ ] **Step 3: Write the accounts migration**

Create `database/migrations/2026_08_07_000001_create_accounts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The chart of accounts. Seeded here rather than in a seeder because it
     * is reference data the ledger cannot function without — production runs
     * migrations, not seeders.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // asset|liability|equity|revenue|expense
            // Links an expense account to `expenses.category`. This column is
            // what makes the accounts table the single definition of expense
            // categories, replacing Expense::CATEGORIES.
            $table->string('category_key')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('accounts')->insert(array_map(
            fn (array $row, int $i) => $row + [
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $rows = [
                ['code' => '1000', 'name' => 'الصندوق', 'type' => 'asset', 'category_key' => null],
                ['code' => '1100', 'name' => 'الذمم المدينة', 'type' => 'asset', 'category_key' => null],
                ['code' => '3000', 'name' => 'رأس المال', 'type' => 'equity', 'category_key' => null],
                ['code' => '4000', 'name' => 'إيرادات الأعمال', 'type' => 'revenue', 'category_key' => null],
                ['code' => '5000', 'name' => 'الرواتب', 'type' => 'expense', 'category_key' => null],
                ['code' => '5100', 'name' => 'المواد', 'type' => 'expense', 'category_key' => null],
                ['code' => '5200', 'name' => 'مواصلات وسفر', 'type' => 'expense', 'category_key' => 'transport'],
                ['code' => '5210', 'name' => 'ضرائب', 'type' => 'expense', 'category_key' => 'taxes'],
                ['code' => '5220', 'name' => 'إيجار', 'type' => 'expense', 'category_key' => 'rent'],
                ['code' => '5230', 'name' => 'كهرباء وماء', 'type' => 'expense', 'category_key' => 'utilities'],
                ['code' => '5240', 'name' => 'صيانة', 'type' => 'expense', 'category_key' => 'maintenance'],
                ['code' => '5290', 'name' => 'أخرى', 'type' => 'expense', 'category_key' => 'other'],
                ['code' => '5900', 'name' => 'ديون معدومة', 'type' => 'expense', 'category_key' => null],
            ],
            array_keys($rows),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```

- [ ] **Step 4: Write the journal entries migration**

Create `database/migrations/2026_08_07_000002_create_journal_entries_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            // The business date, deliberately not created_at: it mirrors the
            // date column the source record is reported by (due_date,
            // payment_date, purchase_date, expense_date).
            $table->date('entry_date');
            $table->string('description');
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
```

- [ ] **Step 5: Write the journal lines migration**

Create `database/migrations/2026_08_07_000003_create_journal_lines_table.php`:

```php
<?php

use App\Models\Dentist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            // Subsidiary detail, used only on receivable lines so a dentist's
            // statement is a filter rather than an account of its own.
            $table->foreignIdFor(Dentist::class, 'dentist_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
            $table->timestamps();

            $table->index('account_id');
            $table->index('dentist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
```

- [ ] **Step 6: Write the models**

Create `app/Models/Account.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type', 'category_key', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Code → row cache. The chart of accounts is small, fixed, and read on
     * every ledger write, so it is loaded once per request.
     *
     * @var Collection<string, self>|null
     */
    private static ?Collection $cache = null;

    public static function chart(): Collection
    {
        return self::$cache ??= self::query()->get()->keyBy('code');
    }

    /**
     * Drop the cache. Tests refresh the database between cases, so the cached
     * ids from a previous case would otherwise be stale.
     */
    public static function flushChart(): void
    {
        self::$cache = null;
    }

    public static function idFor(string $code): int
    {
        $account = self::chart()->get($code);

        if (! $account) {
            throw new \InvalidArgumentException("Unknown account code [{$code}].");
        }

        return $account->id;
    }

    public static function typeFor(string $code): string
    {
        $account = self::chart()->get($code);

        if (! $account) {
            throw new \InvalidArgumentException("Unknown account code [{$code}].");
        }

        return $account->type;
    }

    /**
     * Expense accounts that map to an `expenses.category` value, in display
     * order. This is the single definition of the general-expense categories.
     */
    public static function expenseCategories(): Collection
    {
        return self::chart()
            ->filter(fn (self $a) => $a->category_key !== null && $a->is_active)
            ->sortBy('sort_order')
            ->values();
    }
}
```

Create `app/Models/JournalEntry.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    protected $fillable = ['entry_date', 'description', 'source_type', 'source_id'];

    protected $casts = ['entry_date' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Create `app/Models/JournalLine.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_id', 'dentist_id', 'debit', 'credit'];

    protected $casts = ['debit' => 'integer', 'credit' => 'integer'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }
}
```

- [ ] **Step 7: Flush the account cache between tests**

The chart cache is static, so it survives `RefreshDatabase`. Add to `tests/Pest.php`, immediately after the existing `pest()->extend(...)` call:

```php
uses()->beforeEach(function () {
    \App\Models\Account::flushChart();
})->in('Feature');
```

- [ ] **Step 8: Run test to verify it passes**

```
php artisan test --filter=ChartOfAccounts
```

Expected: PASS, 4 tests.

- [ ] **Step 9: Run the full suite to confirm nothing broke**

Run the `run-checks` skill. Expected: all existing tests still pass — nothing reads the new tables yet.

- [ ] **Step 10: Commit**

```bash
git add database/migrations app/Models/Account.php app/Models/JournalEntry.php app/Models/JournalLine.php tests/Feature/Ledger/ChartOfAccountsTest.php tests/Pest.php
git commit -m "feat(ledger): add accounts, journal entries and lines schema"
```

---

## Task 2: The Ledger service and its balance invariant

**Files:**
- Create: `app/Ledger/AccountCode.php`, `app/Ledger/Line.php`, `app/Ledger/Posting.php`, `app/Ledger/UnbalancedEntryException.php`, `app/Ledger/Ledger.php`
- Test: `tests/Feature/Ledger/LedgerTest.php`

**Interfaces:**
- Consumes: `Account::idFor()` from Task 1
- Produces:
  - `AccountCode` enum — cases `CASH='1000'`, `RECEIVABLE='1100'`, `CAPITAL='3000'`, `REVENUE='4000'`, `SALARIES='5000'`, `MATERIALS='5100'`, `BAD_DEBT='5900'`
  - `Line::debit(string $code, int $amount, ?int $dentistId = null): Line` and `Line::credit(...)` with public readonly `$accountCode`, `$debit`, `$credit`, `$dentistId`
  - `Posting` interface — `shouldPost(): bool`, `date(): string`, `description(): string`, `lines(): array`
  - `Ledger::post(string $date, string $description, array $lines, ?Model $source = null): JournalEntry`
  - `Ledger::sync(Model $source): void`, `Ledger::forget(Model $source): void`
  - `Ledger::POSTINGS` — model class → posting class map, extended in Tasks 3–5

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/LedgerTest.php`:

```php
<?php

use App\Ledger\Ledger;
use App\Ledger\Line;
use App\Ledger\UnbalancedEntryException;
use App\Models\JournalEntry;
use App\Models\JournalLine;

test('a balanced entry is persisted with its lines', function () {
    $entry = app(Ledger::class)->post('2026-06-01', 'اختبار', [
        Line::debit('1000', 5000),
        Line::credit('4000', 5000),
    ]);

    expect(JournalEntry::count())->toBe(1);
    expect($entry->entry_date->toDateString())->toBe('2026-06-01');
    expect($entry->description)->toBe('اختبار');

    $lines = JournalLine::orderBy('id')->get();
    expect($lines)->toHaveCount(2);
    expect($lines[0]->debit)->toBe(5000);
    expect($lines[0]->credit)->toBe(0);
    expect($lines[1]->credit)->toBe(5000);
});

test('an unbalanced entry is refused and nothing is written', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'مختل', [
        Line::debit('1000', 5000),
        Line::credit('4000', 4000),
    ]))->toThrow(UnbalancedEntryException::class);

    expect(JournalEntry::count())->toBe(0);
    expect(JournalLine::count())->toBe(0);
});

test('an entry with no lines is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'فارغ', []))
        ->toThrow(UnbalancedEntryException::class);
});

test('a line carrying both a debit and a credit is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'خطأ', [
        new Line('1000', 500, 500, null),
    ]))->toThrow(UnbalancedEntryException::class);
});

test('a dentist id is stored on the line it belongs to', function () {
    $dentist = \App\Models\Dentist::create(['name' => 'د. تجربة']);

    app(Ledger::class)->post('2026-06-01', 'اختبار', [
        Line::debit('1100', 700, $dentist->id),
        Line::credit('4000', 700),
    ]);

    $lines = JournalLine::orderBy('id')->get();
    expect($lines[0]->dentist_id)->toBe($dentist->id);
    expect($lines[1]->dentist_id)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=LedgerTest
```

Expected: FAIL — `Class "App\Ledger\Ledger" not found`.

- [ ] **Step 3: Write the value objects**

Create `app/Ledger/AccountCode.php`:

```php
<?php

namespace App\Ledger;

/**
 * The structural accounts the posting rules reference by name. Expense
 * category accounts (5200–5290) are deliberately absent: they are resolved
 * at runtime from `accounts.category_key`, so adding a category is a row,
 * not a code change.
 */
enum AccountCode: string
{
    case CASH = '1000';
    case RECEIVABLE = '1100';
    case CAPITAL = '3000';
    case REVENUE = '4000';
    case SALARIES = '5000';
    case MATERIALS = '5100';
    case BAD_DEBT = '5900';
}
```

Create `app/Ledger/Line.php`:

```php
<?php

namespace App\Ledger;

/**
 * One side of an entry. A line carries a debit or a credit, never both.
 */
final class Line
{
    public function __construct(
        public readonly string $accountCode,
        public readonly int $debit,
        public readonly int $credit,
        public readonly ?int $dentistId,
    ) {}

    public static function debit(string $code, int $amount, ?int $dentistId = null): self
    {
        return new self($code, $amount, 0, $dentistId);
    }

    public static function credit(string $code, int $amount, ?int $dentistId = null): self
    {
        return new self($code, 0, $amount, $dentistId);
    }
}
```

Create `app/Ledger/Posting.php`:

```php
<?php

namespace App\Ledger;

/**
 * Turns one domain record into the ledger lines it implies. Implementations
 * take the model in their constructor and touch nothing else — no requests,
 * no database, no other models — so they can be tested directly.
 */
interface Posting
{
    /** Whether this record should appear in the ledger at all. */
    public function shouldPost(): bool;

    /** Business date, `Y-m-d`. */
    public function date(): string;

    public function description(): string;

    /** @return list<Line> */
    public function lines(): array;
}
```

Create `app/Ledger/UnbalancedEntryException.php`:

```php
<?php

namespace App\Ledger;

/**
 * An entry whose debits do not equal its credits is a bug, not a validation
 * failure. It must never reach the database.
 */
class UnbalancedEntryException extends \RuntimeException {}
```

- [ ] **Step 4: Write the Ledger service**

Create `app/Ledger/Ledger.php`:

```php
<?php

namespace App\Ledger;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ledger
{
    /**
     * Source model → posting rule. Extended as each rule lands.
     *
     * @var array<class-string, class-string<Posting>>
     */
    public const POSTINGS = [];

    /**
     * Rewrite a source record's entries from its current state. The ledger
     * mirrors what the records say now: an edit replaces the entry, a cancel
     * removes it. The domain record remains the history of what happened.
     */
    public function sync(Model $source): void
    {
        DB::transaction(function () use ($source) {
            $this->forget($source);

            $posting = $this->postingFor($source);

            if (! $posting || ! $posting->shouldPost()) {
                return;
            }

            $this->post($posting->date(), $posting->description(), $posting->lines(), $source);
        });
    }

    /** Remove every entry a source record produced. Lines cascade. */
    public function forget(Model $source): void
    {
        JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->delete();
    }

    /**
     * @param  list<Line>  $lines
     *
     * @throws UnbalancedEntryException
     */
    public function post(string $date, string $description, array $lines, ?Model $source = null): JournalEntry
    {
        $this->assertBalanced($lines);

        return DB::transaction(function () use ($date, $description, $lines, $source) {
            $entry = JournalEntry::create([
                'entry_date' => $date,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => Account::idFor($line->accountCode),
                    'dentist_id' => $line->dentistId,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ]);
            }

            return $entry;
        });
    }

    private function postingFor(Model $source): ?Posting
    {
        $class = self::POSTINGS[$source::class] ?? null;

        return $class ? new $class($source) : null;
    }

    /** @param  list<Line>  $lines */
    private function assertBalanced(array $lines): void
    {
        if ($lines === []) {
            throw new UnbalancedEntryException('An entry must have at least two lines.');
        }

        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            if ($line->debit !== 0 && $line->credit !== 0) {
                throw new UnbalancedEntryException('A line carries a debit or a credit, never both.');
            }

            if ($line->debit < 0 || $line->credit < 0) {
                throw new UnbalancedEntryException('Line amounts must not be negative.');
            }

            $debits += $line->debit;
            $credits += $line->credit;
        }

        if ($debits !== $credits) {
            throw new UnbalancedEntryException("Entry is unbalanced: debits {$debits}, credits {$credits}.");
        }
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```
php artisan test --filter=LedgerTest
```

Expected: PASS, 5 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Ledger tests/Feature/Ledger/LedgerTest.php
git commit -m "feat(ledger): add Ledger service with balance invariant"
```

---

## Task 3: Order posting and observer wiring

**Files:**
- Create: `app/Ledger/Postings/OrderPosting.php`, `app/Observers/LedgerObserver.php`
- Modify: `app/Ledger/Ledger.php` (POSTINGS map), `app/Models/Order.php` (attach observer)
- Test: `tests/Feature/Ledger/OrderPostingTest.php`

**Interfaces:**
- Consumes: `Ledger`, `Line`, `Posting`, `AccountCode` from Task 2
- Produces: `OrderPosting`, and `LedgerObserver` which every later source model attaches to via `#[ObservedBy(LedgerObserver::class)]`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/OrderPostingTest.php`:

```php
<?php

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;

/** Receivable balance for a dentist, read straight from the lines. */
function receivable(int $dentistId): int
{
    return (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '1100')
        ->where('journal_lines.dentist_id', $dentistId)
        ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
        ->value('balance');
}

test('creating an order debits receivables and credits revenue', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);

    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $entry = JournalEntry::sole();
    expect($entry->entry_date->toDateString())->toBe('2026-06-10');
    expect($entry->source_type)->toBe(Order::class);
    expect($entry->source_id)->toBe($order->id);
    expect(receivable($dentist->id))->toBe(500000);
});

test('editing an order rewrites its entry instead of adding one', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $order->update(['amount' => 400000]);

    expect(JournalEntry::count())->toBe(1);
    expect(receivable($dentist->id))->toBe(400000);
});

test('cancelling an order removes its entry', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $order->update(['status' => 'cancelled']);

    expect(JournalEntry::count())->toBe(0);
    expect(receivable($dentist->id))->toBe(0);
});

test('un-cancelling an order posts it again', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'cancelled',
    ]);

    expect(JournalEntry::count())->toBe(0);

    $order->update(['status' => 'pending']);

    expect(receivable($dentist->id))->toBe(500000);
});

test('deleting an order removes its entry', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $order->delete();

    expect(JournalEntry::count())->toBe(0);
});

test('a zero-amount order posts nothing', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 0,
        'status' => 'pending',
    ]);

    expect(JournalEntry::count())->toBe(0);
});
```

Delete the `receivable()` helper from this file once Task 8 lands and replace its uses with `LedgerReports` — noted in Task 8.

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=OrderPosting
```

Expected: FAIL — no journal entries are created.

- [ ] **Step 3: Write the posting rule**

Create `app/Ledger/Postings/OrderPosting.php`:

```php
<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * An order is a receivable the moment it exists. Cancelled orders post
 * nothing, matching Order::billable() — the scope every money report uses.
 *
 * Dated by `due_date`, and valued from `orders.amount` rather than the
 * `total` items accessor, because those are what the existing reports use.
 */
final class OrderPosting implements Posting
{
    public function __construct(private readonly Order $order) {}

    public function shouldPost(): bool
    {
        return $this->order->status !== 'cancelled' && (int) $this->order->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->order->due_date)->toDateString();
    }

    public function description(): string
    {
        return "طلب #{$this->order->id}";
    }

    public function lines(): array
    {
        $amount = (int) $this->order->amount;

        return [
            Line::debit(AccountCode::RECEIVABLE->value, $amount, $this->order->dentist_id),
            Line::credit(AccountCode::REVENUE->value, $amount),
        ];
    }
}
```

- [ ] **Step 4: Write the observer**

Create `app/Observers/LedgerObserver.php`:

```php
<?php

namespace App\Observers;

use App\Ledger\Ledger;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the ledger in step with the money models. Attached via
 * #[ObservedBy] on each source model.
 *
 * Posting is automatic rather than an explicit call from each controller
 * because it must also cover the backfill command, the seeders, and any
 * future import path — none of which go through a controller.
 */
class LedgerObserver
{
    public function __construct(private readonly Ledger $ledger) {}

    public function saved(Model $model): void
    {
        $this->ledger->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->ledger->forget($model);
    }
}
```

- [ ] **Step 5: Register the posting and attach the observer**

In `app/Ledger/Ledger.php`, replace the empty `POSTINGS` constant with:

```php
    public const POSTINGS = [
        \App\Models\Order::class => \App\Ledger\Postings\OrderPosting::class,
    ];
```

In `app/Models/Order.php`, add the attribute above the class declaration:

```php
use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(LedgerObserver::class)]
class Order extends Model
```

- [ ] **Step 6: Run test to verify it passes**

```
php artisan test --filter=OrderPosting
```

Expected: PASS, 6 tests.

- [ ] **Step 7: Run the full suite**

Run the `run-checks` skill. Existing tests create orders, so entries now appear — but nothing reads them yet, so every existing assertion should still hold. If anything fails, it is a real interaction and must be understood before continuing, not worked around.

- [ ] **Step 8: Commit**

```bash
git add app/Ledger app/Observers app/Models/Order.php tests/Feature/Ledger/OrderPostingTest.php
git commit -m "feat(ledger): post orders to receivables and revenue"
```

---

## Task 4: Dentist payment posting

**Files:**
- Create: `app/Ledger/Postings/DentistPaymentPosting.php`
- Modify: `app/Ledger/Ledger.php`, `app/Models/DentistPayment.php`
- Test: `tests/Feature/Ledger/DentistPaymentPostingTest.php`

**Interfaces:**
- Consumes: `Posting`, `Line`, `AccountCode`, `LedgerObserver`
- Produces: `DentistPaymentPosting`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/DentistPaymentPostingTest.php`:

```php
<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;

function balanceOf(string $code): int
{
    return (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', $code)
        ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
        ->value('balance');
}

test('a payment debits cash and credits the dentist receivable', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 300000,
        'payment_date' => '2026-06-15',
    ]);

    expect(balanceOf('1000'))->toBe(300000);   // cash box
    expect(balanceOf('1100'))->toBe(200000);   // still owed
});

test('a payment with no payment_date falls back to created_at', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);

    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 100000,
    ]);

    expect(JournalEntry::sole()->entry_date->toDateString())
        ->toBe($payment->created_at->toDateString());
});

test('deleting a payment restores the receivable', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 300000,
        'payment_date' => '2026-06-15',
    ]);

    $payment->delete();

    expect(balanceOf('1000'))->toBe(0);
    expect(balanceOf('1100'))->toBe(500000);
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=DentistPaymentPosting
```

Expected: FAIL — cash balance is 0.

- [ ] **Step 3: Write the posting rule**

Create `app/Ledger/Postings/DentistPaymentPosting.php`:

```php
<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\DentistPayment;
use Illuminate\Support\Carbon;

/**
 * Money in from a dentist: it lands in the cash box and reduces what that
 * dentist owes.
 *
 * Dated by `payment_date` falling back to `created_at`, matching the
 * COALESCE the existing reports use.
 */
final class DentistPaymentPosting implements Posting
{
    public function __construct(private readonly DentistPayment $payment) {}

    public function shouldPost(): bool
    {
        return (int) $this->payment->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->payment->payment_date ?? $this->payment->created_at)->toDateString();
    }

    public function description(): string
    {
        return "دفعة #{$this->payment->id}";
    }

    public function lines(): array
    {
        $amount = (int) $this->payment->amount;

        return [
            Line::debit(AccountCode::CASH->value, $amount),
            Line::credit(AccountCode::RECEIVABLE->value, $amount, $this->payment->dentist_id),
        ];
    }
}
```

- [ ] **Step 4: Register it**

Add to `Ledger::POSTINGS`:

```php
        \App\Models\DentistPayment::class => \App\Ledger\Postings\DentistPaymentPosting::class,
```

Add to `app/Models/DentistPayment.php` above the class:

```php
use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(LedgerObserver::class)]
class DentistPayment extends Model
```

- [ ] **Step 5: Run test to verify it passes**

```
php artisan test --filter=DentistPaymentPosting
```

Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Ledger app/Models/DentistPayment.php tests/Feature/Ledger/DentistPaymentPostingTest.php
git commit -m "feat(ledger): post dentist payments to cash and receivables"
```

---

## Task 5: Expense-side postings

**Files:**
- Create: `app/Ledger/Postings/EmployeePaymentPosting.php`, `MaterialPurchasePosting.php`, `ExpensePosting.php`
- Modify: `app/Ledger/Ledger.php`, `app/Models/EmployeePayment.php`, `app/Models/MaterialPurchase.php`, `app/Models/Expense.php`
- Test: `tests/Feature/Ledger/ExpensePostingTest.php`

**Interfaces:**
- Consumes: `Posting`, `Line`, `AccountCode`, `Account::expenseCategories()`
- Produces: the three posting classes. `ExpensePosting` resolves its debit account from `accounts.category_key`, falling back to code `5290` (أخرى) for an unrecognised category.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/ExpensePostingTest.php`:

```php
<?php

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
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=ExpensePostingTest
```

Expected: FAIL — balances are 0.

- [ ] **Step 3: Write the three posting rules**

Create `app/Ledger/Postings/EmployeePaymentPosting.php`:

```php
<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\EmployeePayment;
use Illuminate\Support\Carbon;

/** A salary payout: money leaves the cash box as a salary expense. */
final class EmployeePaymentPosting implements Posting
{
    public function __construct(private readonly EmployeePayment $payment) {}

    public function shouldPost(): bool
    {
        return (int) $this->payment->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->payment->payment_date)->toDateString();
    }

    public function description(): string
    {
        return "راتب #{$this->payment->id}";
    }

    public function lines(): array
    {
        $amount = (int) $this->payment->amount;

        return [
            Line::debit(AccountCode::SALARIES->value, $amount),
            Line::credit(AccountCode::CASH->value, $amount),
        ];
    }
}
```

Create `app/Ledger/Postings/MaterialPurchasePosting.php`:

```php
<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\MaterialPurchase;
use Illuminate\Support\Carbon;

/** A material purchase: money leaves the cash box as a materials expense. */
final class MaterialPurchasePosting implements Posting
{
    public function __construct(private readonly MaterialPurchase $purchase) {}

    public function shouldPost(): bool
    {
        return (int) $this->purchase->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->purchase->purchase_date)->toDateString();
    }

    public function description(): string
    {
        return "مواد: {$this->purchase->name}";
    }

    public function lines(): array
    {
        $amount = (int) $this->purchase->amount;

        return [
            Line::debit(AccountCode::MATERIALS->value, $amount),
            Line::credit(AccountCode::CASH->value, $amount),
        ];
    }
}
```

Create `app/Ledger/Postings/ExpensePosting.php`:

```php
<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\Account;
use App\Models\Expense;
use Illuminate\Support\Carbon;

/**
 * A general expense. The debit account is resolved from the account whose
 * `category_key` matches, so adding a category means inserting an account
 * row — no code change here.
 */
final class ExpensePosting implements Posting
{
    /** Account code used when a category has no matching account. */
    private const FALLBACK = '5290'; // أخرى

    public function __construct(private readonly Expense $expense) {}

    public function shouldPost(): bool
    {
        return (int) $this->expense->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->expense->expense_date)->toDateString();
    }

    public function description(): string
    {
        return $this->expense->description ?: 'مصروف عام';
    }

    public function lines(): array
    {
        $amount = (int) $this->expense->amount;

        return [
            Line::debit($this->accountCode(), $amount),
            Line::credit(AccountCode::CASH->value, $amount),
        ];
    }

    private function accountCode(): string
    {
        return Account::expenseCategories()
            ->firstWhere('category_key', $this->expense->category)
            ?->code ?? self::FALLBACK;
    }
}
```

- [ ] **Step 4: Register all three**

Add to `Ledger::POSTINGS`:

```php
        \App\Models\EmployeePayment::class => \App\Ledger\Postings\EmployeePaymentPosting::class,
        \App\Models\MaterialPurchase::class => \App\Ledger\Postings\MaterialPurchasePosting::class,
        \App\Models\Expense::class => \App\Ledger\Postings\ExpensePosting::class,
```

Add the observer attribute to `EmployeePayment`, `MaterialPurchase`, and `Expense`, exactly as in Task 3:

```php
use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(LedgerObserver::class)]
```

- [ ] **Step 5: Run test to verify it passes**

```
php artisan test --filter=ExpensePostingTest
```

Expected: PASS, 5 tests.

- [ ] **Step 6: Run the full suite**

Run the `run-checks` skill. Expected: green.

- [ ] **Step 7: Commit**

```bash
git add app/Ledger app/Models tests/Feature/Ledger/ExpensePostingTest.php
git commit -m "feat(ledger): post salaries, materials and general expenses"
```

---

## Task 6: Cascade-delete safety

Deleting a dentist cascades to `orders` and `dentist_payments` **at the database level**, which bypasses Eloquent entirely — so `LedgerObserver::deleted` never fires and their journal entries survive as orphans. Same for deleting an employee. Left unhandled, the trial balance stays balanced but receivables and expenses are wrong forever.

**Files:**
- Modify: `app/Ledger/Ledger.php`, `app/Models/Dentist.php`, `app/Models/Employee.php`
- Create: `app/Observers/CascadeLedgerObserver.php`
- Test: `tests/Feature/Ledger/CascadeDeleteTest.php`

**Interfaces:**
- Consumes: `Ledger::forget()`
- Produces: `Ledger::forgetMany(string $sourceType, array $ids): void`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/CascadeDeleteTest.php`:

```php
<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;

test('deleting a dentist removes the entries of their orders and payments', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 200000,
        'payment_date' => '2026-06-15',
    ]);

    expect(JournalEntry::count())->toBe(2);

    $dentist->delete();

    expect(JournalEntry::count())->toBe(0);
    expect(JournalLine::count())->toBe(0);
});

test('deleting an employee removes the entries of their salary payments', function () {
    $employee = Employee::factory()->create();
    EmployeePayment::create([
        'employee_id' => $employee->id,
        'amount' => 80000,
        'payment_date' => '2026-06-05',
    ]);

    expect(JournalEntry::count())->toBe(1);

    $employee->delete();

    expect(JournalEntry::count())->toBe(0);
});

test('deleting one dentist leaves another dentist entries alone', function () {
    $keep = Dentist::create(['name' => 'د. باقٍ']);
    $drop = Dentist::create(['name' => 'د. محذوف']);

    Order::create(['dentist_id' => $keep->id, 'due_date' => '2026-06-10', 'amount' => 100, 'status' => 'pending']);
    Order::create(['dentist_id' => $drop->id, 'due_date' => '2026-06-10', 'amount' => 200, 'status' => 'pending']);

    $drop->delete();

    expect(JournalEntry::count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=CascadeDelete
```

Expected: FAIL — entry count is 2, not 0 (lines were nulled by `nullOnDelete` but the entries remain).

- [ ] **Step 3: Add forgetMany to the Ledger**

In `app/Ledger/Ledger.php`, add:

```php
    /**
     * Remove entries for many sources of one type at once. Used when a parent
     * row is deleted and the database cascades its children away without
     * Eloquent seeing it.
     *
     * @param  list<int>  $ids
     */
    public function forgetMany(string $sourceType, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        JournalEntry::query()
            ->where('source_type', $sourceType)
            ->whereIn('source_id', $ids)
            ->delete();
    }
```

- [ ] **Step 4: Write the cascade observer**

Create `app/Observers/CascadeLedgerObserver.php`:

```php
<?php

namespace App\Observers;

use App\Ledger\Ledger;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * Deleting a dentist or an employee cascades to their money rows at the
 * database level, which Eloquent never observes — so LedgerObserver::deleted
 * does not fire for the children. This runs on `deleting`, while the children
 * still exist, and clears their entries by hand.
 */
class CascadeLedgerObserver
{
    /** Parent model → [child model class => foreign key]. */
    private const CHILDREN = [
        Dentist::class => [
            Order::class => 'dentist_id',
            DentistPayment::class => 'dentist_id',
        ],
        Employee::class => [
            EmployeePayment::class => 'employee_id',
        ],
    ];

    public function __construct(private readonly Ledger $ledger) {}

    public function deleting(Model $parent): void
    {
        foreach (self::CHILDREN[$parent::class] ?? [] as $child => $foreignKey) {
            $ids = $child::query()
                ->where($foreignKey, $parent->getKey())
                ->pluck('id')
                ->all();

            $this->ledger->forgetMany($child, $ids);
        }
    }
}
```

- [ ] **Step 5: Attach it**

Add above the class declaration in `app/Models/Dentist.php` and `app/Models/Employee.php`:

```php
use App\Observers\CascadeLedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(CascadeLedgerObserver::class)]
```

- [ ] **Step 6: Run test to verify it passes**

```
php artisan test --filter=CascadeDelete
```

Expected: PASS, 3 tests.

- [ ] **Step 7: Commit**

```bash
git add app/Ledger/Ledger.php app/Observers/CascadeLedgerObserver.php app/Models/Dentist.php app/Models/Employee.php tests/Feature/Ledger/CascadeDeleteTest.php
git commit -m "fix(ledger): clear entries when a parent row cascades its children away"
```

---

## Task 7: The `ledger:rebuild` backfill command

**Files:**
- Create: `app/Console/Commands/RebuildLedger.php`
- Test: `tests/Feature/Ledger/RebuildLedgerTest.php`

**Interfaces:**
- Consumes: `Ledger::sync()`, all five postings
- Produces: `php artisan ledger:rebuild [--cash-on-hand=N]`, exit code 0 on a balanced rebuild and 1 when the trial balance is non-zero

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/RebuildLedgerTest.php`:

```php
<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
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

test('rebuild is idempotent', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);

    $this->artisan('ledger:rebuild')->assertSuccessful();
    $this->artisan('ledger:rebuild')->assertSuccessful();

    expect(JournalEntry::count())->toBe(1);
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

test('cash-on-hand posts the difference to owner capital', function () {
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

test('rebuild reports receivables matching the old outstanding formula', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-11', 'amount' => 900000, 'status' => 'cancelled']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 300000, 'payment_date' => '2026-06-15']);

    $this->artisan('ledger:rebuild')
        ->expectsOutputToContain('د. سامي')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=RebuildLedger
```

Expected: FAIL — `Command "ledger:rebuild" is not defined.`

- [ ] **Step 3: Write the command**

Create `app/Console/Commands/RebuildLedger.php`:

```php
<?php

namespace App\Console\Commands;

use App\Ledger\AccountCode;
use App\Ledger\Ledger;
use App\Ledger\Line;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MaterialPurchase;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild every journal entry from the domain tables.
 *
 * This is a command rather than a migration because it has to stay
 * rerunnable: if a posting rule is ever corrected, the fix is a rebuild, not
 * a hand-patch of live rows.
 */
class RebuildLedger extends Command
{
    protected $signature = 'ledger:rebuild
                            {--cash-on-hand= : Real counted cash; the difference is posted to owner capital}';

    protected $description = 'Rebuild the double-entry ledger from the domain tables';

    /** Source models, posted oldest-concept-first for a readable journal. */
    private const SOURCES = [
        Order::class,
        DentistPayment::class,
        EmployeePayment::class,
        MaterialPurchase::class,
        Expense::class,
    ];

    public function handle(Ledger $ledger): int
    {
        $this->warn('Rebuilding the ledger — every existing entry will be replaced.');

        DB::transaction(function () use ($ledger) {
            JournalEntry::query()->delete();

            foreach (self::SOURCES as $model) {
                $count = 0;

                $model::query()->orderBy('id')->chunkById(500, function ($rows) use ($ledger, &$count) {
                    foreach ($rows as $row) {
                        $ledger->sync($row);
                        $count++;
                    }
                });

                $this->line(sprintf('  %-24s %d', class_basename($model), $count));
            }

            $this->postOpeningCapital($ledger);
        });

        return $this->report();
    }

    /**
     * If the operator supplied a counted cash figure, post the gap to owner
     * capital. A negative computed balance is the expected case: it means
     * money entered the business without ever being recorded.
     */
    private function postOpeningCapital(Ledger $ledger): void
    {
        $target = $this->option('cash-on-hand');

        if ($target === null) {
            return;
        }

        $difference = (int) $target - $this->balance(AccountCode::CASH->value);

        if ($difference === 0) {
            return;
        }

        $ledger->post(
            now()->toDateString(),
            'رصيد افتتاحي — رأس المال',
            $difference > 0
                ? [
                    Line::debit(AccountCode::CASH->value, $difference),
                    Line::credit(AccountCode::CAPITAL->value, $difference),
                ]
                : [
                    Line::debit(AccountCode::CAPITAL->value, -$difference),
                    Line::credit(AccountCode::CASH->value, -$difference),
                ],
        );
    }

    /** Print the verification report and fail loudly if the books do not balance. */
    private function report(): int
    {
        $totals = JournalLine::selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();
        $difference = (int) $totals->d - (int) $totals->c;

        $this->newLine();
        $this->line('  الصندوق (cash box):    '.number_format($this->balance(AccountCode::CASH->value)));
        $this->line('  الذمم المدينة (A/R):   '.number_format($this->balance(AccountCode::RECEIVABLE->value)));
        $this->newLine();

        $this->line('  Receivables vs the old outstanding formula:');
        $this->table(
            ['Dentist', 'Ledger A/R', 'Old formula', 'Diff'],
            $this->receivableComparison(),
        );

        if ($difference !== 0) {
            $this->error("Trial balance is OFF by {$difference}. The ledger was not rebuilt correctly.");

            return self::FAILURE;
        }

        $this->info('Trial balance: BALANCED');

        return self::SUCCESS;
    }

    /**
     * Per-dentist comparison of the new A/R balance against the subtraction
     * the outstanding page used before the ledger existed. Any non-zero Diff
     * is a real discrepancy in the source data and must be understood before
     * the rebuild is accepted.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function receivableComparison(): array
    {
        $ledgerBalances = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', AccountCode::RECEIVABLE->value)
            ->whereNotNull('journal_lines.dentist_id')
            ->groupBy('journal_lines.dentist_id')
            ->selectRaw('journal_lines.dentist_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
            ->pluck('balance', 'dentist_id');

        return Dentist::query()
            ->withSum(['orders as orders_total' => fn ($q) => $q->billable()], 'amount')
            ->withSum('payments as payments_total', 'amount')
            ->get()
            ->map(function (Dentist $dentist) use ($ledgerBalances) {
                $old = (int) $dentist->orders_total - (int) $dentist->payments_total;
                $new = (int) ($ledgerBalances[$dentist->id] ?? 0);

                return [
                    $dentist->name,
                    number_format($new),
                    number_format($old),
                    $new === $old ? '—' : number_format($new - $old),
                ];
            })
            ->all();
    }

    private function balance(string $code): int
    {
        return (int) JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', $code)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
            ->value('balance');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```
php artisan test --filter=RebuildLedger
```

Expected: PASS, 5 tests.

- [ ] **Step 5: Lint**

```
composer lint
```

Expected: no style violations, or Pint fixes them; re-run the test to confirm still green.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/RebuildLedger.php tests/Feature/Ledger/RebuildLedgerTest.php
git commit -m "feat(ledger): add rerunnable ledger:rebuild backfill command"
```

---

## Task 8: The LedgerReports query service

Every reporting controller change in Tasks 9–13 depends on this. Nothing else in this task changes user-visible behaviour.

**Files:**
- Create: `app/Ledger/LedgerReports.php`
- Modify: `tests/Feature/Ledger/OrderPostingTest.php` (drop the local `receivable()` helper in favour of the service)
- Test: `tests/Feature/Ledger/LedgerReportsTest.php`

**Interfaces:**
- Consumes: `Account`, `AccountCode`, the three ledger tables
- Produces:
  - `balance(string $code, ?string $asOf = null): int` — signed by account type (assets/expenses debit-positive, everything else credit-positive)
  - `receivablesByDentist(?string $asOf = null): Collection` — `dentist_id => int`
  - `movementBetween(string $debitCode, string $creditCode, string $from, string $to): int`
  - `revenue(string $from, string $to): int`
  - `cashReceipts(string $from, string $to): int`
  - `expenseBreakdown(string $from, string $to): Collection` — `['code', 'name', 'total']`, non-zero only, in `sort_order`
  - `expensesTotal(string $from, string $to): int`
  - `trialBalance(?string $asOf = null): Collection` — `['code', 'name', 'type', 'debit', 'credit']`
  - `accountLines(string $code, ?string $from, ?string $to): Collection`
  - `dentistStatement(int $dentistId, ?string $from, ?string $to): array{opening: int, lines: Collection, closing: int}`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/LedgerReportsTest.php`:

```php
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
});

test('balance respects an as-of date', function () {
    expect($this->reports->balance('1100', '2026-05-31'))->toBe(100000);
});

test('receivables are grouped by dentist', function () {
    expect($this->reports->receivablesByDentist()->all())
        ->toBe([$this->dentist->id => 300000]);
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
});

test('a dentist statement carries an opening balance and a running list', function () {
    $statement = $this->reports->dentistStatement($this->dentist->id, '2026-06-01', '2026-06-30');

    expect($statement['opening'])->toBe(100000);     // the May order
    expect($statement['lines'])->toHaveCount(2);     // June order + June payment
    expect($statement['closing'])->toBe(300000);
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=LedgerReports
```

Expected: FAIL — `Class "App\Ledger\LedgerReports" not found`.

- [ ] **Step 3: Write the service**

Create `app/Ledger/LedgerReports.php`:

```php
<?php

namespace App\Ledger;

use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every read of the ledger. Controllers call this instead of summing the
 * domain tables, so no two reports can compute the same figure differently.
 *
 * Date bounds are inclusive `Y-m-d` strings and filter on
 * `journal_entries.entry_date` — the business date, not created_at.
 */
class LedgerReports
{
    /** Account types whose natural balance is on the debit side. */
    private const DEBIT_NATURED = ['asset', 'expense'];

    public function balance(string $code, ?string $asOf = null): int
    {
        $row = $this->linesForAccount($code)
            ->when($asOf, fn ($q) => $q->where('journal_entries.entry_date', '<=', $asOf))
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->first();

        $net = (int) $row->d - (int) $row->c;

        return in_array(Account::typeFor($code), self::DEBIT_NATURED, true) ? $net : -$net;
    }

    /** @return Collection<int, int> dentist_id => balance */
    public function receivablesByDentist(?string $asOf = null): Collection
    {
        return $this->linesForAccount(AccountCode::RECEIVABLE->value)
            ->whereNotNull('journal_lines.dentist_id')
            ->when($asOf, fn ($q) => $q->where('journal_entries.entry_date', '<=', $asOf))
            ->groupBy('journal_lines.dentist_id')
            ->selectRaw('journal_lines.dentist_id, COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
            ->pluck('balance', 'dentist_id')
            ->map(fn ($value) => (int) $value);
    }

    /**
     * Total of entries in the range that debit one account and credit
     * another. Every entry this app writes has exactly two lines, so this
     * isolates one specific kind of movement — cash in from dentists, say,
     * as opposed to cash in from an owner capital injection.
     */
    public function movementBetween(string $debitCode, string $creditCode, string $from, string $to): int
    {
        return (int) DB::table('journal_lines as dr')
            ->join('journal_entries as e', 'e.id', '=', 'dr.journal_entry_id')
            ->join('journal_lines as cr', 'cr.journal_entry_id', '=', 'e.id')
            ->join('accounts as dra', 'dra.id', '=', 'dr.account_id')
            ->join('accounts as cra', 'cra.id', '=', 'cr.account_id')
            ->where('dra.code', $debitCode)
            ->where('cra.code', $creditCode)
            ->where('dr.debit', '>', 0)
            ->where('cr.credit', '>', 0)
            ->whereBetween('e.entry_date', [$from, $to])
            ->sum('dr.debit');
    }

    /** Work earned in the range — orders, by due date. */
    public function revenue(string $from, string $to): int
    {
        return $this->movementBetween(
            AccountCode::RECEIVABLE->value,
            AccountCode::REVENUE->value,
            $from,
            $to,
        );
    }

    /** Cash actually collected from dentists in the range. */
    public function cashReceipts(string $from, string $to): int
    {
        return $this->movementBetween(
            AccountCode::CASH->value,
            AccountCode::RECEIVABLE->value,
            $from,
            $to,
        );
    }

    /**
     * One row per expense account with movement in the range. Replaces the
     * hardcoded category array the finance page used to carry — a new expense
     * account appears here automatically.
     *
     * @return Collection<int, array{code: string, name: string, total: int}>
     */
    public function expenseBreakdown(string $from, string $to): Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', 'expense')
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->groupBy('accounts.code', 'accounts.name', 'accounts.sort_order')
            ->orderBy('accounts.sort_order')
            ->selectRaw('accounts.code, accounts.name, COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as total')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'total' => (int) $row->total,
            ])
            ->filter(fn (array $row) => $row['total'] !== 0)
            ->values();
    }

    public function expensesTotal(string $from, string $to): int
    {
        return (int) $this->expenseBreakdown($from, $to)->sum('total');
    }

    /**
     * @return Collection<int, array{code: string, name: string, type: string, debit: int, credit: int}>
     */
    public function trialBalance(?string $asOf = null): Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->when($asOf, fn ($q) => $q->where('journal_entries.entry_date', '<=', $asOf))
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type', 'accounts.sort_order')
            ->orderBy('accounts.sort_order')
            ->selectRaw('accounts.code, accounts.name, accounts.type, COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debit' => (int) $row->debit,
                'credit' => (int) $row->credit,
            ])
            ->filter(fn (array $row) => $row['debit'] !== 0 || $row['credit'] !== 0)
            ->values();
    }

    /**
     * Movements on one account, newest first, each carrying its entry's date
     * and description.
     */
    public function accountLines(string $code, ?string $from = null, ?string $to = null): Collection
    {
        return $this->linesForAccount($code)
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('journal_entries.entry_date', '<=', $to))
            ->orderByDesc('journal_entries.entry_date')
            ->orderByDesc('journal_lines.id')
            ->select(
                'journal_lines.id',
                'journal_entries.entry_date',
                'journal_entries.description',
                'journal_lines.debit',
                'journal_lines.credit',
            )
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'date' => $row->entry_date,
                'description' => $row->description,
                'debit' => (int) $row->debit,
                'credit' => (int) $row->credit,
            ]);
    }

    /**
     * A dentist's receivable movements in a period, with the balance carried
     * in from before it and a running balance on each line.
     *
     * @return array{opening: int, lines: Collection, closing: int}
     */
    public function dentistStatement(int $dentistId, ?string $from = null, ?string $to = null): array
    {
        $opening = 0;

        if ($from) {
            $opening = (int) $this->linesForAccount(AccountCode::RECEIVABLE->value)
                ->where('journal_lines.dentist_id', $dentistId)
                ->where('journal_entries.entry_date', '<', $from)
                ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
                ->value('balance');
        }

        $running = $opening;

        $lines = $this->linesForAccount(AccountCode::RECEIVABLE->value)
            ->where('journal_lines.dentist_id', $dentistId)
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('journal_entries.entry_date', '<=', $to))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select(
                'journal_lines.id',
                'journal_entries.entry_date',
                'journal_entries.description',
                'journal_lines.debit',
                'journal_lines.credit',
            )
            ->get()
            ->map(function ($row) use (&$running) {
                $running += (int) $row->debit - (int) $row->credit;

                return [
                    'id' => (int) $row->id,
                    'date' => $row->entry_date,
                    'description' => $row->description,
                    'debit' => (int) $row->debit,
                    'credit' => (int) $row->credit,
                    'balance' => $running,
                ];
            });

        return ['opening' => $opening, 'lines' => $lines, 'closing' => $running];
    }

    private function linesForAccount(string $code): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', $code);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```
php artisan test --filter=LedgerReports
```

Expected: PASS, 8 tests.

- [ ] **Step 5: Replace the temporary helper in the order posting test**

In `tests/Feature/Ledger/OrderPostingTest.php`, delete the local `receivable()` function and replace each call with:

```php
app(\App\Ledger\LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0
```

- [ ] **Step 6: Run the full suite**

Run the `run-checks` skill. Expected: green.

- [ ] **Step 7: Commit**

```bash
git add app/Ledger/LedgerReports.php tests/Feature/Ledger
git commit -m "feat(ledger): add LedgerReports query service"
```

---

## Task 9: Outstanding page reads the ledger

**Files:**
- Modify: `app/Http/Controllers/OutstandingController.php`
- Test: `tests/Feature/OutstandingTest.php` (add a parity test; existing tests must pass unchanged)

**Interfaces:**
- Consumes: `LedgerReports::receivablesByDentist()`
- Produces: no signature changes — the Inertia props (`dentists[].id|name|phone|orders_total|payments_total|outstanding`, `totalOutstanding`) stay exactly as they are so the React page is untouched

- [ ] **Step 1: Write the failing parity test**

Append to `tests/Feature/OutstandingTest.php`:

```php
test('outstanding balances come from the ledger and match the old formula', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. مطابقة']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-01', 'amount' => 700000, 'status' => 'pending']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-02', 'amount' => 300000, 'status' => 'recieved']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-03', 'amount' => 999999, 'status' => 'cancelled']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 250000, 'payment_date' => '2026-06-10']);

    $oldFormula = (int) Order::billable()->where('dentist_id', $dentist->id)->sum('amount')
        - (int) DentistPayment::where('dentist_id', $dentist->id)->sum('amount');

    // Prove the number is read from the ledger, not recomputed: wipe the
    // ledger and the page must report zero.
    $this->get(route('outstanding.index'))
        ->assertInertia(fn ($page) => $page->where('dentists.0.outstanding', $oldFormula));

    \App\Models\JournalEntry::query()->delete();

    $this->get(route('outstanding.index'))
        ->assertInertia(fn ($page) => $page->where('dentists.0.outstanding', 0));
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=OutstandingTest
```

Expected: FAIL on the second assertion — the controller still subtracts domain tables, so wiping the ledger changes nothing.

- [ ] **Step 3: Rewrite the controller**

Replace the body of `app/Http/Controllers/OutstandingController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;

class OutstandingController extends Controller
{
    /**
     * What each dentist owes, read as their balance on the receivables
     * account rather than by subtracting two tables. `orders_total` and
     * `payments_total` remain for display, but the balance itself is the
     * ledger's.
     */
    public function index(LedgerReports $reports)
    {
        $balances = $reports->receivablesByDentist();

        $dentists = Dentist::query()
            ->withSum(['orders as orders_total' => fn ($q) => $q->billable()], 'amount')
            ->withSum('payments as payments_total', 'amount')
            ->get()
            ->map(fn (Dentist $dentist) => [
                'id' => $dentist->id,
                'name' => $dentist->name,
                'phone' => $dentist->phone,
                'orders_total' => (int) $dentist->orders_total,
                'payments_total' => (int) $dentist->payments_total,
                'outstanding' => $balances[$dentist->id] ?? 0,
            ])
            ->sortByDesc('outstanding')
            ->values();

        return inertia('outstanding/index', [
            'dentists' => $dentists,
            'totalOutstanding' => $reports->balance(AccountCode::RECEIVABLE->value),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```
php artisan test --filter=OutstandingTest
```

Expected: PASS — all four tests, including the two that predate the ledger.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OutstandingController.php tests/Feature/OutstandingTest.php
git commit -m "refactor(outstanding): read balances from the ledger"
```

---

## Task 10: Expense categories move to the accounts table

This removes the three-places-to-hand-sync problem called out in the spec: `Expense::CATEGORIES`, `EXPENSE_CATEGORIES` in `types/models.ts`, and the finance bucket array.

**Files:**
- Modify: `app/Models/Expense.php`, `app/Http/Requests/StoreExpenseRequest.php`, `app/Http/Requests/UpdateExpenseRequest.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/models.ts`, `resources/js/pages/expenses/{index,create,edit}.tsx`, `resources/js/pages/report/index.tsx`
- Test: `tests/Feature/ExpenseTest.php`

**Interfaces:**
- Consumes: `Account::expenseCategories()`
- Produces: shared Inertia prop `expenseCategories: Record<string, string>` (category key → Arabic name), available on every page via `usePage()`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/ExpenseTest.php`:

```php
test('expense categories are shared from the accounts table', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $this->get(route('expenses.index'))
        ->assertInertia(fn ($page) => $page
            ->where('expenseCategories.rent', 'إيجار')
            ->where('expenseCategories.transport', 'مواصلات وسفر')
        );
});

test('an expense category must exist as an account', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => 'not_a_category',
        'amount' => 1000,
        'expense_date' => '2026-06-01',
    ])->assertSessionHasErrors('category');

    $this->post(route('expenses.store'), [
        'category' => 'rent',
        'amount' => 1000,
        'expense_date' => '2026-06-01',
    ])->assertSessionHasNoErrors();
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=ExpenseTest
```

Expected: FAIL — `expenseCategories` prop does not exist.

- [ ] **Step 3: Share the categories**

In `app/Http/Middleware/HandleInertiaRequests.php`, inside the array returned by `share()`, add:

```php
            // Expense categories live in the accounts table so the chart of
            // accounts is their single definition. Shared globally because
            // four unrelated pages render them.
            'expenseCategories' => fn () => \App\Models\Account::expenseCategories()
                ->pluck('name', 'category_key'),
```

- [ ] **Step 4: Point validation at the accounts table**

In both `StoreExpenseRequest.php` and `UpdateExpenseRequest.php`, replace the `category` rule and drop the now-unused `use App\Models\Expense;`:

```php
            'category' => ['required', 'string', Rule::exists('accounts', 'category_key')],
```

- [ ] **Step 5: Remove the constant**

Delete the `CATEGORIES` constant and its docblock from `app/Models/Expense.php` (lines 13–26). Then confirm nothing else references it:

```bash
grep -rn "Expense::CATEGORIES" app/ tests/
```

Expected: no output. If `FinanceController` still references it, leave that line alone — Task 11 replaces it.

- [ ] **Step 6: Switch the frontend to the shared prop**

In `resources/js/types/models.ts`, delete the `EXPENSE_CATEGORIES` constant and add a hook in its place:

```ts
import { usePage } from '@inertiajs/react';

/**
 * Expense categories, defined by the accounts table and shared on every
 * Inertia response. Replaces the constant that had to be hand-synced with
 * the PHP side.
 */
export function useExpenseCategories(): Record<string, string> {
    return (
        (usePage().props.expenseCategories as Record<string, string>) ?? {}
    );
}
```

In `resources/js/pages/expenses/create.tsx` and `edit.tsx`, replace the import and add the hook call inside the component, above the returned JSX:

```ts
import { useExpenseCategories } from '@/types';
// …inside the component body:
const EXPENSE_CATEGORIES = useExpenseCategories();
```

Do the same in `resources/js/pages/expenses/index.tsx` and `resources/js/pages/report/index.tsx`. The existing `Object.entries(EXPENSE_CATEGORIES)` and `EXPENSE_CATEGORIES[...]` expressions then work unchanged.

- [ ] **Step 7: Run test and type check**

```
php artisan test --filter=ExpenseTest
npm run types
```

Expected: both PASS.

- [ ] **Step 8: Commit**

```bash
git add app resources/js tests/Feature/ExpenseTest.php
git commit -m "refactor(expenses): define categories in the accounts table"
```

---

## Task 11: Finance page reads the ledger

**Files:**
- Modify: `app/Http/Controllers/FinanceController.php`, `resources/js/pages/finance/index.tsx`
- Test: `tests/Feature/FinanceTest.php`

**Interfaces:**
- Consumes: `LedgerReports::cashReceipts()`, `expenseBreakdown()`, `expensesTotal()`, `revenue()`, `balance()`
- Produces: existing props unchanged (`month`, `income`, `expenses`, `net`, `categories`, `incomeByDentist`, `expensesByEmployee`, `expensesByMaterial`, `expensesByCategory`, `trend`) plus two new ones — `earned: int` (work delivered) and `receivables: int` (A/R balance). `categories` keeps its `['key', 'label', 'total']` shape, with `key` now the account code.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/FinanceTest.php`:

```php
test('the finance page reports cash received, work earned and receivables', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    // 500,000 of work delivered in June, only 200,000 collected.
    \App\Models\Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('income', 200000)       // cash headline
            ->where('expenses', 40000)
            ->where('net', 160000)
            ->where('earned', 500000)       // work delivered
            ->where('receivables', 300000)  // still owed
        );
});

test('expense categories on the finance page come from accounts with movement', function () {
    $this->actingAs(User::factory()->create());

    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);
    MaterialPurchase::create(['name' => 'خزف', 'amount' => 25000, 'purchase_date' => '2026-06-02']);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertInertia(function ($page) {
            $categories = collect($page->toArray()['props']['categories']);

            // Only accounts with actual movement appear — no empty buckets.
            expect($categories->pluck('total', 'label')->all())->toBe([
                'المواد' => 25000,
                'إيجار' => 40000,
            ]);
        });
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=FinanceTest
```

Expected: FAIL — `earned` prop missing.

- [ ] **Step 3: Rewrite the controller**

Replace `app/Http/Controllers/FinanceController.php` entirely:

```php
<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesMonth;
use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    use ResolvesMonth;

    public function __construct(private readonly LedgerReports $reports) {}

    /**
     * The monthly financial summary. The headline is cash: money actually
     * collected from dentists less money actually paid out. Work earned and
     * receivables sit beside it, because a strong month of work is not the
     * same as money in hand.
     *
     * Expense rows are whatever expense accounts moved this month — there is
     * no hardcoded bucket list. Adding an account adds a row.
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));
        [$start, $end] = $this->range($month);

        $income = $this->reports->cashReceipts($start, $end);
        $categories = $this->reports->expenseBreakdown($start, $end)
            ->map(fn (array $row) => [
                'key' => $row['code'],
                'label' => $row['name'],
                'total' => $row['total'],
            ]);
        $expenses = (int) $categories->sum('total');

        return inertia('finance/index', [
            'month' => $month->format('Y-m'),
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses,
            'earned' => $this->reports->revenue($start, $end),
            'receivables' => $this->reports->balance(AccountCode::RECEIVABLE->value, $end),
            'cashBalance' => $this->reports->balance(AccountCode::CASH->value, $end),
            'categories' => $categories->values(),
            'incomeByDentist' => $this->incomeByDentist($start, $end),
            'expensesByEmployee' => $this->expensesByEmployee($start, $end),
            'expensesByMaterial' => $this->expensesByMaterial($start, $end),
            'expensesByCategory' => $categories
                ->map(fn (array $row) => ['name' => $row['label'], 'total' => $row['total']])
                ->values(),
            'trend' => $this->trend($month),
        ]);
    }

    /**
     * Money collected per dentist. Read from receivable lines rather than the
     * payments table so it cannot disagree with the headline figure.
     */
    private function incomeByDentist(string $start, string $end): \Illuminate\Support\Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('dentists', 'dentists.id', '=', 'journal_lines.dentist_id')
            ->where('accounts.code', AccountCode::RECEIVABLE->value)
            ->where('journal_lines.credit', '>', 0)
            ->whereBetween('journal_entries.entry_date', [$start, $end])
            ->groupBy('dentists.id', 'dentists.name')
            ->orderByDesc('total')
            ->selectRaw('dentists.name, SUM(journal_lines.credit) as total')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    /**
     * Salary detail still comes from the domain table: the ledger records the
     * money but not which employee it went to.
     */
    private function expensesByEmployee(string $start, string $end): \Illuminate\Support\Collection
    {
        return DB::table('employee_payments')
            ->join('employees', 'employees.id', '=', 'employee_payments.employee_id')
            ->whereBetween('employee_payments.payment_date', [$start, $end])
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('total')
            ->select('employees.name', DB::raw('SUM(employee_payments.amount) as total'))
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    /** Material detail, likewise, for the material name. */
    private function expensesByMaterial(string $start, string $end): \Illuminate\Support\Collection
    {
        return DB::table('material_purchases')
            ->whereBetween('purchase_date', [$start, $end])
            ->groupBy('name')
            ->orderByDesc('total')
            ->select('name', DB::raw('SUM(amount) as total'))
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    /**
     * Last 6 months of cash in / cash out / net, oldest first.
     *
     * Six pairs of small aggregate queries rather than one grouped query,
     * because grouping by month needs a driver-specific date function and
     * this must run on both SQLite and MySQL.
     */
    private function trend(Carbon $month): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $bucket = $month->subMonths($i);
            [$start, $end] = $this->range($bucket);

            $income = $this->reports->cashReceipts($start, $end);
            $expenses = $this->reports->expensesTotal($start, $end);

            $trend[] = [
                'month' => $bucket->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
            ];
        }

        return $trend;
    }

    /** @return array{0: string, 1: string} */
    private function range(Carbon $month): array
    {
        return [
            $month->startOfMonth()->toDateString(),
            $month->endOfMonth()->toDateString(),
        ];
    }
}
```

Note: `Date::use(CarbonImmutable::class)` means `subMonths` and `startOfMonth` return new instances — no `copy()` needed and no mutation of `$month`.

- [ ] **Step 4: Surface the two new figures on the page**

In `resources/js/pages/finance/index.tsx`, add `earned`, `receivables`, and `cashBalance` to the props type as `number`, and render them as a secondary row beneath the existing headline cards, following the card markup already in that file:

```tsx
<Card>
    <CardContent className="flex items-center justify-between p-5">
        <span className="text-sm text-muted-foreground">الأعمال المنجزة</span>
        <span className="text-xl font-semibold tabular-nums">{nf(earned)}</span>
    </CardContent>
</Card>
<Card>
    <CardContent className="flex items-center justify-between p-5">
        <span className="text-sm text-muted-foreground">الذمم المدينة</span>
        <span className="text-xl font-semibold tabular-nums">{nf(receivables)}</span>
    </CardContent>
</Card>
<Card>
    <CardContent className="flex items-center justify-between p-5">
        <span className="text-sm text-muted-foreground">رصيد الصندوق</span>
        <span className="text-xl font-semibold tabular-nums">{nf(cashBalance)}</span>
    </CardContent>
</Card>
```

- [ ] **Step 5: Run tests and type check**

```
php artisan test --filter=FinanceTest
npm run types
```

Expected: PASS. Pre-existing finance tests assert `income`, `expenses`, `net` and the category breakdown — they must still pass, because cash receipts equal the payments total and expense accounts sum to the same three buckets.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FinanceController.php resources/js/pages/finance/index.tsx tests/Feature/FinanceTest.php
git commit -m "refactor(finance): read income and expenses from the ledger"
```

---

## Task 12: Dashboard and report totals read the ledger

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/ReportController.php`
- Test: `tests/Feature/DashboardTest.php`, `tests/Feature/ReportTest.php`

**Interfaces:**
- Consumes: `LedgerReports`
- Produces: `DashboardController` gains `stats.cash_balance` and `stats.earned`; `ReportController` gains `totals.earned`. All existing props keep their names and meanings.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/DashboardTest.php`:

```php
test('the dashboard reports the cash box balance from the ledger', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $dentist = \App\Models\Dentist::create(['name' => 'د. سامي']);
    \App\Models\Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => now()->toDateString(),
        'amount' => 500000,
        'status' => 'pending',
    ]);
    \App\Models\DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 200000,
        'payment_date' => now()->toDateString(),
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.cash_balance', 200000)
            ->where('stats.outstanding', 300000)
            ->where('stats.earned', 500000)
        );
});
```

Append to `tests/Feature/ReportTest.php`:

```php
test('report totals come from the ledger', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $dentist = \App\Models\Dentist::create(['name' => 'د. سامي']);
    \App\Models\Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    \App\Models\DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
    \App\Models\Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-05']);

    $this->get(route('report.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('totals.income', 200000)
            ->where('totals.expenses', 40000)
            ->where('totals.net', 160000)
            ->where('totals.earned', 500000)
            ->where('totals.orders_count', 1)
        );
});
```

- [ ] **Step 2: Run tests to verify they fail**

```
php artisan test --filter="DashboardTest|ReportTest"
```

Expected: FAIL — `stats.cash_balance` and `totals.earned` do not exist.

- [ ] **Step 3: Rewrite the dashboard controller**

Replace the `index` method of `app/Http/Controllers/DashboardController.php`:

```php
    public function index(LedgerReports $reports)
    {
        $now = Carbon::now();
        $start = $now->startOfMonth()->toDateString();
        $end = $now->endOfMonth()->toDateString();

        $income = $reports->cashReceipts($start, $end);
        $breakdown = $reports->expenseBreakdown($start, $end);
        $expenses = (int) $breakdown->sum('total');

        return inertia('dashboard', [
            'stats' => [
                'month' => $now->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
                'salaries' => (int) ($breakdown->firstWhere('code', AccountCode::SALARIES->value)['total'] ?? 0),
                'materials' => (int) ($breakdown->firstWhere('code', AccountCode::MATERIALS->value)['total'] ?? 0),
                // Everything that is neither salaries nor materials.
                'general_expenses' => (int) $breakdown
                    ->reject(fn (array $row) => in_array($row['code'], [
                        AccountCode::SALARIES->value,
                        AccountCode::MATERIALS->value,
                    ], true))
                    ->sum('total'),
                'earned' => $reports->revenue($start, $end),
                'outstanding' => $reports->balance(AccountCode::RECEIVABLE->value),
                'cash_balance' => $reports->balance(AccountCode::CASH->value),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'dentists' => Dentist::count(),
                'employees' => Employee::count(),
            ],
            'recentOrders' => Order::with(['dentist', 'items'])->latest()->take(5)->get(),
            'recentPayments' => DentistPayment::with('dentist')->latest()->take(5)->get(),
        ]);
    }
```

Update the imports at the top of the file: add `use App\Ledger\AccountCode;` and `use App\Ledger\LedgerReports;`, and remove `EmployeePayment`, `Expense`, and `MaterialPurchase` if Pint reports them unused.

Add `cash_balance: number;` and `earned: number;` to the stats type in `resources/js/pages/dashboard.tsx`, and render the cash balance as a stat card matching the existing ones.

- [ ] **Step 4: Rewrite the report controller totals**

In `app/Http/Controllers/ReportController.php`, inject the service and replace the totals block. The five detail queries above it stay exactly as they are — they feed the on-screen lists, which need names and notes the ledger does not carry.

```php
    public function index(Request $request, LedgerReports $reports)
    {
        [$from, $to] = $this->resolveRange($request->query('from'), $request->query('to'));

        // …the five existing detail queries stay unchanged…

        // Totals come from the ledger so they cannot disagree with the
        // finance page; the lists above stay on the domain tables because
        // only they carry employee names, material names and notes.
        $income = $reports->cashReceipts($from, $to);
        $breakdown = $reports->expenseBreakdown($from, $to);
        $outgoing = (int) $breakdown->sum('total');

        return inertia('report/index', [
            'orders' => $orders,
            'payments' => $payments,
            'salaries' => $salaries,
            'materials' => $materials,
            'expenses' => $expenses,
            'totals' => [
                'income' => $income,
                'expenses' => $outgoing,
                'net' => $income - $outgoing,
                'earned' => $reports->revenue($from, $to),
                'orders_value' => (int) $orders->sum('amount'),
                'orders_count' => $orders->count(),
                'salaries' => (int) ($breakdown->firstWhere('code', AccountCode::SALARIES->value)['total'] ?? 0),
                'materials' => (int) ($breakdown->firstWhere('code', AccountCode::MATERIALS->value)['total'] ?? 0),
                'general_expenses' => (int) $breakdown
                    ->reject(fn (array $row) => in_array($row['code'], [
                        AccountCode::SALARIES->value,
                        AccountCode::MATERIALS->value,
                    ], true))
                    ->sum('total'),
            ],
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }
```

Add `earned: number;` to the totals type in `resources/js/pages/report/index.tsx`.

- [ ] **Step 5: Run tests and type check**

```
php artisan test --filter="DashboardTest|ReportTest"
npm run types
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers resources/js/pages tests/Feature
git commit -m "refactor(reports): read dashboard and report totals from the ledger"
```

---

## Task 13: Invoice opening balances read the ledger

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php`
- Test: `tests/Feature/InvoiceTest.php`

**Interfaces:**
- Consumes: `LedgerReports::receivablesByDentist(?string $asOf)`
- Produces: no prop changes — `openingByDentist` and `totals.opening` keep their shapes

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/InvoiceTest.php`:

```php
test('invoice opening balances are the ledger receivable before the period', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $dentist = \App\Models\Dentist::create(['name' => 'د. سامي']);
    // Before the period: 300,000 billed, 100,000 paid → 200,000 carried in.
    \App\Models\Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-10', 'amount' => 300000, 'status' => 'pending']);
    \App\Models\DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 100000, 'payment_date' => '2026-05-20']);
    // In the period.
    \App\Models\Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('totals.opening', 200000)
            ->where('openingByDentist.'.$dentist->id, 200000)
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=InvoiceTest
```

Expected: PASS on the value but FAIL to prove it comes from the ledger. If it passes, temporarily wipe `JournalEntry` in the test before the request to confirm; the assertion must then be 0. Keep that wipe in the final test, as in Task 9.

- [ ] **Step 3: Replace the opening-balance calculation**

In `app/Http/Controllers/InvoiceController.php`, replace the `$priorOrders` / `$priorPayments` block and the loop that follows it with:

```php
            // Opening balance: what each dentist owed the day before this
            // period began, read as their receivable balance as of that date.
            // This supersedes the old two-query subtraction, which had to
            // reproduce the same date asymmetry by hand.
            $asOf = Carbon::parse($from)->subDay()->toDateString();

            $openingByDentist = $this->reports->receivablesByDentist($asOf)
                ->when($dentistId, fn ($balances) => $balances->only([$dentistId]))
                ->reject(fn (int $balance) => $balance === 0)
                ->all();

            $openingTotal = array_sum($openingByDentist);
```

Inject the service by adding a constructor to the class:

```php
    public function __construct(private readonly LedgerReports $reports) {}
```

and `use App\Ledger\LedgerReports;` to the imports. Keep the explanatory comment above the block about the intentional date asymmetry — it still describes why orders and payments can land in different periods.

- [ ] **Step 4: Run tests**

```
php artisan test --filter=InvoiceTest
```

Expected: PASS, including the pre-existing invoice tests.

- [ ] **Step 5: Run the full suite**

Run the `run-checks` skill. Expected: green. Every money figure in the app now comes from the ledger.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/InvoiceTest.php
git commit -m "refactor(invoices): read opening balances from the ledger"
```

---

## Task 14: Trial balance and cash box pages

**Files:**
- Create: `app/Http/Controllers/LedgerController.php`, `resources/js/pages/ledger/trial-balance.tsx`, `resources/js/pages/ledger/cash.tsx`
- Modify: `routes/web.php`, `resources/js/components/app-sidebar.tsx`
- Test: `tests/Feature/Ledger/LedgerPagesTest.php`

**Interfaces:**
- Consumes: `LedgerReports::trialBalance()`, `balance()`, `accountLines()`
- Produces: routes `ledger.trial-balance` and `ledger.cash`; `LedgerController` gains `journal()` and `statement()` in Tasks 15–16

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/LedgerPagesTest.php`:

```php
<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);
});

test('the trial balance page lists accounts and balances', function () {
    $this->get(route('ledger.trial-balance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/trial-balance')
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
            ->has('lines', 2)
        );
});

test('guests cannot reach the ledger pages', function () {
    auth()->logout();

    $this->get(route('ledger.trial-balance'))->assertRedirect(route('login'));
    $this->get(route('ledger.cash'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=LedgerPages
```

Expected: FAIL — route `ledger.trial-balance` not defined.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/LedgerController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use Illuminate\Http\Request;

/**
 * The read-only accounting views. Nothing here writes to the ledger — every
 * entry originates from a domain record.
 */
class LedgerController extends Controller
{
    public function __construct(private readonly LedgerReports $reports) {}

    /** ميزان المراجعة — proof that the books balance. */
    public function trialBalance(Request $request)
    {
        $asOf = $this->parseDate($request->query('as_of'));
        $accounts = $this->reports->trialBalance($asOf);

        $debit = (int) $accounts->sum('debit');
        $credit = (int) $accounts->sum('credit');

        return inertia('ledger/trial-balance', [
            'accounts' => $accounts,
            'totals' => ['debit' => $debit, 'credit' => $credit],
            'balanced' => $debit === $credit,
            'filters' => ['as_of' => $asOf],
        ]);
    }

    /** الصندوق — cash balance and every movement through it. */
    public function cash(Request $request)
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        return inertia('ledger/cash', [
            'balance' => $this->reports->balance(AccountCode::CASH->value, $to),
            'lines' => $this->reports->accountLines(AccountCode::CASH->value, $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    /** Accept only well-formed Y-m-d; anything else collapses to no filter. */
    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/web.php`, inside the `auth`/`verified` group, after the `report` route:

```php
    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('trial-balance', [App\Http\Controllers\LedgerController::class, 'trialBalance'])->name('trial-balance');
        Route::get('cash', [App\Http\Controllers\LedgerController::class, 'cash'])->name('cash');
    });
```

- [ ] **Step 5: Write the trial balance page**

Create `resources/js/pages/ledger/trial-balance.tsx`, following the structure of `resources/js/pages/outstanding/index.tsx` (same imports, same `AppLayout` + `Head` + heading + `Card` + `Table` shape):

```tsx
import { Head } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ميزان المراجعة', href: '/ledger/trial-balance' },
];

type AccountRow = {
    code: string;
    name: string;
    type: string;
    debit: number;
    credit: number;
};

type Props = {
    accounts: AccountRow[];
    totals: { debit: number; credit: number };
    balanced: boolean;
};

const nf = (value: number) => value.toLocaleString('en-US');

export default function TrialBalance({ accounts, totals, balanced }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ميزان المراجعة" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        ميزان المراجعة
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        مجموع المدين يساوي مجموع الدائن في الدفاتر السليمة
                    </p>
                </div>

                <Card>
                    <CardContent className="flex items-center justify-between p-5">
                        <span className="text-sm text-muted-foreground">
                            حالة الدفاتر
                        </span>
                        <span
                            className={
                                balanced
                                    ? 'text-lg font-bold text-[#047857]'
                                    : 'text-lg font-bold text-[#BE123C]'
                            }
                        >
                            {balanced ? 'متوازنة' : 'غير متوازنة'}
                        </span>
                    </CardContent>
                </Card>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>الرمز</TableHead>
                                <TableHead>الحساب</TableHead>
                                <TableHead className="text-left">مدين</TableHead>
                                <TableHead className="text-left">دائن</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {accounts.map((account) => (
                                <TableRow key={account.code}>
                                    <TableCell className="tabular-nums text-muted-foreground">
                                        {account.code}
                                    </TableCell>
                                    <TableCell>{account.name}</TableCell>
                                    <TableCell className="text-left tabular-nums">
                                        {nf(account.debit)}
                                    </TableCell>
                                    <TableCell className="text-left tabular-nums">
                                        {nf(account.credit)}
                                    </TableCell>
                                </TableRow>
                            ))}
                            <TableRow className="font-bold">
                                <TableCell colSpan={2}>الإجمالي</TableCell>
                                <TableCell className="text-left tabular-nums">
                                    {nf(totals.debit)}
                                </TableCell>
                                <TableCell className="text-left tabular-nums">
                                    {nf(totals.credit)}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 6: Write the cash page**

Create `resources/js/pages/ledger/cash.tsx` with the same skeleton: a heading `الصندوق`, one `Card` showing `balance`, and a table of `lines` with columns `التاريخ`, `البيان`, `وارد` (`debit`), `صادر` (`credit`). The line type is:

```tsx
type CashLine = {
    id: number;
    date: string;
    description: string;
    debit: number;
    credit: number;
};
```

- [ ] **Step 7: Add both to the sidebar**

In `resources/js/components/app-sidebar.tsx`, add to `mainNavItems` after the التقرير entry, and add `Scale` and `Banknote` to the `lucide-react` import:

```tsx
    {
        title: 'الصندوق',
        href: '/ledger/cash',
        icon: Banknote,
    },
    {
        title: 'ميزان المراجعة',
        href: '/ledger/trial-balance',
        icon: Scale,
    },
```

- [ ] **Step 8: Run tests and type check**

```
php artisan test --filter=LedgerPages
npm run types
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/LedgerController.php resources/js routes/web.php tests/Feature/Ledger/LedgerPagesTest.php
git commit -m "feat(ledger): add trial balance and cash box pages"
```

---

## Task 15: Journal browser page

**Files:**
- Modify: `app/Http/Controllers/LedgerController.php`, `routes/web.php`, `resources/js/components/app-sidebar.tsx`
- Create: `resources/js/pages/ledger/journal.tsx`
- Test: `tests/Feature/Ledger/JournalPageTest.php`

**Interfaces:**
- Consumes: `JournalEntry` with `lines.account` and `lines.dentist` eager-loaded
- Produces: route `ledger.journal`, paginated 50 per page, filters `from`, `to`, `account`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/JournalPageTest.php`:

```php
<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;

test('the journal lists entries with both sides', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);

    $this->get(route('ledger.journal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/journal')
            ->has('entries.data', 2)
            ->has('entries.data.0.lines', 2)
        );
});

test('the journal filters by account and date', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-10', 'amount' => 100000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);

    // Only the cash account moved in June.
    $this->get(route('ledger.journal', ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));

    $this->get(route('ledger.journal', ['from' => '2026-05-01', 'to' => '2026-05-31']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));
});

test('guests cannot reach the journal', function () {
    $this->get(route('ledger.journal'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=JournalPage
```

Expected: FAIL — route not defined.

- [ ] **Step 3: Add the controller method**

Add to `app/Http/Controllers/LedgerController.php`:

```php
    /** قيود اليومية — every entry, for tracing a figure back to its source. */
    public function journal(Request $request)
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));
        $account = $request->query('account');

        $entries = \App\Models\JournalEntry::query()
            ->with(['lines.account', 'lines.dentist'])
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->when($account, fn ($q) => $q->whereHas(
                'lines.account',
                fn ($a) => $a->where('code', $account),
            ))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return inertia('ledger/journal', [
            'entries' => $entries,
            'accounts' => \App\Models\Account::orderBy('sort_order')->get(['code', 'name']),
            'filters' => ['from' => $from, 'to' => $to, 'account' => $account],
        ]);
    }
```

- [ ] **Step 4: Register the route**

Inside the existing `ledger` route group:

```php
        Route::get('journal', [App\Http\Controllers\LedgerController::class, 'journal'])->name('journal');
```

- [ ] **Step 5: Write the page**

Create `resources/js/pages/ledger/journal.tsx`, same skeleton as the trial balance page. Render one table where each entry contributes a header row (`entry_date`, `description`) followed by its line rows (account name, dentist name when present, debit, credit). Types:

```tsx
type JournalLine = {
    id: number;
    debit: number;
    credit: number;
    account: { code: string; name: string };
    dentist: { id: number; name: string } | null;
};

type JournalEntry = {
    id: number;
    entry_date: string;
    description: string;
    lines: JournalLine[];
};

type Props = {
    entries: { data: JournalEntry[]; links: { url: string | null; label: string; active: boolean }[] };
    accounts: { code: string; name: string }[];
    filters: { from: string | null; to: string | null; account: string | null };
};
```

Add a filter row above the table: two date inputs and an account `<select>` built from `accounts`, submitting with `router.get(route('ledger.journal'), {...})` — follow the filter pattern already used in `resources/js/pages/report/index.tsx`.

- [ ] **Step 6: Add to the sidebar**

Add after ميزان المراجعة, importing `BookOpen` from `lucide-react`:

```tsx
    {
        title: 'قيود اليومية',
        href: '/ledger/journal',
        icon: BookOpen,
    },
```

- [ ] **Step 7: Run tests and type check**

```
php artisan test --filter=JournalPage
npm run types
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app resources/js routes/web.php tests/Feature/Ledger/JournalPageTest.php
git commit -m "feat(ledger): add journal browser page"
```

---

## Task 16: Dentist account statement, printable

**Files:**
- Modify: `app/Http/Controllers/LedgerController.php`, `routes/web.php`, `resources/js/components/app-sidebar.tsx`
- Create: `resources/js/pages/ledger/statement.tsx`, `resources/js/pages/ledger/statement-print.tsx`
- Test: `tests/Feature/Ledger/StatementPageTest.php`

**Interfaces:**
- Consumes: `LedgerReports::dentistStatement()`
- Produces: routes `ledger.statement`, `ledger.statement.pdf`, `ledger.statement.print-view` (signed, outside the auth group — mirrors `invoices.print-view`)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ledger/StatementPageTest.php`:

```php
<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-05-10', 'amount' => 100000, 'status' => 'pending']);
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
});

test('the statement carries an opening balance and a running balance', function () {
    $this->get(route('ledger.statement', [
        'dentist_id' => $this->dentist->id,
        'from' => '2026-06-01',
        'to' => '2026-06-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/statement')
            ->where('statement.opening', 100000)
            ->where('statement.closing', 400000)
            ->has('statement.lines', 2)
            ->where('statement.lines.0.balance', 600000)
            ->where('statement.lines.1.balance', 400000)
        );
});

test('the statement page renders with no dentist selected', function () {
    $this->get(route('ledger.statement'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/statement')
            ->where('statement', null)
            ->has('dentists', 1)
        );
});

test('the print view is reachable only through a signed url', function () {
    auth()->logout();

    $this->get(route('ledger.statement.print-view', ['dentist_id' => $this->dentist->id]))
        ->assertForbidden();

    $signed = URL::temporarySignedRoute(
        'ledger.statement.print-view',
        now()->addMinutes(2),
        ['dentist_id' => $this->dentist->id],
        absolute: false,
    );

    $this->get($signed)->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

```
php artisan test --filter=StatementPage
```

Expected: FAIL — route not defined.

- [ ] **Step 3: Add the controller methods**

Add to `app/Http/Controllers/LedgerController.php`:

```php
    /** كشف حساب — one dentist's receivable movements with a running balance. */
    public function statement(Request $request)
    {
        return inertia('ledger/statement', $this->buildStatement($request));
    }

    /**
     * The same statement without app chrome, rendered by headless Chromium.
     * Outside the auth group — the browser has no session — with the signed
     * middleware as the gate, exactly as invoices.print-view works.
     */
    public function statementPrintView(Request $request)
    {
        return inertia('ledger/statement-print', $this->buildStatement($request));
    }

    /** Render the statement as an A4 portrait PDF. */
    public function statementPdf(Request $request)
    {
        $data = $this->buildStatement($request);

        abort_if($data['statement'] === null, 422, 'اختر طبيباً أولاً.');

        $path = URL::temporarySignedRoute(
            'ledger.statement.print-view',
            now()->addMinutes(config('pdf.signed_url_ttl')),
            array_filter($data['filters'], fn ($value) => $value !== null),
            absolute: false,
        );

        return response(
            Browsershot::url(rtrim(config('pdf.internal_url'), '/').$path)
                ->format('A4')
                ->showBackground()
                ->waitUntilNetworkIdle()
                ->pdf(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="statement.pdf"',
            ],
        );
    }

    /** @return array{statement: array|null, dentist: \App\Models\Dentist|null, dentists: \Illuminate\Support\Collection, filters: array} */
    private function buildStatement(Request $request): array
    {
        $dentistId = $request->integer('dentist_id') ?: null;
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        $dentist = $dentistId ? \App\Models\Dentist::find($dentistId) : null;

        return [
            'statement' => $dentist
                ? $this->reports->dentistStatement($dentist->id, $from, $to)
                : null,
            'dentist' => $dentist,
            'dentists' => \App\Models\Dentist::orderBy('name')->get(['id', 'name']),
            'filters' => ['dentist_id' => $dentistId, 'from' => $from, 'to' => $to],
        ];
    }
```

Add `use Illuminate\Support\Facades\URL;` and `use Spatie\Browsershot\Browsershot;` to the imports.

- [ ] **Step 4: Register the routes**

Inside the `ledger` group (authenticated):

```php
        Route::get('statement', [App\Http\Controllers\LedgerController::class, 'statement'])->name('statement');
        Route::get('statement/pdf', [App\Http\Controllers\LedgerController::class, 'statementPdf'])->name('statement.pdf');
```

Outside the auth group, beside the existing `invoices/print-view` route:

```php
Route::get('ledger/statement/print-view', [App\Http\Controllers\LedgerController::class, 'statementPrintView'])
    ->middleware('signed:relative')
    ->name('ledger.statement.print-view');
```

- [ ] **Step 5: Write the pages**

Create `resources/js/pages/ledger/statement.tsx`: a dentist `<select>` plus two date inputs (same filter pattern as the journal page), an opening-balance row, a table of lines with columns `التاريخ`, `البيان`, `مدين`, `دائن`, `الرصيد`, a closing-balance row, and a print button linking to `/ledger/statement/pdf` with the current query string. Types:

```tsx
type StatementLine = {
    id: number;
    date: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
};

type Props = {
    statement: { opening: number; lines: StatementLine[]; closing: number } | null;
    dentist: { id: number; name: string } | null;
    dentists: { id: number; name: string }[];
    filters: { dentist_id: number | null; from: string | null; to: string | null };
};
```

Create `resources/js/pages/ledger/statement-print.tsx`: the same table with no `AppLayout`, no filters and no buttons — a bare `<div dir="rtl">` wrapper. Follow `resources/js/pages/invoices/print.tsx` for the print-page conventions already in use.

- [ ] **Step 6: Add to the sidebar**

Add after قيود اليومية, importing `Receipt` from `lucide-react`:

```tsx
    {
        title: 'كشف حساب',
        href: '/ledger/statement',
        icon: Receipt,
    },
```

- [ ] **Step 7: Run tests and type check**

```
php artisan test --filter=StatementPage
npm run types
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app resources/js routes/web.php tests/Feature/Ledger/StatementPageTest.php
git commit -m "feat(ledger): add printable dentist account statement"
```

---

## Task 17: Documentation and the full quality gate

**Files:**
- Modify: `CLAUDE.md`
- Test: the whole suite

- [ ] **Step 1: Update CLAUDE.md**

In the **Domain Model** section, add after the `MaterialPurchase` entry:

```markdown
**Account / JournalEntry / JournalLine** (`app/Models/`)
- The double-entry ledger. `accounts` is the chart of accounts (seeded by
  migration, codes referenced via `App\Ledger\AccountCode`); `journal_entries`
  holds one entry per money event; `journal_lines` holds its debits and credits.
- **The ledger is the source of truth for every money figure.** Reporting
  controllers read `App\Ledger\LedgerReports`, never `SUM(amount)` on a domain
  table. Detail lists still come from the domain tables — they carry the names
  and notes the ledger does not.
- Entries are written automatically by `LedgerObserver` on the five money
  models. The ledger mirrors current state: editing a record rewrites its
  entry, cancelling or deleting removes it.
- `App\Ledger\Ledger::post()` throws `UnbalancedEntryException` if debits do
  not equal credits. Never bypass it.
- Expense categories are `accounts` rows carrying a `category_key`, shared to
  the frontend as the `expenseCategories` Inertia prop. Adding a category is
  one row — do not reintroduce a PHP constant or a TS constant for them.
- Rebuild all entries from the domain tables with `php artisan ledger:rebuild`
  (rerunnable; `--cash-on-hand=N` posts the gap to owner capital).
```

In the **Reporting layer** section, replace the `Order::billable()` paragraph's last sentence with:

```markdown
`Order::billable()` still scopes which orders post to the ledger (cancelled
ones do not), but money totals come from `LedgerReports`, not from summing
orders directly.
```

- [ ] **Step 2: Run the full quality gate**

Run the `run-checks` skill (Pest + Pint + `npm run types`). Every check must pass. Do not proceed on a failure — investigate it.

- [ ] **Step 3: Verify the rebuild on a realistic dataset**

```
php artisan migrate:fresh --seed
php artisan ledger:rebuild
```

Expected: the trial balance reports BALANCED, and every row of the receivable comparison shows `—` in the Diff column. Any non-zero diff is a real discrepancy and must be understood before this ships.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: describe the double-entry ledger in CLAUDE.md"
```

- [ ] **Step 5: Hand off the deployment**

Use the `deploy` skill to prepare the release. The VPS commands are run by the user. The production sequence is:

1. Fresh database backup (`backups` skill) — do not rely on the nightly Drive backup
2. Screenshot the outstanding and finance pages for before/after comparison
3. Rebuild the image and deploy (a code change requires an image rebuild, never a restart)
4. `docker compose exec app php artisan migrate`
5. `docker compose exec app php artisan ledger:rebuild` — read the verification report
6. Re-run with `--cash-on-hand=N` once the real cash figure is known
7. Compare the outstanding and finance pages against the screenshots

---

## Self-Review

**Spec coverage:**

| Spec requirement | Task |
|---|---|
| Three ledger tables | 1 |
| Chart of accounts incl. 5900 seeded | 1 |
| A/R subsidiary via `dentist_id` | 1, 8 |
| `Ledger::post()` refuses unbalanced entries | 2 |
| One posting rule per source model | 3, 4, 5 |
| Order posts on creation, not delivery | 3 |
| Ledger mirrors current state | 3 (edit/cancel/un-cancel/delete tests) |
| Posting via observers | 3 |
| `ledger:rebuild`, rerunnable, verification report | 7 |
| `--cash-on-hand` → owner capital | 7 |
| Expense categories replace `Expense::CATEGORIES` | 10 |
| `OutstandingController` on the ledger | 9 |
| `FinanceController` account-driven, cash headline, earned + A/R lines | 11 |
| `DashboardController`, `ReportController` totals | 12 |
| `InvoiceController` opening balance | 13 |
| Detail lists stay on domain tables | 11, 12 |
| Four new pages | 14, 15, 16 |
| Statement printable via signed Chromium URL | 16 |
| Parity tests | 9, 11, 12, 13 |
| Trial balance zero after any sequence | 5, 7, 14 |
| Rollout sequence | 17 |
| Write-offs deferred | not implemented — matches the spec's non-goals |

**Gap found and closed:** the spec did not mention database-level cascade deletes, which silently bypass the observer and orphan entries. Task 6 was added for it.

**Type consistency:** `LedgerReports` method names used in Tasks 9–16 match their Task 8 definitions (`balance`, `receivablesByDentist`, `cashReceipts`, `revenue`, `expenseBreakdown`, `expensesTotal`, `trialBalance`, `accountLines`, `dentistStatement`). `expenseBreakdown` returns `['code', 'name', 'total']` throughout; `FinanceController` maps it to `['key', 'label', 'total']` for the existing prop shape, and Tasks 12 reads `['total']` off `firstWhere('code', …)`. `Line::debit`/`Line::credit` take `(string $code, int $amount, ?int $dentistId)` in every posting rule.

**Placeholder scan:** none. The one deliberate omission is the exact JSX body of `cash.tsx`, `journal.tsx`, `statement.tsx` and `statement-print.tsx` — each names its prop types, columns, and the existing page to copy the skeleton from, which is the same level of direction the codebase's own pages provide.
