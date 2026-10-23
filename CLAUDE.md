# CLAUDE.md

## Project Overview

This is a dental lab order management system built with Laravel 12, Inertia.js 2.0, React 19, and TypeScript. The UI is **Arabic and RTL** (`<html lang="ar-SY" dir="rtl">`, translations in `lang/ar/`). It manages dentists/orders/payments plus an expense side (employees, salaries, material purchases) and rolls both up into financial reports (finance, invoices, outstanding balances).

## Development Commands

> **Environment:** This machine is Linux/WSL2 and runs the app via **Docker**, *not* Laravel Herd. The Herd instructions that previously lived here did not apply — ignore any leftover Herd references in older docs. See `docker-compose.local.yml` and the `local-runs-via-docker-not-herd` project memory.

### Local dev (Docker)
```bash
./docker-start-local.sh                                  # Bring up app + db + redis + vite (http://dental.test)
docker compose -f docker-compose.local.yml up -d         # Equivalent manual start
docker compose -f docker-compose.local.yml exec app php artisan migrate   # Run artisan INSIDE the container
```
The Vite dev server runs in its own container and writes `public/hot`, so the `@vite` directive serves hot-reloaded assets. `composer dev` / `composer setup` (Herd-era helpers) are not the local workflow here.

### Testing & type checks
Pest must run **on the host** (the container is read-only PHP 8.3; the host is 8.4) with storage/cache redirected to a writable path — the bare `php artisan test` fails here. Use the **`run-checks` skill**, which encodes the correct invocation, or replicate its env redirects. Other checks:
```bash
php artisan test --filter=TestName   # Single test (with the run-checks env redirects)
npm run types                        # TypeScript type checking (tsc --noEmit)
```

### User accounts
Public registration is **disabled** (single shared dataset, no per-user scoping). Create/reset logins via:
```bash
php artisan app:create-user          # or use the manage-users skill
```

## Architecture

### Domain model gotchas

**Order** (`app/Models/Order.php`) status enum is `'pending'`, `'completed'`, `'cancelled'`, `'recieved'`.
- **Known issue:** `recieved` is a misspelling of `received`. It is baked
  into the DB `enum` column and the `in:` validation rules, so renaming it
  requires a data migration plus updating the requests and frontend. Left
  as-is for now; don't "fix" the spelling piecemeal.
- Cancelled orders are excluded from all money totals via `Order::billable()`.

### Account / JournalEntry / JournalLine (`app/Models/`)

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
- Rebuilding entries from the domain tables has two mandatory flags and a
  destructive failure mode — use the **`ledger-rebuild` skill**.

### Reporting layer

Several read-only controllers aggregate the models above into reports; no models of their own:
- **DashboardController** — top-level KPIs.
- **InvoiceController** (`invoices`) — per-dentist billing built on `Order::billable()`.
- **OutstandingController** (`outstanding`) — unpaid balances (orders billed minus payments), also via `billable()`.
- **FinanceController** (`finance`) — monthly cash in vs. cash out vs. net, read from `LedgerReports`. Expense rows are whichever expense accounts moved that month — add a category by adding an `accounts` row with a `category_key`, not by editing the controller.

`Order::billable()` still scopes which orders post to the ledger (cancelled
ones do not), but money totals come from `LedgerReports`, not from summing
orders directly. When adding a new CRUD section, follow the existing pattern via the **`add-section` skill** (Arabic/RTL list + create/edit + optional finance roll-up).

### Testing Strategy

- Run tests via the `run-checks` skill (host execution with storage/cache env redirects), not bare `php artisan test`

## Key Conventions

- **CSS**: Tailwind utility classes, no custom CSS unless necessary

## Notes

- **Local** dev runs in Docker via `docker-compose.local.yml` (`./docker-start-local.sh`, served at `http://dental.test`). Run artisan/migrations inside the `app` container.
- **Production** runs on a Contabo VPS via Docker (`docker-compose.yml`) at `dental-lab.zoher-moslie.me`, in `/var/www/moslie-dental-lab` as the `deploy` user; a code change requires an image **rebuild** (composer install and `npm run build` happen at build time), never just a restart. Use the **`deploy` skill**; full reference in `DEPLOYMENT.md`.
- Single-tenant by design: one shared dataset, no per-user data scoping, public registration disabled.
- Nightly off-site DB backups go to Google Drive (spatie/laravel-backup); see `BACKUPS.md` and the `backups` skill.
