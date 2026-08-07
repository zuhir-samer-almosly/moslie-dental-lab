# Double-Entry Ledger — Design

**Date:** 2026-08-07
**Status:** Approved, ready for implementation planning

## Problem

The money layer is single-entry. Five tables each hold an `amount`, and reports
sum them independently:

- Income: `dentist_payments`
- Expenses: `employee_payments`, `material_purchases`, `expenses`
- Receivables: `orders.amount` (billable) minus `dentist_payments`

Three consequences:

1. **Nothing forces the numbers to reconcile.** `OutstandingController`
   subtracts two unrelated tables. If either drifts, nothing detects it.
2. **There is no cash balance.** The system records that money moved but never
   where it sits, so cash on hand is unknowable.
3. **The books are not auditable.** No chart of accounts, no trial balance, no
   balance sheet. An accountant has nothing to check.

Expense buckets are also defined in three places that must be hand-synced:
`Expense::CATEGORIES` (`app/Models/Expense.php:19`), `EXPENSE_CATEGORIES` in
`resources/js/types/models.ts`, and the `$categories` array in
`FinanceController::index` (`app/Http/Controllers/FinanceController.php:34`).

## Goals

- Cash box balance, always correct and reconcilable against physical cash
- Auditable books: chart of accounts, trial balance, journal
- Receivables that cannot drift — a real account balance, not a subtraction

## Non-goals

- Multi-currency. Amounts stay plain integers in a single currency.
- Period close / locking. There are no closed periods; the ledger mirrors
  current state.
- Manual journal entry UI. All entries come from domain records.
- Dentist debt write-offs. Account 5900 is seeded, but no screen creates one.
- Bank, owner, or transfer-service accounts. Only a cash box exists today; the
  `accounts` table accommodates more later without a migration.

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Ledger role | Source of truth for all money reporting | Two sources of truth would preserve the drift problem |
| Headline profit | Cash received minus cash paid | Matches the number in use today; nothing shifts on cutover |
| Revenue recognition | On order creation, any non-cancelled order | Preserves existing outstanding balances exactly |
| Corrections | Ledger mirrors current state | Edits rewrite entries; cancels remove them. No period close exists, so immutability buys noise without benefit |
| A/R subsidiary | One control account + `dentist_id` on the line | Avoids one account per dentist and a trial balance full of noise |
| Posting mechanism | Model observers | Cannot be forgotten; covers backfill, seeders, future import paths |
| Historical data | Full backfill from the first record | Old reports keep showing the same numbers |

## Data model

Three new tables. Nothing is removed from the existing schema.

```
accounts
  code         string   unique, stable, referenced in code via enum
  name         string   Arabic display name
  type         enum     asset|liability|equity|revenue|expense
  is_active    bool
  sort_order   int

journal_entries
  entry_date   date     business date, not created_at
  description  string
  source_type  string   nullable, morph to the originating model
  source_id    int      nullable
  timestamps
  index (entry_date), index (source_type, source_id)

journal_lines
  journal_entry_id  cascade on delete
  account_id
  dentist_id        nullable, subsidiary detail for A/R only
  debit             int default 0
  credit            int default 0
  index (account_id), index (dentist_id)
```

Amounts remain integers, consistent with the existing schema.

### Chart of accounts

Seeded. Replaces `Expense::CATEGORIES` as the single definition of expense
categories — validation rules, the frontend category list, and the finance
breakdown all read from this table.

| Code | Name | Type | Fed by |
|---|---|---|---|
| 1000 | الصندوق | asset | every cash movement |
| 1100 | الذمم المدينة | asset | orders, dentist payments, write-offs |
| 3000 | رأس المال | equity | owner injections, backfill balancer |
| 4000 | إيرادات الأعمال | revenue | orders |
| 5000 | الرواتب | expense | `employee_payments` |
| 5100 | المواد | expense | `material_purchases` |
| 5200 | مواصلات وسفر | expense | `expenses.category = transport` |
| 5210 | ضرائب | expense | `expenses.category = taxes` |
| 5220 | إيجار | expense | `expenses.category = rent` |
| 5230 | كهرباء وماء | expense | `expenses.category = utilities` |
| 5240 | صيانة | expense | `expenses.category = maintenance` |
| 5290 | أخرى | expense | `expenses.category = other` |
| 5900 | ديون معدومة | expense | dentist write-offs |

## Posting rules

One rule class per source model. Each answers three questions: entry date,
description, and lines.

| Source | Entry date | Debit | Credit |
|---|---|---|---|
| Order (status ≠ cancelled) | `due_date` | 1100 A/R *[dentist]* | 4000 إيرادات |
| DentistPayment | `payment_date ?? created_at` | 1000 الصندوق | 1100 A/R *[dentist]* |
| EmployeePayment | `payment_date` | 5000 الرواتب | 1000 الصندوق |
| MaterialPurchase | `purchase_date` | 5100 المواد | 1000 الصندوق |
| Expense | `expense_date` | 52xx per category | 1000 الصندوق |

Two further entry shapes are defined by the chart of accounts but have no user
interface in this pass:

| Shape | Debit | Credit | How it is created |
|---|---|---|---|
| Owner capital | 1000 الصندوق | 3000 رأس المال | `ledger:rebuild --cash-on-hand` only |
| Write-off | 5900 ديون معدومة | 1100 A/R *[dentist]* | **Deferred** — see below |

**Write-offs are deferred.** Marking a dentist's debt uncollectable is a real
need, but it requires its own model, form, and permissions, and no screen for
it was requested. Account 5900 is seeded now so that adding the feature later
is a model plus a posting rule, with no migration to the chart of accounts and
no rebuild. Until then, an uncollectable balance stays on the books.

Entry dates deliberately match the columns the current reports already bucket
by (`ReportController` uses `due_date` for orders,
`COALESCE(payment_date, created_at)` for dentist payments, and so on). Matching
them is what guarantees monthly figures do not shift on cutover.

Orders post from `orders.amount`, not the `total` items accessor, because
`amount` is what every existing report sums.

### Structure

```
app/Ledger/
  Ledger.php          post() / sync(); wraps a DB transaction;
                      throws UnbalancedEntryException when debits ≠ credits
  AccountCode.php     enum of seeded account codes
  Posting.php         interface: date(), description(), lines()
  Postings/
    OrderPosting.php
    DentistPaymentPosting.php
    EmployeePaymentPosting.php
    MaterialPurchasePosting.php
    ExpensePosting.php
app/Observers/LedgerObserver.php    saved/deleted → Ledger::sync($model)
app/Models/Account.php
app/Models/JournalEntry.php
app/Models/JournalLine.php
```

Each posting class is independently testable: given a model instance, it
returns lines. It touches no controller, no request, and no database.

### Correction semantics

`Ledger::sync($source)` deletes the source's existing entries and rewrites them
from current state, inside one transaction.

- Order edited → entry rewritten with the new amount
- Order cancelled → entry removed; nothing owed, nothing earned
- Order un-cancelled → entry written again
- Any source deleted → entries removed

The domain record remains the history of what happened; the ledger states what
is currently true.

### Core invariant

`Ledger::post()` throws if debits do not equal credits. An unbalanced entry is
a bug, not a validation failure, and must be impossible to persist.

## Reporting changes

All money totals move to the ledger. Detail rows continue to come from the
domain tables, which carry names, notes, and other fields the ledger does not.

- **`OutstandingController`** — the `withSum` subtraction becomes an A/R
  balance grouped by `dentist_id`.
- **`FinanceController`** — the hardcoded `$categories` array is replaced by
  every 5xxx account with movement in the month, one row each. Headline
  المقبوضات is cash-account debits arising from dentist payments; الأعمال
  المنجزة (4000 credits) and الذمم المدينة (1100 balance) appear as secondary
  lines.
- **`ReportController`** — keeps its date-range resolution and its detail
  lists from domain tables; only the totals block switches to the ledger.
- **`InvoiceController`** — the opening-balance calculation becomes the A/R
  balance before the period start.
- **`DashboardController`** — KPIs read account balances.

## New pages

Four read-only Inertia pages under a `ledger/` route prefix, following the
existing Arabic/RTL page patterns.

| Route | Page | Contents |
|---|---|---|
| `ledger/statement` | كشف حساب | Per-dentist running statement, date-filtered, printable via the existing signed-URL Chromium path used by the invoice PDF |
| `ledger/cash` | الصندوق | Current cash balance and every movement in and out |
| `ledger/trial-balance` | ميزان المراجعة | Debit and credit totals per account, proving the books balance |
| `ledger/journal` | قيود اليومية | Paginated entries with both sides, filterable by date and account |

## Backfill

Migrations create the three tables and seed the chart of accounts. The backfill
itself is an artisan command, `php artisan ledger:rebuild`, not a migration —
it must be rerunnable so that a corrected posting rule means a rebuild rather
than a hand-patch.

The command wipes and rebuilds all entries from the domain tables in one
transaction, then prints a verification report:

- Trial balance total (must be zero)
- Per-dentist comparison: old outstanding formula vs new A/R balance
- Computed cash balance

A negative computed cash balance is expected if historical expenses exceed
recorded dentist payments — it means money entered the business unrecorded.
`ledger:rebuild --cash-on-hand=N` posts the difference to 3000 رأس المال so the
cash box starts at the real counted figure.

## Testing

- One unit test per posting rule, asserting the exact lines produced
- `Ledger::post()` throws on an unbalanced entry
- Order lifecycle feature test: create → edit → cancel → un-cancel → delete,
  with A/R asserted at each step
- **Parity tests**: on a seeded dataset, ledger-derived outstanding equals the
  old subtraction formula, and ledger-derived finance income equals the old
  `FinanceController` figure. These prove the cutover does not move any number.
- Trial balance sums to zero after any sequence of operations

Run via the `run-checks` skill (host execution with storage/cache redirects).

## Rollout

Production carries live data.

1. Take a fresh database backup (`backups` skill) — do not rely on the nightly
   Drive backup alone
2. Screenshot the outstanding and finance pages for before/after comparison
3. Rebuild the image and deploy (`deploy` skill — a code change requires an
   image rebuild, never a restart)
4. Run migrations, then `php artisan ledger:rebuild` in the `app` container
5. Read the verification report; supply `--cash-on-hand` and rerun
6. Compare outstanding and finance against the screenshots

Local work and command hand-off follow the usual split: the VPS commands are
run by the user.

## Risks

| Risk | Mitigation |
|---|---|
| Backfill produces balances that disagree with today's pages | The rebuild prints a per-dentist comparison before you accept it; parity tests catch it earlier, in CI |
| Observers fire during factories and seeders, changing test expectations | Intended — seeded data should post. Existing tests that assert money totals are reviewed as part of the work |
| A posting rule is wrong and entries are already live | `ledger:rebuild` is rerunnable and idempotent |
| Cash box starts negative | Expected case, handled by `--cash-on-hand` |
