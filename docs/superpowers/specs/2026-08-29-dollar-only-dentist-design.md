# Dollar-Only Dentist — Design

**Date:** 2026-08-29
**Status:** Approved, ready for implementation planning

## Problem

One dentist works entirely in US dollars. He is quoted in dollars, billed in
dollars, and pays in physical dollars that the lab keeps as dollars. Nothing
about his account should involve the lira: not his price list, not his order
lines, not his invoice, and — the part that matters most — not his balance.

The system cannot express this today. The lira is the hard currency of record:

- `HasForeignCurrency` (`app/Concerns/HasForeignCurrency.php`) converts any
  non-SYP amount to lira **on write**, at a rate stored on the row, and
  **requires** both `original_amount` and `rate` or it throws
  `MissingRateException`.
- `journal_lines.debit` / `.credit` are bare integers of whole lira. Every
  report — `LedgerReports::balance`, `receivablesByDentist`,
  `movementBetween`, `dentistStatement` — reads them as lira.
- Dollars exist only as *provenance* beside the lira: `ForeignOrigin` renders
  "$100 × 13", `ApproxUsd` renders "≈ $208" and is explicitly documented as
  approximate and never stored.

So a dollar figure today is either converted away, or decoration next to a
lira figure. Either way the dentist's **balance** is a lira number. If he is
quoted $500 and pays $200 a month later at a different rate, the lira
receivable left behind does not read back as $300 at any rate you pick. For a
dentist whose entire relationship with the lab is denominated in dollars,
that is the wrong answer, and no amount of display formatting fixes it.

This supersedes the "Multi-currency" non-goal in
`2026-08-07-double-entry-ledger-design.md`.

## Goals

- A dollar dentist's balance is exact dollar arithmetic. $500 ordered, $200
  paid, $300 owed — forever, with no exchange rate anywhere in the path.
- His orders, price list, invoice, statement and PDF show dollars only. No
  lira figure, no rate column, no "≈ $" approximation.
- Physical dollars are tracked as a real second cash box.
- Existing lira dentists, reports and totals are **provably** unaffected.

## Non-goals

- **Spending dollars.** The dollar box only fills. No dollar-denominated
  materials, salaries or expenses, and no selling dollars for lira. Both would
  introduce FX gain/loss, which is deliberately out of scope.
- **Converting an existing dentist.** The dollar dentist is created new, with
  no lira history. Switching a dentist's currency mid-history is explicitly
  blocked rather than supported.
- **Blended totals.** No report ever adds lira and dollars into one number.
- Replacing the existing convert-on-write path. A *lira* dentist handing over
  dollars keeps working exactly as it does today (commit `dac332d`); that is a
  different, still-valid case.

## Decisions

Settled with the owner before design:

| Question | Decision |
|---|---|
| What is his balance? | True dollars end to end; no rate touches his account |
| Where do his dollars go? | Stay as physical dollars — a real second cash box |
| Do dollars ever leave it? | No. It only fills, for now |
| How do lab-wide reports show it? | Its own column, never merged with lira |
| Does he have lira history? | No. New dentist, dollars from his first order |

## Architecture

**Parallel dollar accounts in the same ledger.** `accounts` gains a
`currency`, and three USD-denominated accounts join the chart. A dollar order
debits `1101` and credits `4001`; a dollar payment debits `1001` and credits
`1101`.

The invariant that makes this safe:

> **An account holds exactly one currency. An entry never crosses currencies.**
> A SYP account holds whole lira; a USD account holds cents.

Two consequences fall out of it:

1. `Ledger::post()`'s debit-equals-credit check works **unchanged**, because
   both sides of every entry are already the same currency and the same unit.
2. Every existing report keys on an account **code** — `balance('1100')`,
   `movementBetween('1000','1100')`, `receivablesByDentist()`. None of them
   can pick up a dollar figure, because no dollar figure lives in those
   accounts. The lira reports keep returning today's answers with no changes
   to their queries.

### Approaches rejected

**A `currency` column on `journal_lines`, sharing the existing accounts.**
Three fewer accounts, but every report query then needs a `where currency = ?`
filter, and omitting one adds cents to lira silently — a wrong number on a
report, not an error. These are raw SQL queries, the suite runs SQLite and
production runs MySQL, and this codebase has already shipped one such silent
divergence (`raw-sql-untested-by-suite`). The safe answer should be the
default, not a filter someone must remember.

**A separate dollar sub-ledger outside `journal_entries`.** Fastest to write
and leaves the existing ledger untouched, but duplicates the posting and
reporting logic in a second place that can drift — the exact problem the
ledger was built to eliminate.

## Data model

Three additive migrations. No existing row changes value.

| Migration | Change |
|---|---|
| `add_currency_to_accounts_table` | `accounts.currency` char(3) default `'SYP'`, plus the three new chart rows |
| `add_currency_to_dentists_table` | `dentists.currency` char(3) default `'SYP'` |
| `add_currency_to_orders_table` | `orders.currency` char(3) default `'SYP'`, `orders.original_amount` int nullable |

New accounts, seeded by the migration the way the rest of the chart is:

| Code | Name | Type | Currency |
|---|---|---|---|
| `1001` | صندوق الدولار | asset | USD |
| `1101` | ذمم الأطباء بالدولار | asset | USD |
| `4001` | إيرادات بالدولار | revenue | USD |

`orders` mirrors the `currency` / `original_amount` pair `order_items`,
`dentist_payments` and the expense tables already carry, so an order row stays
self-describing and `OutstandingController`'s `withSum(...)` keeps working.
`orders` needs no `rate` column: a native-dollar order has no rate, and a lira
order's per-line rates already live on `order_items`.

### New code

- **`App\Money\Currency`** — enum `SYP` / `USD`. Knows its minor unit.
- **`AccountCode::cashFor()` / `receivableFor()` / `revenueFor()`** — the
  single place currency maps to account code. Nothing downstream hardcodes
  `'1101'`.

## The three money states

`HasForeignCurrency` currently expresses two states. It gains a third.

| State | `currency` | `original_amount` | `rate` | lira column |
|---|---|---|---|---|
| Lira | `SYP` | NULL | NULL | the lira amount |
| Dollars converted (existing) | `USD` | cents | set | converted lira |
| **Native dollars (new)** | `USD` | **cents** | **NULL** | **0** |

A native-dollar row is decided by its owner, not by a column on itself:
`OrderItem` asks its order's dentist, `DentistPayment` asks its dentist. If
that dentist is a dollar dentist, no conversion runs, the cents stand as the
row's value, and the lira column is forced to `0`.

Setting the lira column to `0` is not a fudge — he genuinely owes zero lira —
and it is the property that keeps every untouched lira `SUM()` in the codebase
correct without being edited.

`rate` NULL distinguishes native dollars from converted dollars *on the row*,
but the authority is always the dentist's `currency`. The two are kept from
diverging by a guard (below) that throws if a native-dollar row arrives
carrying a rate.

## Write path

- **`Dentist`** — `currency` becomes fillable, with an `isDollar()` helper.
  The dentist form gets a currency choice, disabled once he has ledger lines.
- **Price list** — a dollar dentist's `price_list` entries are all USD. The
  per-row currency picker is removed from `price-list-editor.tsx` for him, and
  the model's setter forces USD (not `normalisePriceList`, which is static and
  has no dentist context).
- **Order form** (`order-form.tsx`) and **payment form** — for a dollar
  dentist there is no currency toggle and no rate field at all. He types
  dollars. `CurrencyAmountField`'s "يُسجَّل في الحساب: … ليرة" preview has
  nothing to preview and is replaced by a plain dollar total.
- **`OrderController::store/update`** — already recomputes `orders.amount`
  from the items on every write; it recomputes `orders.original_amount` the
  same way.
- **`OrderPosting` / `DentistPaymentPosting`** — resolve their account codes
  through `Currency`, and read the row's value in its own currency.
  `shouldPost()` checks that value rather than `amount`, which for a dollar
  order is legitimately `0`.
- **Validation** (`MoneyValidationRules`, `OrderValidationRules`, the Store /
  Update requests) — a dollar dentist's line **requires** `original_amount`
  and **rejects** both `rate` and a lira `amount`. Enforced in the Form
  Request, not merely hidden in the UI.

## Read path

Every report already routes through `LedgerReports`, so the dollar column is a
second call with a different account code — not a second implementation.

- **Outstanding** — `receivablesByDentist()` takes a currency. The page shows a
  ليرة column and a دولار column; each dentist appears in his own. The two
  totals are `balance('1100')` and `balance('1101')`, never summed together.
  The page's two display-only columns need the same treatment: its
  `withSum(orders, 'amount')` / `withSum(payments, 'amount')` return `0` for a
  dollar dentist, so they sum `original_amount` for him instead. The
  `outstanding` figure itself is the ledger's either way.
- **Dashboard & Finance** — `outstanding`, `cash_balance`, `income` and
  `earned` each become a pair; the dollar side of `income` is
  `movementBetween('1001','1101')`. Expenses stay lira-only, so the dollar
  column shows cash in with a dash where expenses would be. That is the truth
  about a box that only fills, not an omission.
- **Statement** — `dentistStatement()` picks its receivable code from the
  dentist. For a dollar dentist the `ForeignOrigin` ("$100 × 13") column
  disappears — there is no rate — and `ApproxUsd` is **suppressed explicitly
  by the dentist's currency**. It must not be left to `ApproxUsd`'s own
  no-rate check: rates exist in the table for the lira dentists, so it would
  happily divide his dollar balance by one and print nonsense.
- **Invoice, print view and PDF** — `buildReport` carries a currency into the
  payload and `invoice-report.tsx` renders `$` with two decimals instead of
  ليرة. With a dentist selected (how his invoice is actually printed) the
  report is cleanly single-currency. With **no** dentist selected it spans
  both kinds: it stays grouped by dentist as today and gets **two total
  rows** — مجموع الليرة and مجموع الدولار. `closingRate` remains for the lira
  dentists and is unused for him.
- **Trial balance** — `trialBalance()` returns each row's currency and the page
  renders one block per currency, each balancing on its own. This is where a
  violation of the core invariant would actually surface.
- **`accountLines()`** — already per-code; works with no change.

## Guard rails

The architecture is only safe while its invariant holds, so the invariant is
enforced, not assumed.

1. **`Ledger::post()` rejects a mixed-currency entry** — a sibling of the
   existing `UnbalancedEntryException`. Makes "an entry never crosses
   currencies" impossible rather than merely intended.
2. **A dentist's currency is frozen once he has any ledger line** — in the
   Form Request and in the model. Blocks the mid-history conversion that is an
   explicit non-goal.
3. **`HasForeignCurrency` throws if a native-dollar row carries a rate** — the
   third state cannot quietly collapse into the second.

## Commands

- **`ledger:rebuild`** works unchanged, because postings resolve their own
  accounts — but gets a test proving a dollar dentist's balance reconciles
  exactly after a rebuild, since rerunnability is load-bearing.
  `--cash-on-hand` stays lira-only; the dollar box opens at zero because the
  dentist is new.
- **`money:redenominate`** gains an explicit skip for USD accounts and
  native-dollar rows. It is a historical one-off, but it divides by 100 and
  would silently wreck cents.

## Testing

`tests/Feature/Money/DollarDentistTest.php`:

- a dollar order posts to `1101` / `4001` in cents and touches `1100` / `4000`
  not at all
- $500 ordered, $200 paid ⇒ **exactly** $300 owed, with no rate in the path
- **existing lira totals are identical with a dollar dentist present** — the
  test that actually proves isolation
- his invoice and statement render dollars: no lira, no rate column
- the trial balance balances within each currency
- `ledger:rebuild` reproduces his balance exactly
- validation refuses a rate or a lira amount on his lines
- his currency cannot change once he has entries
- an order straddling a month boundary, in dollars — per the
  `orders-are-appended-to-over-weeks` note

Run through the `run-checks` skill (Pest on the host with the storage/cache
redirects), plus `npm run types` for the changed Inertia props.

## Deployment

Unusually calm. Every migration is additive, the three accounts are inserted
by migration, and no existing row changes value — so **no `ledger:rebuild` is
required**, and with it none of the `--cash-on-hand` trap. Normal image
rebuild plus `php artisan migrate`, via the `deploy` skill.
