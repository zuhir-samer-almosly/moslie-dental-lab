# Dollar-Only Dentist Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let one dentist be billed, paid and reported entirely in US dollars, with a balance that is exact dollar arithmetic and never passes through an exchange rate.

**Architecture:** Parallel USD-denominated accounts inside the existing double-entry ledger. `accounts` gains a `currency`; three new accounts (`1001` صندوق الدولار, `1101` ذمم الأطباء بالدولار, `4001` إيرادات بالدولار) hold cents. One account holds one currency and no entry ever crosses currencies, so `Ledger::post()`'s balance check works unchanged and every existing lira report — which looks accounts up by **code** — keeps returning today's answers untouched.

**Tech Stack:** Laravel 12, Pest 4, Inertia 2 + React 19 + TypeScript, Tailwind 4. Arabic RTL UI.

**Spec:** `docs/superpowers/specs/2026-08-29-dollar-only-dentist-design.md`

## Global Constraints

- **Unit rule:** a SYP account holds **whole lira**; a USD account holds **cents**. Never mixed in one account, never mixed in one entry.
- **The three money states** a row can be in:

  | State | `currency` | `original_amount` | `rate` | lira column (`amount`/`price`) |
  |---|---|---|---|---|
  | Lira | `SYP` | NULL | NULL | the lira amount |
  | Dollars converted (existing, unchanged) | `USD` | cents | set | converted lira |
  | Native dollars (new) | `USD` | cents | **NULL** | **0** |

- **Authority:** whether a row is native dollars is decided by its **dentist's `currency`**, never by inspecting the row alone.
- **No blended totals.** No report adds a lira figure to a dollar figure, ever.
- **Non-goals — do not implement:** spending dollars, selling dollars for lira, FX gain/loss, converting an existing dentist with history.
- **UI is Arabic/RTL.** New labels are Arabic. Force `dir="ltr"` on any element rendering `$` next to a number (see `foreign-origin.tsx` for why).
- **Checks** (from the `run-checks` skill — the naive commands fail here):

  ```bash
  # Pest — on the HOST, with storage/cache redirected
  S=$(mktemp -d)
  mkdir -p "$S/storage/framework/views" "$S/storage/framework/cache/data" \
           "$S/storage/framework/sessions" "$S/storage/logs" "$S/bootcache"
  LARAVEL_STORAGE_PATH="$S/storage" \
  APP_PACKAGES_CACHE="$S/bootcache/packages.php" \
  APP_SERVICES_CACHE="$S/bootcache/services.php" \
  APP_CONFIG_CACHE="$S/bootcache/config.php" \
  APP_EVENTS_CACHE="$S/bootcache/events.php" \
  APP_ROUTES_CACHE="$S/bootcache/routes-v7.php" \
  SESSION_DRIVER=array CACHE_STORE=array LOG_CHANNEL=single \
  vendor/bin/pest --filter="DollarDentist"

  vendor/bin/pint --test                      # PHP style, on the host
  docker exec moslie-vite-local npm run types # TypeScript, in the vite container
  ```

  Shorthand used below: **"Run Pest"** means the full host block above with an appropriate `--filter`.

---

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `app/Money/Currency.php` | The `SYP`/`USD` enum and its minor-unit knowledge |
| `app/Ledger/MixedCurrencyEntryException.php` | Thrown when an entry crosses currencies |
| `database/migrations/2026_08_29_120000_add_currency_to_accounts_table.php` | `accounts.currency` + the three USD accounts |
| `database/migrations/2026_08_29_120001_add_currency_to_dentists_table.php` | `dentists.currency` |
| `database/migrations/2026_08_29_120002_add_currency_to_orders_table.php` | `orders.currency`, `orders.original_amount` |
| `tests/Feature/Money/DollarDentistTest.php` | The whole feature's behaviour |
| `tests/Feature/Money/CurrencyIsolationTest.php` | Proof the lira side is unaffected |

**Modified:** `app/Ledger/AccountCode.php`, `Ledger.php`, `LedgerReports.php`, `Postings/OrderPosting.php`, `Postings/DentistPaymentPosting.php`, `app/Models/Account.php`, `Dentist.php`, `Order.php`, `OrderItem.php`, `DentistPayment.php`, `app/Concerns/HasForeignCurrency.php`, `MoneyValidationRules.php`, `OrderValidationRules.php`, the four dentist/order/payment Form Requests, `OrderController.php`, `OutstandingController.php`, `DashboardController.php`, `FinanceController.php`, `InvoiceController.php`, `app/Console/Commands/RedenominateMoney.php`, plus `resources/js/types/models.ts`, `components/dentists/dentist-form.tsx`, `components/price-list-editor.tsx`, `components/order-form.tsx`, `components/invoice-report.tsx`, `components/money/currency-amount-field.tsx`, `lib/money.ts`, `pages/outstanding/index.tsx`, `pages/ledger/statement.tsx`.

---

## Task 1: The currency enum and the three USD accounts

**Files:**
- Create: `app/Money/Currency.php`
- Create: `database/migrations/2026_08_29_120000_add_currency_to_accounts_table.php`
- Modify: `app/Ledger/AccountCode.php`
- Modify: `app/Models/Account.php`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Produces: `App\Money\Currency` (enum, cases `SYP`, `USD`; `minorPerMajor(): int`, `symbol(): string`); `AccountCode::cashFor(Currency): string`, `AccountCode::receivableFor(Currency): string`, `AccountCode::revenueFor(Currency): string`; `Account::currencyFor(string $code): Currency`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — `Error: Call to undefined method App\Models\Account::currencyFor()`.

- [ ] **Step 3: Create the currency enum**

```php
<?php
// app/Money/Currency.php

namespace App\Money;

/**
 * The two currencies this lab's books are kept in.
 *
 * Every stored money figure is an integer in its currency's MINOR unit: whole
 * lira for SYP (which has no subunit in practice), cents for USD. `minorPerMajor`
 * is the only place that ratio is written down.
 */
enum Currency: string
{
    case SYP = 'SYP';
    case USD = 'USD';

    /** Minor units in one major unit — 1 lira is 1, 1 dollar is 100 cents. */
    public function minorPerMajor(): int
    {
        return match ($this) {
            self::SYP => 1,
            self::USD => 100,
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::SYP => 'ل.س',
            self::USD => '$',
        };
    }
}
```

- [ ] **Step 4: Write the migration**

```php
<?php
// database/migrations/2026_08_29_120000_add_currency_to_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An account holds exactly one currency, and an entry never crosses
     * currencies — which is what lets `Ledger::post()`'s balance check stay
     * unchanged, and lets every existing code-keyed lira report keep reading
     * lira and only lira.
     *
     * A SYP account holds whole lira; a USD account holds cents.
     *
     * Every existing account is SYP, which is what it has always been.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('type');
        });

        $now = now();

        DB::table('accounts')->insert([
            [
                'code' => '1001', 'name' => 'صندوق الدولار', 'type' => 'asset',
                'currency' => 'USD', 'category_key' => null, 'is_active' => true,
                'sort_order' => 15, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => '1101', 'name' => 'ذمم الأطباء بالدولار', 'type' => 'asset',
                'currency' => 'USD', 'category_key' => null, 'is_active' => true,
                'sort_order' => 25, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => '4001', 'name' => 'إيرادات بالدولار', 'type' => 'revenue',
                'currency' => 'USD', 'category_key' => null, 'is_active' => true,
                'sort_order' => 45, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['1001', '1101', '4001'])->delete();

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
```

Note the `sort_order` values (15/25/45): they interleave each dollar account directly after its lira twin in the existing 10/20/30/40 sequence, so the trial balance and journal pages list them in a sensible order without renumbering anything.

- [ ] **Step 5: Add the resolvers**

In `app/Ledger/AccountCode.php`, add the three new cases and the resolvers:

```php
    case CASH_USD = '1001';
    case RECEIVABLE_USD = '1101';
    case REVENUE_USD = '4001';

    /**
     * The account a given currency's cash, receivables and revenue live in.
     * The ONLY place a currency maps to a code — nothing downstream should
     * ever hardcode '1101'.
     */
    public static function cashFor(Currency $currency): string
    {
        return match ($currency) {
            Currency::SYP => self::CASH->value,
            Currency::USD => self::CASH_USD->value,
        };
    }

    public static function receivableFor(Currency $currency): string
    {
        return match ($currency) {
            Currency::SYP => self::RECEIVABLE->value,
            Currency::USD => self::RECEIVABLE_USD->value,
        };
    }

    public static function revenueFor(Currency $currency): string
    {
        return match ($currency) {
            Currency::SYP => self::REVENUE->value,
            Currency::USD => self::REVENUE_USD->value,
        };
    }
```

Add `use App\Money\Currency;` at the top of the file.

In `app/Models/Account.php`, add alongside `typeFor()`:

```php
    public static function currencyFor(string $code): Currency
    {
        $account = self::chart()->get($code);

        if (! $account) {
            throw new \InvalidArgumentException("Unknown account code [{$code}].");
        }

        return Currency::from($account->currency);
    }
```

Add `use App\Money\Currency;` at the top, and add `'currency'` to `$fillable`.

- [ ] **Step 6: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: 2 passed.

- [ ] **Step 7: Run the full suite — nothing else may move**

Run Pest with no filter. Expected: all green. The migration is additive and every existing account defaults to `SYP`, so any failure here is a real regression.

- [ ] **Step 8: Commit**

```bash
git add app/Money/Currency.php app/Ledger/AccountCode.php app/Models/Account.php \
        database/migrations/2026_08_29_120000_add_currency_to_accounts_table.php \
        tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(ledger): give every account a currency and open the dollar three"
```

---

## Task 2: An entry may never cross currencies

**Files:**
- Create: `app/Ledger/MixedCurrencyEntryException.php`
- Modify: `app/Ledger/Ledger.php`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: `Account::currencyFor()` from Task 1.
- Produces: `App\Ledger\MixedCurrencyEntryException`; `Ledger::post()` now throws it when an entry's lines span two currencies.

This is the structural guard that makes the whole architecture safe. Without it, "one currency per entry" is a convention someone can break by accident; with it, it is impossible.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — `Error: Class "App\Ledger\MixedCurrencyEntryException" not found`.

- [ ] **Step 3: Create the exception**

```php
<?php
// app/Ledger/MixedCurrencyEntryException.php

namespace App\Ledger;

use RuntimeException;

/**
 * An entry whose lines span more than one currency.
 *
 * Sibling of UnbalancedEntryException, and thrown for the same reason: debits
 * equalling credits is meaningless when the two sides count different units.
 * 500 cents and 500 lira balance as integers and are not the same money.
 */
class MixedCurrencyEntryException extends RuntimeException {}
```

- [ ] **Step 4: Enforce it in `Ledger::post()`**

In `app/Ledger/Ledger.php`, call the new assertion right after the existing one:

```php
    public function post(string $date, string $description, array $lines, ?Model $source = null): JournalEntry
    {
        $this->assertBalanced($lines);
        $this->assertSingleCurrency($lines);
        // ... unchanged from here
```

and add the method beside `assertBalanced()`:

```php
    /**
     * Every line in an entry must belong to accounts of one currency.
     *
     * assertBalanced() compares bare integers, which is only meaningful while
     * both sides count the same unit. This is what keeps that true — and with
     * it, the guarantee that a lira report reading account 1100 can never see
     * a figure denominated in cents.
     *
     * @param  list<Line>  $lines
     */
    private function assertSingleCurrency(array $lines): void
    {
        $currencies = [];

        foreach ($lines as $line) {
            $currencies[Account::currencyFor($line->accountCode)->value] = true;
        }

        if (count($currencies) > 1) {
            throw new MixedCurrencyEntryException(
                'An entry may not mix currencies; this one spans '.implode(' and ', array_keys($currencies)).'.'
            );
        }
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: 4 passed.

- [ ] **Step 6: Run the full suite**

Run Pest with no filter. Expected: all green — every existing entry is single-currency by construction.

- [ ] **Step 7: Commit**

```bash
git add app/Ledger/MixedCurrencyEntryException.php app/Ledger/Ledger.php \
        tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(ledger): refuse an entry that spans two currencies"
```

---

## Task 3: The dollar dentist flag, and freezing it

**Files:**
- Create: `database/migrations/2026_08_29_120001_add_currency_to_dentists_table.php`
- Modify: `app/Models/Dentist.php`, `app/Http/Requests/StoreDentistRequest.php`, `app/Http/Requests/UpdateDentistRequest.php`
- Modify: `resources/js/types/models.ts`, `resources/js/components/dentists/dentist-form.tsx`, `resources/js/components/price-list-editor.tsx`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: `Currency` from Task 1.
- Produces: `Dentist::currency(): Currency`, `Dentist::isDollar(): bool`, `Dentist::hasLedgerLines(): bool`; `dentists.currency` column; the `currency` prop on the TS `Dentist` interface.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — `Error: Call to undefined method App\Models\Dentist::isDollar()`.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_29_120001_add_currency_to_dentists_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The currency a dentist's whole relationship with the lab is kept in.
     *
     * This is the AUTHORITY for the money on his rows: a dollar dentist's
     * orders and payments are native dollars — held in cents, converted by
     * nothing, and posted to the dollar accounts. Every existing dentist is
     * SYP, which is what they have always been.
     */
    public function up(): void
    {
        Schema::table('dentists', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('dentists', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
```

- [ ] **Step 4: Add the model helpers**

In `app/Models/Dentist.php`, add `'currency'` to `$fillable` (after `'name'`), add `use App\Money\Currency;` and `use App\Ledger\AccountCode;`, then:

```php
    /** The currency this dentist is quoted, billed and paid in. */
    public function currency(): Currency
    {
        return Currency::from($this->currency ?? Currency::SYP->value);
    }

    public function isDollar(): bool
    {
        return $this->currency() === Currency::USD;
    }

    /**
     * Whether anything has been posted to this dentist's account yet.
     *
     * Guards the currency flag: switching a dentist mid-history would leave
     * his old entries in one currency's receivable account and his new ones
     * in another, with no defensible way to read a single balance. Creating a
     * dollar dentist is supported; converting one is not.
     */
    public function hasLedgerLines(): bool
    {
        return DB::table('journal_lines')->where('dentist_id', $this->id)->exists();
    }
```

Add `use Illuminate\Support\Facades\DB;`.

Also make the price-list setter force USD for a dollar dentist, so his list can never hold a lira price. Replace the `set:` half of the `priceList()` attribute:

```php
            set: function (?array $value) {
                if ($value === null) {
                    return null;
                }

                $list = self::normalisePriceList($value);

                // A dollar dentist is quoted in dollars, full stop. Forced
                // here rather than in normalisePriceList, which is static and
                // has no dentist to ask.
                if ($this->isDollar()) {
                    $list = array_map(
                        fn (array $entry) => ['price' => $entry['price'], 'currency' => 'USD'],
                        $list,
                    );
                }

                return json_encode($list, JSON_UNESCAPED_UNICODE);
            },
```

- [ ] **Step 5: Add the validation rules**

In `StoreDentistRequest::rules()`, add:

```php
            'currency' => ['nullable', 'in:SYP,USD'],
```

In `UpdateDentistRequest::rules()`, add the same plus the freeze. Because the rule needs the model, use a closure:

```php
            'currency' => ['nullable', 'in:SYP,USD', function ($attribute, $value, $fail) {
                $dentist = $this->route('dentist');

                if ($value !== null
                    && $value !== ($dentist->currency ?? 'SYP')
                    && $dentist->hasLedgerLines()) {
                    $fail('لا يمكن تغيير عملة الطبيب بعد تسجيل حركات على حسابه.');
                }
            }],
```

- [ ] **Step 6: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: 7 passed.

- [ ] **Step 7: Add the currency choice to the dentist form**

In `resources/js/types/models.ts`, add to the `Dentist` interface, right after `name`:

```ts
    /**
     * What this dentist is quoted, billed and paid in. A USD dentist's money
     * is native dollars: cents, converted by nothing, and no rate anywhere.
     */
    currency?: 'SYP' | 'USD';
```

In `resources/js/components/dentists/dentist-form.tsx`:

1. Add `currency: dentist?.currency ?? ('SYP' as const),` to the `useForm` initial data.
2. Add `currency: payload.currency,` inside the `transform()` object.
3. Render the choice after the gender block, disabled once he has history — the server is the real guard, this is just honesty in the UI:

```tsx
            <div className="grid gap-2">
                <Label>العملة *</Label>
                <div className="flex gap-4">
                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="radio"
                            name="dentist_currency"
                            value="SYP"
                            checked={data.currency === 'SYP'}
                            onChange={() => setData('currency', 'SYP')}
                            className="accent-primary"
                        />
                        <span>ليرة</span>
                    </label>
                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="radio"
                            name="dentist_currency"
                            value="USD"
                            checked={data.currency === 'USD'}
                            onChange={() => setData('currency', 'USD')}
                            className="accent-primary"
                        />
                        <span>دولار</span>
                    </label>
                </div>
                <p className="text-sm text-muted-foreground">
                    طبيب الدولار تُسعَّر وتُفوتر وتُسدَّد حساباته بالدولار
                    بالكامل، دون أي تحويل إلى الليرة. لا يمكن تغيير العملة بعد
                    تسجيل أول حركة على حسابه.
                </p>
                <InputError message={errors.currency} />
            </div>
```

4. Pass the currency down to the price list so it stops offering a per-row choice:

```tsx
                <PriceListEditor
                    value={data.price_list}
                    onChange={(rows) => setData('price_list', rows)}
                    dentistCurrency={data.currency}
                />
```

5. `toRows()` must respect it too — a new dollar dentist's default rows start in USD. Change the `else` branch:

```tsx
const toRows = (dentist?: Dentist | null): PriceRow[] =>
    dentist
        ? Object.entries(dentist.price_list ?? {}).map(([name, entry]) => ({
              name,
              // Dollar prices are stored in cents; the row edits whole dollars.
              price: entry.currency === 'USD' ? entry.price / 100 : entry.price,
              currency: entry.currency,
          }))
        : DEFAULT_WORK_TYPES.map((name) => ({
              name,
              price: 0,
              currency: 'SYP' as const,
          }));
```

leave as is, and instead force the currency on submit inside `transform()`, which is the single place that already shapes the payload:

```tsx
                    {
                        price:
                            payload.currency === 'USD' || row.currency === 'USD'
                                ? Math.round(row.price * 100)
                                : Math.round(row.price),
                        currency:
                            payload.currency === 'USD' ? 'USD' : row.currency,
                    },
```

- [ ] **Step 8: Lock the price list editor to dollars for a dollar dentist**

In `resources/js/components/price-list-editor.tsx`, take the new prop and hide the per-row toggle:

```tsx
export default function PriceListEditor({
    value,
    onChange,
    dentistCurrency = 'SYP',
}: {
    value: PriceRow[];
    onChange: (rows: PriceRow[]) => void;
    /**
     * A dollar dentist is quoted only in dollars, so the per-row currency
     * toggle is meaningless for him and is hidden rather than shown disabled.
     */
    dentistCurrency?: 'SYP' | 'USD';
}) {
```

Inside the row, replace the `<CurrencyToggle .../>` element with:

```tsx
                            {dentistCurrency === 'USD' ? (
                                <span
                                    dir="ltr"
                                    className="w-16 text-center text-sm text-muted-foreground"
                                >
                                    $
                                </span>
                            ) : (
                                <CurrencyToggle
                                    value={row.currency}
                                    onChange={(currency) =>
                                        setCurrency(index, currency)
                                    }
                                />
                            )}
```

and make the price input's step follow the dentist as well as the row:

```tsx
                                step={
                                    dentistCurrency === 'USD' ||
                                    row.currency === 'USD'
                                        ? '0.01'
                                        : '1'
                                }
```

- [ ] **Step 9: Run the type check**

```bash
docker exec moslie-vite-local npm run types
```

Expected: no errors.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_29_120001_add_currency_to_dentists_table.php \
        app/Models/Dentist.php app/Http/Requests/StoreDentistRequest.php \
        app/Http/Requests/UpdateDentistRequest.php resources/js/types/models.ts \
        resources/js/components/dentists/dentist-form.tsx \
        resources/js/components/price-list-editor.tsx \
        tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(dentists): mark a dentist as billed in dollars, and freeze it once posted"
```

---

## Task 4: Native dollars — the third money state

**Files:**
- Modify: `app/Concerns/HasForeignCurrency.php`, `app/Models/OrderItem.php`, `app/Models/DentistPayment.php`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: `Dentist::currency()` (Task 3), `Currency` (Task 1).
- Produces: `HasForeignCurrency::nativeCurrency(): Currency` (overridable hook, defaults to `SYP`), `HasForeignCurrency::isNativeUsd(): bool`, `HasForeignCurrency::valueInOwnCurrency(): int`.

- [ ] **Step 1: Write the failing test**

```php
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Money\MissingRateException;

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — the first test throws `MissingRateException` ("needs both an original_amount and a rate"), because the trait knows only two states.

- [ ] **Step 3: Teach the trait the third state**

Replace the body of `app/Concerns/HasForeignCurrency.php` below the class docblock. Update the docblock's opening line too — the lira is no longer unconditionally the currency of record:

```php
/**
 * Money that may have arrived in a currency other than the row's own.
 *
 * A row is in one of three states:
 *
 *   Lira            — `amount` holds lira; no provenance.
 *   Dollars, converted — `original_amount` and `rate` say what it was, and
 *                     `amount` holds the lira it became. Conversion happens
 *                     once, on write, at the rate stored on the row, so a
 *                     rate recorded tomorrow never moves a figure booked
 *                     today.
 *   Native dollars  — the owner (a dollar dentist) is denominated in dollars,
 *                     so nothing converts: `original_amount` holds the cents,
 *                     `rate` stays NULL, and the lira column is 0. He owes no
 *                     lira, and every lira SUM() in the codebase stays right
 *                     without being touched.
 *
 * Which state a row is in is decided by its OWNER, never by inspecting the
 * row: `nativeCurrency()` asks the dentist. `rate` NULL is the row's own
 * marker of the third state, and the two are kept from diverging by the
 * exception below.
 */
trait HasForeignCurrency
{
    public static function bootHasForeignCurrency(): void
    {
        static::saving(fn ($model) => $model->applyExchangeRate());
    }

    /**
     * The column holding the value in the row's own currency. Every money
     * table calls it `amount` except `order_items`, which calls it `price`.
     */
    protected function liraColumn(): string
    {
        return 'amount';
    }

    /**
     * The currency this row's OWNER is denominated in. SYP for everything
     * that has no dollar owner — the expense tables, and every lira dentist.
     */
    protected function nativeCurrency(): Currency
    {
        return Currency::SYP;
    }

    /** Is this row dollars that were never converted, and never will be? */
    public function isNativeUsd(): bool
    {
        return $this->nativeCurrency() === Currency::USD;
    }

    /** Did this money arrive as something other than the lira? */
    public function isForeign(): bool
    {
        return $this->currency !== null && $this->currency !== 'SYP';
    }

    /**
     * This row's value in its own currency's minor unit: cents for a native
     * dollar row, whole lira for everything else. What the ledger posts.
     */
    public function valueInOwnCurrency(): int
    {
        return $this->isNativeUsd()
            ? (int) $this->original_amount
            : (int) $this->{$this->liraColumn()};
    }

    protected function applyExchangeRate(): void
    {
        if ($this->isNativeUsd()) {
            // Nothing to convert, and nothing to convert AT: a dollar
            // dentist's money never touches a rate.
            if ($this->rate !== null) {
                throw new \InvalidArgumentException(
                    static::class.' belongs to a dollar dentist and must not carry an exchange rate; '
                    .'his money is never converted.'
                );
            }

            $this->currency = Currency::USD->value;
            $this->{$this->liraColumn()} = 0;

            if ($this->original_amount === null) {
                throw new MissingRateException(
                    static::class.' for a dollar dentist needs an original_amount; '
                    .'there is no lira figure to fall back on.'
                );
            }

            return;
        }

        if (! $this->isForeign()) {
            // A lira row carries no conversion; clear any provenance left
            // behind by an edit that switched the currency back.
            $this->original_amount = null;
            $this->rate = null;

            return;
        }

        if ($this->original_amount === null || $this->rate === null) {
            throw new MissingRateException(
                static::class.' in '.$this->currency.' needs both an original_amount and a rate; '
                .'converting without them would book it as nothing.'
            );
        }

        $this->{$this->liraColumn()} = Rate::toSyp((int) $this->original_amount, (string) $this->rate);
    }
}
```

Add `use App\Money\Currency;` to the imports.

- [ ] **Step 4: Point the two owned models at their dentist**

In `app/Models/DentistPayment.php`:

```php
    protected function nativeCurrency(): Currency
    {
        return $this->dentist?->currency() ?? Currency::SYP;
    }
```

In `app/Models/OrderItem.php`:

```php
    /**
     * An item is native dollars when its order's dentist is a dollar dentist.
     *
     * `$this->order` is a relation read on save. OrderController sets the
     * relation explicitly before saving each item (see its `itemFor()`), so
     * this costs no query on the write path that matters.
     */
    protected function nativeCurrency(): Currency
    {
        return $this->order?->dentist?->currency() ?? Currency::SYP;
    }
```

Add `use App\Money\Currency;` to both.

- [ ] **Step 5: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: 10 passed.

- [ ] **Step 6: Run the full suite**

Run Pest with no filter. Expected: all green. Every existing row has a lira dentist or no dentist, so `nativeCurrency()` returns `SYP` and the old two-state logic runs unchanged.

- [ ] **Step 7: Commit**

```bash
git add app/Concerns/HasForeignCurrency.php app/Models/OrderItem.php \
        app/Models/DentistPayment.php tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(money): add native dollars, money that is never converted"
```

---

## Task 5: A dollar order saves, end to end

**Files:**
- Create: `database/migrations/2026_08_29_120002_add_currency_to_orders_table.php`
- Modify: `app/Models/Order.php`, `app/Concerns/OrderValidationRules.php`, `app/Http/Controllers/OrderController.php`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: `Dentist::isDollar()` (Task 3), `OrderItem::valueInOwnCurrency()` (Task 4).
- Produces: `orders.currency`, `orders.original_amount`; `Order::currency(): Currency`, `Order::valueInOwnCurrency(): int`.

- [ ] **Step 1: Write the failing test**

```php
use App\Models\Order;

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

    expect(Order::count())->toBe(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — `orders.original_amount` does not exist.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_29_120002_add_currency_to_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same currency/original_amount pair `order_items` already carries,
     * so an order row stays self-describing: `amount` is its lira (zero for a
     * dollar dentist) and `original_amount` its cents. Both are recomputed
     * from the items on every write, exactly as `amount` already was.
     *
     * No `rate` column: a native dollar order has no rate, and a lira order's
     * per-line rates already live on its items.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('amount');
            $table->integer('original_amount')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'original_amount']);
        });
    }
};
```

- [ ] **Step 4: Extend the Order model**

In `app/Models/Order.php`, add `'currency'` and `'original_amount'` to `$fillable`, add `'original_amount' => 'integer'` to `$casts`, add `use App\Money\Currency;`, and:

```php
    public function currency(): Currency
    {
        return Currency::from($this->currency ?? Currency::SYP->value);
    }

    /**
     * What this order is worth in its own currency's minor unit — cents for a
     * dollar dentist, whole lira otherwise. What OrderPosting books.
     */
    public function valueInOwnCurrency(): int
    {
        return $this->currency() === Currency::USD
            ? (int) $this->original_amount
            : (int) $this->amount;
    }
```

- [ ] **Step 5: Make the validation currency-aware**

`orderRules()` has no dentist in scope, so add a `dentistIsDollar()` helper to the trait and branch the two dollar rules. In `app/Concerns/OrderValidationRules.php`, inside `orderRules()`, replace the `items.*.rate` rule and add a native branch:

```php
        // A dollar dentist's line is NATIVE dollars: cents, and no rate at
        // all — his money is never converted. A lira dentist's dollar line is
        // a quote and still needs the rate it was quoted at.
        $dollarDentist = $this->dentistIsDollar();

        return [
            // ... unchanged rules above ...
            'items.*.original_amount' => $dollarDentist
                ? ['required', 'integer', 'min:1']
                : ['exclude_unless:items.*.currency,USD', 'required', 'integer', 'min:1'],
            'items.*.rate' => $dollarDentist
                ? ['prohibited']
                : ['exclude_unless:items.*.currency,USD', 'required', 'numeric', 'min:0.000001'],
            'items.*.price' => $dollarDentist
                ? ['prohibited']
                : ['required_unless:items.*.currency,USD', 'nullable', 'integer', 'min:0'],
            // ... unchanged rules below ...
        ];
```

and add to the same trait:

```php
    /**
     * Whether the dentist this order is for is billed in dollars. Read from
     * the request rather than the route, because both storing and updating
     * submit `dentist_id`.
     */
    protected function dentistIsDollar(): bool
    {
        $dentist = \App\Models\Dentist::find($this->input('dentist_id'));

        return (bool) $dentist?->isDollar();
    }
```

Note: the `items.*.price` rule becomes `prohibited` for a dollar dentist, so the order form must stop sending it for him — that is Task 6's frontend step. Until then the two tests in this task cover the API contract, which is the thing being specified.

- [ ] **Step 6: Compute the order's two totals**

In `app/Http/Controllers/OrderController.php`, replace `withLiraPrices()` and both `$validated['amount'] = ...` lines. First the item resolver:

```php
    /**
     * Resolve every item's stored value before anything sums them.
     *
     * A dollar dentist's line is native dollars: the cents stand, no rate is
     * involved, and its lira price is zero. A lira dentist's dollar line is a
     * quote that converts at the rate given with it — `App\Money\Rate` is the
     * only thing that converts, here and in the model.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withResolvedPrices(array $items, Dentist $dentist): array
    {
        return array_map(function (array $item) use ($dentist) {
            if ($dentist->isDollar()) {
                $item['currency'] = 'USD';
                $item['price'] = 0;
                $item['rate'] = null;

                return $item;
            }

            if (($item['currency'] ?? 'SYP') !== 'USD') {
                $item['currency'] = 'SYP';
                $item['original_amount'] = null;
                $item['rate'] = null;

                return $item;
            }

            $item['price'] = Rate::toSyp((int) $item['original_amount'], (string) $item['rate']);

            return $item;
        }, $items);
    }
```

Then in **both** `store()` and `update()`, replace the three lines after `unset($validated['items']);` with:

```php
        $dentist = Dentist::findOrFail($validated['dentist_id']);
        $items = $this->withResolvedPrices($items, $dentist);

        // Both totals are derived from the items, so neither can drift from
        // them. For a dollar dentist the lira total is legitimately zero and
        // the cents total is what he owes; for a lira dentist, the reverse.
        $validated['currency'] = $dentist->currency()->value;
        $validated['amount'] = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        $validated['original_amount'] = $dentist->isDollar()
            ? collect($items)->sum(fn ($item) => $item['quantity'] * (int) $item['original_amount'])
            : null;
        // The order's due date is derived from the earliest item date.
        $validated['due_date'] = collect($items)->pluck('date')->filter()->min() ?? now()->toDateString();
```

Add `use App\Models\Dentist;` to the controller's imports.

- [ ] **Step 7: Set the order relation before saving each item**

`OrderItem::nativeCurrency()` reads `$this->order->dentist`. Saving through `$order->items()->create()` leaves that relation unset, costing a query per item. In both `store()` and `update()`, replace the item loop body:

```php
            foreach ($items as $item) {
                $order->items()->save($this->itemFor($order, $item));
            }
```

and add:

```php
    /**
     * Build an item already knowing its order — and through it, its dentist.
     *
     * OrderItem::nativeCurrency() asks the dentist whether this line is
     * native dollars, and it is asked on every save. Setting the relation
     * here answers it from memory instead of a query per item.
     *
     * @param  array<string, mixed>  $item
     */
    private function itemFor(Order $order, array $item): OrderItem
    {
        $model = new OrderItem($this->itemAttributes($item));
        $model->setRelation('order', $order);

        return $model;
    }
```

Add `use App\Models\OrderItem;` to the imports. `$order` must already have its `dentist` relation set — add `$order->setRelation('dentist', $dentist);` immediately after `Order::create($validated)` in `store()`, and after `$order->update($validated)` in `update()`.

- [ ] **Step 8: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: 12 passed.

- [ ] **Step 9: Run the full suite**

Run Pest with no filter. Expected: all green — `ForeignCurrencyOrderTest` in particular must still pass unchanged, since every dentist in it is a lira dentist.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_29_120002_add_currency_to_orders_table.php \
        app/Models/Order.php app/Concerns/OrderValidationRules.php \
        app/Http/Controllers/OrderController.php tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(orders): save a dollar dentist's order in cents, with no rate"
```

---

## Task 6: The order form, in dollars only

**Files:**
- Modify: `resources/js/components/order-form.tsx`, `resources/js/lib/money.ts`, `resources/js/types/models.ts`
- Test: manual, plus `npm run types`

**Interfaces:**
- Consumes: `Dentist.currency` (Task 3), the API contract from Task 5.
- Produces: `formatMoney(value, currency)` in `lib/money.ts`.

The form must stop sending `price` and `rate` for a dollar dentist — Task 5 made both `prohibited` — and must stop offering a currency toggle or a lira preview.

- [ ] **Step 1: Add the shared formatter**

In `resources/js/lib/money.ts`, append:

```ts
/**
 * A stored figure rendered in its own currency: whole lira grouped, or cents
 * as dollars with both decimals. The single place a currency decides how a
 * number reads.
 */
export const formatMoney = (
    value: number,
    currency: 'SYP' | 'USD' = 'SYP',
): string => (currency === 'USD' ? `$${formatUsd(value)}` : formatSyp(value));
```

- [ ] **Step 2: Add the order's currency to the TS types**

In `resources/js/types/models.ts`, add to the `Order` interface after `amount`:

```ts
    /** What this order is billed in. USD orders hold cents in `original_amount`. */
    currency?: 'SYP' | 'USD';
    /** Total in US cents, when `currency` is USD. `amount` is then 0. */
    original_amount?: number | null;
```

- [ ] **Step 3: Branch the order form on the selected dentist**

In `resources/js/components/order-form.tsx`, derive the flag once from the already-selected dentist and thread it through:

```tsx
    const selectedDentist = dentists.find(
        (d) => String(d.id) === String(data.dentist_id),
    );
    const dollarDentist = selectedDentist?.currency === 'USD';
```

Then, for a dollar dentist:

- Hide the per-item `CurrencyToggle` and the rate input entirely; the item is dollars by definition.
- Label the price input `السعر بالدولار` with `step="0.01"`, and bind it to `original_amount` in dollars (multiply by 100 on submit, as the price list already does).
- Replace the lira conversion preview with the plain dollar line total.
- In the form's `transform()`, **omit `price` and `rate` entirely** from each item for a dollar dentist, and send `original_amount` as cents. Sending them zeroed will now fail validation (`prohibited`), which is deliberate — the server refuses to be told a lira figure for a dollar dentist.
- The price-list autofill already stores dollar prices in cents, so for a dollar dentist it fills straight into the dollar field with no conversion.

- [ ] **Step 4: Show order totals in the right currency**

Anywhere the form or the orders list renders an order total, route it through `formatMoney(order.currency === 'USD' ? order.original_amount ?? 0 : order.amount, order.currency)`. Grep for `formatSyp(` and `toLocaleString('en-US')` in `resources/js/pages/orders/` and `resources/js/components/order-form.tsx` and convert each site.

- [ ] **Step 5: Run the type check**

```bash
docker exec moslie-vite-local npm run types
```

Expected: no errors.

- [ ] **Step 6: Verify by hand in the running app**

Start the app (`./docker-start-local.sh`), create a dollar dentist with a dollar price list, then create an order for him. Confirm: no currency toggle, no rate field, no lira anywhere on the form, and the order saves without validation errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/order-form.tsx resources/js/lib/money.ts \
        resources/js/types/models.ts resources/js/pages/orders
git commit -m "feat(orders): drop the rate and the lira from a dollar dentist's form"
```

---

## Task 7: Posting a dollar order and payment to the dollar accounts

**Files:**
- Modify: `app/Ledger/Postings/OrderPosting.php`, `app/Ledger/Postings/DentistPaymentPosting.php`, `app/Concerns/MoneyValidationRules.php`, `app/Http/Requests/StoreDentistPaymentRequest.php`, `app/Http/Requests/UpdateDentistPaymentRequest.php`, `app/Http/Controllers/DentistPaymentController.php`
- Modify: `resources/js/components/money/currency-amount-field.tsx`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: `AccountCode::receivableFor()/revenueFor()/cashFor()` (Task 1), `Order::valueInOwnCurrency()` (Task 5), `DentistPayment::valueInOwnCurrency()` (Task 4).

This is the task the whole feature exists for: **$500 ordered, $200 paid, exactly $300 owed.**

- [ ] **Step 1: Write the failing test**

```php
use App\Ledger\LedgerReports;

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
    \App\Models\ExchangeRate::create(['rate_date' => '2026-10-01', 'rate' => '250']);

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — `balance('1101')` is `0`; the order posted to `1100` at a value of `0`, or did not post at all.

- [ ] **Step 3: Make `OrderPosting` currency-aware**

In `app/Ledger/Postings/OrderPosting.php`:

```php
    public function shouldPost(): bool
    {
        return $this->order->status !== 'cancelled' && $this->order->valueInOwnCurrency() !== 0;
    }
```

In `entries()`, resolve the codes from the order's currency:

```php
    public function entries(): array
    {
        $currency = $this->order->currency();

        return $this->amountsByDate()
            ->map(fn (int $amount, string $date) => new Entry(
                $date,
                "طلب #{$this->order->id}",
                [
                    Line::debit(AccountCode::receivableFor($currency), $amount, $this->order->dentist_id),
                    Line::credit(AccountCode::revenueFor($currency), $amount),
                ],
            ))
            ->values()
            ->all();
    }
```

And in `amountsByDate()`, value the order and its items in their own currency:

```php
        $amount = $this->order->valueInOwnCurrency();
```

```php
        $byDate = $items
            ->groupBy(fn (OrderItem $item) => $item->meta['date'] ?? $dueDate)
            ->map(fn (Collection $group) => (int) $group->sum(
                fn (OrderItem $item) => $item->quantity * $item->valueInOwnCurrency()
            ));
```

The residual logic below it is unchanged and keeps working: it reconciles the per-date item sum against the order's own total, and both are now read in the same currency.

- [ ] **Step 4: Make `DentistPaymentPosting` currency-aware**

```php
    public function shouldPost(): bool
    {
        return $this->payment->valueInOwnCurrency() !== 0;
    }

    public function lines(): array
    {
        $currency = $this->payment->dentist->currency();
        $amount = $this->payment->valueInOwnCurrency();

        return [
            Line::debit(AccountCode::cashFor($currency), $amount),
            Line::credit(AccountCode::receivableFor($currency), $amount, $this->payment->dentist_id),
        ];
    }
```

- [ ] **Step 5: Make the payment validation currency-aware**

In `app/Concerns/MoneyValidationRules.php`, add an optional native flag to both methods. `moneyRules(string $field = 'amount', bool $native = false)`:

```php
        if ($native) {
            // A dollar dentist's payment: dollars, and nothing else. No rate,
            // because his money is never converted, and no lira figure,
            // because he does not deal in lira.
            return [
                'currency' => ['nullable', 'in:USD'],
                $field => ['prohibited'],
                'original_amount' => ['required', 'numeric', 'min:0.01'],
                'rate' => ['prohibited'],
            ];
        }
```

placed at the top of the method, before the existing `return`. And in `moneyPayload(array $data, string $field = 'amount', bool $native = false)`, at the top:

```php
        if ($native) {
            $data['currency'] = 'USD';
            $data['original_amount'] = (int) round(((float) $data['original_amount']) * 100);
            $data['rate'] = null;
            unset($data[$field]);

            return $data;
        }
```

In both `StoreDentistPaymentRequest` and `UpdateDentistPaymentRequest`, resolve the dentist and pass the flag:

```php
    public function rules(): array
    {
        return [
            'dentist_id' => ['required', 'exists:dentists,id'],
            'payment_date' => ['required', 'date'],
            ...$this->moneyRules('amount', $this->dentistIsDollar()),
        ];
    }

    public function payload(): array
    {
        return $this->moneyPayload($this->validated(), 'amount', $this->dentistIsDollar());
    }

    /** Whether the dentist being paid is billed in dollars. */
    private function dentistIsDollar(): bool
    {
        return (bool) \App\Models\Dentist::find($this->input('dentist_id'))?->isDollar();
    }
```

- [ ] **Step 6: Do not remember a rate that does not exist**

In `DentistPaymentController::store()` and `update()`, `Rate::remember()` is called when `isForeign()`. A native dollar payment is foreign but has no rate, so guard it:

```php
        if ($payment->isForeign() && ! $payment->isNativeUsd()) {
            Rate::remember($payment->payment_date, $payment->rate);
        }
```

Apply the same change in `update()`, using `$dentistPayment`.

- [ ] **Step 7: Drop the toggle and the preview from the payment form**

`CurrencyAmountField` needs a native mode. Add a prop:

```tsx
    /**
     * A dollar dentist: the money is dollars by definition, so there is no
     * currency to choose, no rate to agree, and no lira result to preview.
     */
    native?: boolean;
```

When `native` is true, render only the dollar amount input (labelled `{label} بالدولار`) and skip the currency radios, the rate input and the "يُسجَّل في الحساب" preview entirely. Pass `native={dollarDentist}` from the payment create/edit pages, deriving `dollarDentist` from the selected dentist exactly as Task 6 does. The pages must also stop sending `rate` and `amount` for him, matching the `prohibited` rules.

- [ ] **Step 8: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: 14 passed — including the $300 one.

- [ ] **Step 9: Run the full suite and the type check**

Run Pest with no filter, then `docker exec moslie-vite-local npm run types`. Expected: both green.

- [ ] **Step 10: Commit**

```bash
git add app/Ledger/Postings app/Concerns/MoneyValidationRules.php \
        app/Http/Requests/StoreDentistPaymentRequest.php \
        app/Http/Requests/UpdateDentistPaymentRequest.php \
        app/Http/Controllers/DentistPaymentController.php \
        resources/js/components/money/currency-amount-field.tsx resources/js/pages/payments \
        tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(ledger): book a dollar dentist's work and cash in dollars"
```

---

## Task 8: Currency-aware ledger reads

**Files:**
- Modify: `app/Ledger/LedgerReports.php`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Produces: `receivablesByDentist(?string $asOf = null, Currency $currency = Currency::SYP)`; `dentistStatement(int $dentistId, ?string $from = null, ?string $to = null, Currency $currency = Currency::SYP)`; `trialBalance()` rows gain a `currency` key.

Every existing caller passes `$asOf` positionally and gets `SYP` by default, so no call site changes meaning.

- [ ] **Step 1: Write the failing test**

```php
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

    $inLira = $reports->receivablesByDentist(null, App\Money\Currency::SYP);
    $inDollars = $reports->receivablesByDentist(null, App\Money\Currency::USD);

    expect($inLira->get($lira->id))->toBe(400)
        ->and($inLira->has($dollar->id))->toBeFalse()
        ->and($inDollars->get($dollar->id))->toBe(50000)
        ->and($inDollars->has($lira->id))->toBeFalse();

    $statement = $reports->dentistStatement($dollar->id, '2026-09-01', '2026-09-30', App\Money\Currency::USD);

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — `receivablesByDentist()` takes one argument.

- [ ] **Step 3: Thread the currency through**

In `app/Ledger/LedgerReports.php`, add `use App\Money\Currency;` and:

```php
    /**
     * @return Collection<int, int> dentist_id => balance, in the given
     *                              currency's minor unit
     */
    public function receivablesByDentist(?string $asOf = null, Currency $currency = Currency::SYP): Collection
    {
        return $this->linesForAccount(AccountCode::receivableFor($currency))
            // ... body otherwise unchanged
    }
```

`dentistStatement()` gains the same trailing parameter and swaps its two
`AccountCode::RECEIVABLE->value` uses for `AccountCode::receivableFor($currency)`.

Its per-line `'currency' => $row->currency ?? 'SYP'` fallback must change too — for a dollar dentist a line with no payment behind it is dollars, not lira:

```php
                    // A line with no payment behind it is denominated in the
                    // account it sits in — lira for the lira receivable,
                    // dollars for the dollar one.
                    'currency' => $row->currency ?? $currency->value,
```

`trialBalance()` selects and returns the account's currency:

```php
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type', 'accounts.currency', 'accounts.sort_order')
            ->orderBy('accounts.sort_order')
            ->selectRaw('accounts.code, accounts.name, accounts.type, accounts.currency, COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
```

and each mapped row gains `'currency' => $row->currency,`. Update the method's `@return` docblock to include `currency: string`.

- [ ] **Step 4: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: 16 passed.

- [ ] **Step 5: Run the full suite**

Run Pest with no filter. Expected: all green — every existing call site defaults to `SYP`.

- [ ] **Step 6: Commit**

```bash
git add app/Ledger/LedgerReports.php tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(ledger): let the reports be read in either currency"
```

---

## Task 9: Outstanding, dashboard and finance grow a dollar column

**Files:**
- Modify: `app/Http/Controllers/OutstandingController.php`, `DashboardController.php`, `FinanceController.php`
- Modify: `resources/js/pages/outstanding/index.tsx`, `resources/js/pages/dashboard.tsx`, `resources/js/pages/finance/index.tsx`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: `receivablesByDentist(asOf, currency)` (Task 8), `formatMoney()` (Task 6).
- Produces: an `outstandingUsd` / `cashUsd` / `incomeUsd` shape on the Inertia props, and a `currency` on each outstanding row.

- [ ] **Step 1: Write the failing test**

```php
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

    $this->get(route('outstanding.index'))
        ->assertInertia(fn ($page) => $page
            ->where('totalOutstanding', 400)      // lira only
            ->where('totalOutstandingUsd', 50000) // dollars only, never added
            ->where('dentists.0.currency', 'USD')
            ->where('dentists.0.outstanding', 50000)
            ->where('dentists.0.orders_total', 50000)
        );
});
```

(`dentists.0` is the dollar dentist because the list is sorted by `outstanding` descending and 50000 > 400 — a coincidence of units, which is itself a reminder never to sort the two together. Sort within currency instead: see Step 2.)

- [ ] **Step 2: Make Outstanding currency-aware**

Rewrite `OutstandingController::index()`:

```php
    public function index(LedgerReports $reports)
    {
        $balances = [
            Currency::SYP->value => $reports->receivablesByDentist(null, Currency::SYP),
            Currency::USD->value => $reports->receivablesByDentist(null, Currency::USD),
        ];

        $dentists = Dentist::query()
            ->withSum(['orders as orders_total' => fn ($q) => $q->billable()], 'amount')
            // A dollar dentist's orders and payments hold zero lira and their
            // real value in `original_amount`, so both display columns need
            // the second sum to have anything to show.
            ->withSum(['orders as orders_total_usd' => fn ($q) => $q->billable()], 'original_amount')
            ->withSum('payments as payments_total', 'amount')
            ->withSum('payments as payments_total_usd', 'original_amount')
            ->get()
            ->map(function (Dentist $dentist) use ($balances) {
                $currency = $dentist->currency();
                $dollar = $currency === Currency::USD;

                return [
                    'id' => $dentist->id,
                    'name' => $dentist->name,
                    'phone' => $dentist->phone,
                    'currency' => $currency->value,
                    'orders_total' => (int) ($dollar ? $dentist->orders_total_usd : $dentist->orders_total),
                    'payments_total' => (int) ($dollar ? $dentist->payments_total_usd : $dentist->payments_total),
                    'outstanding' => $balances[$currency->value][$dentist->id] ?? 0,
                ];
            })
            // Sorted within currency, then by size. Sorting the two together
            // would rank cents against lira, which means nothing.
            ->sortBy([['currency', 'asc'], ['outstanding', 'desc']])
            ->values();

        return inertia('outstanding/index', [
            'dentists' => $dentists,
            'totalOutstanding' => $reports->balance(AccountCode::RECEIVABLE->value),
            'totalOutstandingUsd' => $reports->balance(AccountCode::RECEIVABLE_USD->value),
        ]);
    }
```

Add `use App\Money\Currency;`. Adjust the test's `dentists.0` index if the sort puts the lira dentist first — `SYP` sorts before `USD`, so `dentists.0` is the lira dentist and the dollar one is `dentists.1`. Fix the test accordingly rather than the sort.

- [ ] **Step 3: Add the dollar column to the outstanding page**

In `resources/js/pages/outstanding/index.tsx`: add `currency: 'SYP' | 'USD'` to `DentistBalance`, add `totalOutstandingUsd: number` to the props, render **two** summary cards (إجمالي المستحق بالليرة and إجمالي المستحق بالدولار), and render each row's three money cells through `formatMoney(value, dentist.currency)`. Replace the local `nf` helper with the shared `formatMoney` import. Give the dollar rows a `dir="ltr"` on the money cells.

- [ ] **Step 4: Add the dollar pairs to the dashboard**

In `DashboardController::index()`, add beside the existing stats:

```php
                'income_usd' => $reports->movementBetween(
                    AccountCode::CASH_USD->value, AccountCode::RECEIVABLE_USD->value, $start, $end,
                ),
                'earned_usd' => $reports->movementBetween(
                    AccountCode::RECEIVABLE_USD->value, AccountCode::REVENUE_USD->value, $start, $end,
                ),
                'outstanding_usd' => $reports->balance(AccountCode::RECEIVABLE_USD->value),
                'cash_balance_usd' => $reports->balance(AccountCode::CASH_USD->value),
```

Render them in `resources/js/pages/dashboard.tsx` as a dollar figure beneath each matching lira KPI, through `formatMoney(value, 'USD')` with `dir="ltr"`. There is no dollar expense or net figure — the dollar box only fills — so show cash in and receivables only, and do not compute a dollar net.

- [ ] **Step 5: Add the dollar cash-in column to finance**

In `FinanceController`, add the same `movementBetween(CASH_USD, RECEIVABLE_USD, ...)` per month alongside the existing lira cash-in, and render it in `resources/js/pages/finance/index.tsx` as its own column headed `الوارد بالدولار`, with a dash in the expenses and net columns for it.

- [ ] **Step 6: Run the tests and the type check**

Run Pest with `--filter="DollarDentist"`, then `docker exec moslie-vite-local npm run types`. Expected: both green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/OutstandingController.php app/Http/Controllers/DashboardController.php \
        app/Http/Controllers/FinanceController.php resources/js/pages/outstanding \
        resources/js/pages/dashboard.tsx resources/js/pages/finance \
        tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(reports): give the dollar side its own column, never merged"
```

---

## Task 10: The invoice, statement and PDF in dollars

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php`, `app/Http/Controllers/LedgerController.php`
- Modify: `resources/js/components/invoice-report.tsx`, `resources/js/pages/ledger/statement.tsx`, `resources/js/pages/ledger/statement-print.tsx`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: `dentistStatement(..., Currency)` (Task 8), `receivablesByDentist(asOf, currency)` (Task 8), `formatMoney()` (Task 6).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: FAIL — no `currency` prop, and the totals are `0` because they sum the lira columns.

- [ ] **Step 3: Carry a currency through `buildReport`**

In `InvoiceController::buildReport()`, after `$dentistId` is read:

```php
        // With one dentist selected the report is single-currency — which is
        // how an invoice is actually printed. With none selected it spans
        // both, and the totals below are kept apart rather than added.
        $dentist = $dentistId ? Dentist::find($dentistId) : null;
        $currency = $dentist?->currency() ?? Currency::SYP;
```

Value the orders and payments in each row's own currency. Replace the two total computations:

```php
            $ordersTotal = $orders->sum(
                fn (Order $order) => $order->items->isEmpty()
                    ? $order->valueInOwnCurrency()
                    : $order->items->sum(fn ($item) => $item->valueInOwnCurrency() * $item->quantity)
            );
            $paymentsTotal = $payments->sum(fn (DentistPayment $payment) => $payment->valueInOwnCurrency());
```

and read the opening balance from the matching receivable account:

```php
            $openingByDentist = $this->reports->receivablesByDentist($asOf, $currency)
```

When no dentist is selected, compute **both** and expose them separately:

```php
            $totals = [
                'opening' => $openingTotal,
                'orders' => $ordersTotal,
                'payments' => $paymentsTotal,
                'balance' => $openingTotal + $ordersTotal - $paymentsTotal,
            ];
```

stays as the selected-currency total; add alongside it, only when `$dentistId` is null, a second set built the same way from `Currency::USD`, keyed `totalsUsd`. Add `'currency' => $currency->value` to the returned payload.

Add `use App\Money\Currency;` to the imports.

- [ ] **Step 4: Render the invoice in its currency**

In `resources/js/components/invoice-report.tsx`: take `currency` (and optional `totalsUsd`) as props, and route every money cell through `formatMoney(value, currency)`. Each order line's value is `item.currency === 'USD' ? item.original_amount : item.price`. Suppress `<ForeignOrigin>` when the dentist is a dollar dentist — there is no rate to prove — and suppress `<ApproxUsd>` **explicitly by currency**, not by relying on its own no-rate check: rates exist in the table for the lira dentists, so it would otherwise divide his dollar balance by one and print nonsense.

When `totalsUsd` is present (no dentist selected), render two total rows — `مجموع الليرة` and `مجموع الدولار` — never one blended figure.

- [ ] **Step 5: Render the statement in its currency**

In `LedgerController`, pass the dentist's currency into `dentistStatement()` and add `'currency' => $dentist->currency()->value` to the Inertia payload. In `statement.tsx` and `statement-print.tsx`, format every figure with `formatMoney(value, currency)`, and hide the `ForeignOrigin` and `ApproxUsd` elements for a dollar dentist for the same reasons.

- [ ] **Step 6: Run the tests and the type check**

Run Pest with `--filter="DollarDentist"`, then `docker exec moslie-vite-local npm run types`. Expected: both green.

- [ ] **Step 7: Check the PDF by hand**

The PDF renders the same print page through headless Chromium, so it follows automatically — but confirm it, since it is the artefact the dentist actually receives:

```
Visit /invoices?from=…&to=…&dentist_id=<the dollar dentist> and download the PDF.
Confirm: dollars throughout, no ليرة, no rate column, correct totals.
```

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php app/Http/Controllers/LedgerController.php \
        resources/js/components/invoice-report.tsx resources/js/pages/ledger \
        tests/Feature/Money/DollarDentistTest.php
git commit -m "feat(invoices): bill a dollar dentist in dollars, on screen and in the PDF"
```

---

## Task 11: The commands stay correct

**Files:**
- Modify: `app/Console/Commands/RedenominateMoney.php`
- Test: `tests/Feature/Money/DollarDentistTest.php`

**Interfaces:**
- Consumes: everything above.

`ledger:rebuild` needs no code change — the postings resolve their own accounts — but its correctness here is load-bearing and must be proven. `money:redenominate` divides every money column by 100 and would silently wreck cents.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run Pest with `--filter="DollarDentist"`.
Expected: the rebuild test passes already (good — it is a regression guard); the redenominate test fails, or passes only by accident. Read the command before assuming.

- [ ] **Step 3: Exclude native dollars from redenomination**

In `app/Console/Commands/RedenominateMoney.php`, extend the class docblock with a third numbered point:

```
 * 3. Native dollar rows — a dollar dentist's orders, items and payments —
 *    are in CENTS, not lira, and this command's divisor is a lira
 *    redenomination. Dividing them would turn $500 into $5. They are
 *    excluded from every column scan and from the derived recomputation.
```

Every query over `COLUMNS` and over `orders` must exclude native dollar rows. A native row is one whose `currency` is `USD` and whose `rate` is NULL, which is exactly the third state:

```php
    /**
     * Native dollar rows hold cents, not lira, so a lira redenomination must
     * not see them. `currency = USD AND rate IS NULL` is precisely the third
     * money state (see App\Concerns\HasForeignCurrency).
     */
    private function excludeNativeDollars(\Illuminate\Database\Query\Builder $query): \Illuminate\Database\Query\Builder
    {
        return $query->where(function ($q) {
            $q->where('currency', '!=', 'USD')->orWhereNotNull('rate');
        });
    }
```

Apply it in `findIndivisible()` and in each `UPDATE`. For `orders`, which has no `rate` column, exclude on `currency` alone: `->where('currency', '!=', 'USD')`.

- [ ] **Step 4: Run the tests to verify they pass**

Run Pest with `--filter="DollarDentist"`. Expected: all green.

- [ ] **Step 5: Run the full suite**

Run Pest with no filter, paying attention to `RedenominateMoneyTest` — it must still pass unchanged, since every row in it is lira.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/RedenominateMoney.php tests/Feature/Money/DollarDentistTest.php
git commit -m "fix(money): keep the redenominator away from cents"
```

---

## Task 12: Prove the lira side never moved

**Files:**
- Create: `tests/Feature/Money/CurrencyIsolationTest.php`
- Test: itself

**Interfaces:**
- Consumes: everything above.

This is the test that actually justifies approach A. Every other test says the dollar side works; this one says the lira side is untouched by its existence.

- [ ] **Step 1: Write the test**

```php
<?php
// tests/Feature/Money/CurrencyIsolationTest.php

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\User;

/**
 * A dollar dentist must be invisible to every lira figure in the system.
 *
 * The same lira activity is run twice — once alone, once alongside a busy
 * dollar dentist — and every lira report must return the identical number.
 * This is the property that makes parallel accounts worth their extra rows:
 * not that the dollar side works, but that the lira side cannot notice it.
 */
function liraActivity(): void
{
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    test()->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [
            ['type' => 'جسر', 'quantity' => 2, 'date' => '2026-09-03',
                'price' => 400, 'selected_teeth' => []],
            ['type' => 'تلبيسة', 'quantity' => 1, 'date' => '2026-09-28',
                'price' => 150, 'selected_teeth' => []],
        ],
    ])->assertSessionHasNoErrors();

    test()->post(route('payments.store'), [
        'dentist_id' => $dentist->id, 'payment_date' => '2026-09-15', 'amount' => 300,
    ])->assertSessionHasNoErrors();
}

function dollarActivity(): void
{
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    test()->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 3, 'date' => '2026-09-05',
            'currency' => 'USD', 'original_amount' => 250_00, 'selected_teeth' => []]],
    ])->assertSessionHasNoErrors();

    test()->post(route('payments.store'), [
        'dentist_id' => $dentist->id, 'payment_date' => '2026-09-18',
        'currency' => 'USD', 'original_amount' => '400',
    ])->assertSessionHasNoErrors();
}

/** @return array<string, int> every lira figure the app reports */
function liraFigures(): array
{
    $reports = app(LedgerReports::class);

    return [
        'cash' => $reports->balance('1000'),
        'receivable' => $reports->balance('1100'),
        'revenue' => $reports->balance('4000'),
        'monthly_receipts' => $reports->cashReceipts('2026-09-01', '2026-09-30'),
        'monthly_revenue' => $reports->revenue('2026-09-01', '2026-09-30'),
        'receivables_total' => (int) $reports->receivablesByDentist()->sum(),
    ];
}

test('a dollar dentist changes no lira figure anywhere', function () {
    $this->actingAs(User::factory()->create());

    liraActivity();
    $alone = liraFigures();

    dollarActivity();
    $alongside = liraFigures();

    expect($alongside)->toBe($alone)
        // ...and the lira figures are non-trivial, or the assertion above
        // would pass on a pair of empty reports.
        ->and($alone['revenue'])->toBe(950)
        ->and($alone['cash'])->toBe(300);
});

test('the dollar dentist is meanwhile visible in the dollar accounts', function () {
    $this->actingAs(User::factory()->create());

    dollarActivity();

    $reports = app(LedgerReports::class);

    expect($reports->balance('4001'))->toBe(75000)  // 3 x $250
        ->and($reports->balance('1001'))->toBe(40000)
        ->and($reports->balance('1101'))->toBe(35000);
});
```

- [ ] **Step 2: Run it**

Run Pest with `--filter="CurrencyIsolation"`.
Expected: PASS. If the first test fails, a lira report is reading a dollar figure — find it before going further; that is the architecture's core claim breaking.

- [ ] **Step 3: Run the complete quality gate**

```bash
# Full Pest suite, on the host
S=$(mktemp -d)
mkdir -p "$S/storage/framework/views" "$S/storage/framework/cache/data" \
         "$S/storage/framework/sessions" "$S/storage/logs" "$S/bootcache"
LARAVEL_STORAGE_PATH="$S/storage" \
APP_PACKAGES_CACHE="$S/bootcache/packages.php" \
APP_SERVICES_CACHE="$S/bootcache/services.php" \
APP_CONFIG_CACHE="$S/bootcache/config.php" \
APP_EVENTS_CACHE="$S/bootcache/events.php" \
APP_ROUTES_CACHE="$S/bootcache/routes-v7.php" \
SESSION_DRIVER=array CACHE_STORE=array LOG_CHANNEL=single \
vendor/bin/pest

vendor/bin/pint --test
docker exec moslie-vite-local npm run types
```

Expected: all three green.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Money/CurrencyIsolationTest.php
git commit -m "test(money): prove a dollar dentist moves no lira figure"
```

---

## Task 13: Ship it

**Files:** none — verification and handoff.

- [ ] **Step 1: Run the migrations locally**

```bash
docker exec moslie-dental-lab-local php artisan migrate --force
```

Expected: the three new migrations run. `--force` is required — the local container runs `APP_ENV=production`.

- [ ] **Step 2: Walk the feature by hand**

With the app running at `http://dental.test`:

1. Create a dollar dentist with a dollar price list. Confirm no per-row currency toggle.
2. Create an order for him — no toggle, no rate field, no lira.
3. Record a $200 payment against a $500 order. Confirm the outstanding page shows exactly `$300` in the dollar column, and the lira total is unchanged.
4. Open the ledger's trial balance. Confirm two blocks, each balancing.
5. Download his invoice PDF. Confirm dollars throughout.
6. Try to change his currency. Confirm it is refused.

- [ ] **Step 3: Hand off the deployment**

Use the `deploy` skill. The user runs everything server-side themselves — do the local half and hand off the commands. Two things belong in the handoff notes:

- The release needs an **image rebuild** (composer/npm run at build time), then `php artisan migrate --force`.
- **No `ledger:rebuild` is required.** Every migration is additive and no existing row changes value — so none of the `--cash-on-hand` trap applies either.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| Data model — 3 migrations, 3 accounts | 1, 3, 5 |
| `Currency` enum, `AccountCode` resolvers | 1 |
| Three money states / `HasForeignCurrency` | 4 |
| Write path — forms, controller, validation | 3, 5, 6, 7 |
| Postings resolve accounts by currency | 7 |
| Read path — Outstanding, Dashboard, Finance | 9 |
| Read path — statement, invoice, PDF | 10 |
| Read path — trial balance per currency | 8 |
| Guard 1: mixed-currency entry rejected | 2 |
| Guard 2: dentist currency frozen | 3 |
| Guard 3: native row must not carry a rate | 4 |
| `ledger:rebuild` / `money:redenominate` | 11 |
| Isolation of the lira side | 12 |
| Deployment | 13 |

No gaps.

**Type consistency:** `valueInOwnCurrency(): int` is defined on the `HasForeignCurrency` trait (Task 4) and separately on `Order` (Task 5, which does not use the trait) — same name, same meaning, same return type, used identically by `OrderPosting` and `InvoiceController`. `nativeCurrency(): Currency` is the trait's protected hook, overridden in `OrderItem` and `DentistPayment` only. `AccountCode::receivableFor()` / `cashFor()` / `revenueFor()` take a `Currency` and return `string` throughout. `formatMoney(value, currency)` is defined once in Task 6 and used in Tasks 9 and 10.

**Known ordering constraint:** Task 5 makes `items.*.price` `prohibited` for a dollar dentist before Task 6 stops the form sending it. Between those two commits the order form cannot save a dollar dentist's order through the browser, though the API contract and its tests are correct. Tasks 5 and 6 should land together, or 6 immediately after 5.
