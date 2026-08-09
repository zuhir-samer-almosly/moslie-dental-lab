# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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

### Linting & Formatting
```bash
composer lint  # Run Pint to fix PHP code style
composer test:lint  # Check PHP code style without fixing
npm run lint  # Fix JavaScript/TypeScript with ESLint
npm run format  # Format frontend code with Prettier
npm run format:check  # Check frontend formatting
```

### Database
```bash
php artisan migrate  # Run migrations
php artisan migrate:fresh --seed  # Fresh database with seeders
php artisan db:seed --class=DentistSeeder  # Run specific seeder
```

## Architecture

### Backend Stack
- **Framework**: Laravel 12 with Inertia.js for server-side rendering
- **Authentication**: Laravel Fortify with two-factor authentication support
- **Routing**: Laravel Wayfinder for type-safe routing between backend and frontend
- **Testing**: Pest 4.x with RefreshDatabase trait for Feature tests
- **Code Style**: Laravel Pint (parallel mode enabled)

### Frontend Stack
- **Framework**: React 19 with TypeScript (strict mode)
- **Build Tool**: Vite 7 with React Compiler plugin enabled
- **Styling**: Tailwind CSS 4.0
- **Components**: Radix UI primitives with custom shadcn/ui-style components in `resources/js/components/ui/`
- **Forms**: Headless UI components
- **Icons**: Lucide React
- **Path Aliases**: `@/*` maps to `resources/js/*`

### Domain Model

The application centers around dental lab order management:

**Dentist** (`app/Models/Dentist.php`)
- Has many Orders and DentistPayments
- Fields: name, phone (unique), address, price_list (JSON)

**Order** (`app/Models/Order.php`)
- Belongs to Dentist
- Has many OrderItems (implicit via order_items table)
- Fields: dentist_id, due_date, amount, status, notes, meta (JSON)
- Status enum: 'pending', 'completed', 'cancelled', 'recieved'
  - **Known issue:** `recieved` is a misspelling of `received`. It is baked
    into the DB `enum` column and the `in:` validation rules, so renaming it
    requires a data migration plus updating the requests and frontend. Left
    as-is for now; don't "fix" the spelling piecemeal.
  - Cancelled orders are excluded from all money totals via `Order::billable()`.
- Foreign key cascades on delete

**OrderItem** (`app/Models/OrderItem.php`)
- Belongs to Order
- Fields: order_id, type, quantity, price, notes, meta (JSON)
- Meta attribute is automatically JSON decoded via accessor

**DentistPayment** (`app/Models/DentistPayment.php`)
- Belongs to Dentist
- Fields: dentist_id, amount (income side)

**Employee** (`app/Models/Employee.php`)
- Has many EmployeePayments
- Fields: name, role, phone, notes, is_active

**EmployeePayment** (`app/Models/EmployeePayment.php`)
- Belongs to Employee; represents a salary payout (expense side)
- Fields: employee_id, amount, payment_date, notes

**MaterialPurchase** (`app/Models/MaterialPurchase.php`)
- Standalone purchase record (expense side)
- Fields: name, supplier, quantity, amount, purchase_date, notes

**Expense** (`app/Models/Expense.php`)
- Standalone general-expense record (expense side)
- Fields: category, description, amount, expense_date, notes

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
- Rebuild all entries from the domain tables with
  `php artisan ledger:rebuild --force` (rerunnable). **`--force` is required
  on both local and production containers** — they run `APP_ENV=production`,
  which puts the command behind Laravel's destructive-command guard, and a
  non-interactive `docker compose exec` answers it with the default (no) and
  aborts with "Command Cancelled".
- `--cash-on-hand=N` posts the gap between the real counted cash and the
  computed cash box to owner capital, dated the day before the ledger's first
  entry. **Supply it on every rebuild, not just the first** — a rerun without
  it deletes the opening balance (the command warns loudly when that happens).

### Reporting layer

Several read-only controllers aggregate the models above into reports; no models of their own:
- **DashboardController** — top-level KPIs.
- **InvoiceController** (`invoices`) — per-dentist billing built on `Order::billable()`.
- **OutstandingController** (`outstanding`) — unpaid balances (orders billed minus payments), also via `billable()`.
- **FinanceController** (`finance`) — monthly cash in vs. cash out vs. net, read from `LedgerReports`. Expense rows are whichever expense accounts moved that month — add a category by adding an `accounts` row with a `category_key`, not by editing the controller.

`Order::billable()` still scopes which orders post to the ledger (cancelled
ones do not), but money totals come from `LedgerReports`, not from summing
orders directly. When adding a new CRUD section, follow the existing pattern via the **`add-section` skill** (Arabic/RTL list + create/edit + optional finance roll-up).

### Frontend Structure

**Pages** (`resources/js/pages/`)
- Inertia pages are auto-resolved from `./pages/{name}.tsx`
- Auth pages: login, register, forgot-password, reset-password, verify-email, two-factor-challenge
- Settings pages: profile, password, two-factor, appearance
- Domain pages (one dir each): dentists, orders, payments, employees, employee-payments, material-purchases
- Reporting pages: dashboard, finance, invoices, outstanding

**Components** (`resources/js/components/`)
- `app-shell.tsx`: Root layout wrapper supporting header or sidebar variants
- `app-sidebar.tsx`, `app-header.tsx`: Main navigation components
- `ui/`: Reusable UI components built on Radix UI primitives
- Component imports use `@/` alias for clean paths

**Layouts**: The app supports two layout variants via `AppShell`:
- `header`: Header-based layout for marketing/auth pages
- `sidebar`: Collapsible sidebar layout for authenticated app pages

### Routing

- Backend routes defined in `routes/web.php` and `routes/settings.php`
- Wayfinder plugin generates type-safe route helpers for frontend
- Use Inertia's `router.visit()` or `Link` component for navigation

### Testing Strategy

- Feature tests in `tests/Feature/` use RefreshDatabase trait
- Test structure mirrors app structure: Auth/, Settings/
- Tests are configured in `tests/Pest.php` with custom expectations available
- Run them via the `run-checks` skill (host execution with storage/cache env redirects), not bare `php artisan test`

## Key Conventions

- **Controllers**: Use Form Requests for validation (e.g., `StoreDentistRequest`, `UpdateDentistRequest`)
- **Factories**: All models have factories in `database/factories/`
- **Seeders**: All models have seeders in `database/seeders/`
- **Policies**: Authorization policies in `app/Policies/`
- **TypeScript**: Strict mode enabled, prefer explicit types
- **React**: Uses React 19 with the React Compiler enabled for automatic optimization
- **CSS**: Tailwind utility classes, no custom CSS unless necessary

## Notes

- **Local** dev runs in Docker via `docker-compose.local.yml` (`./docker-start-local.sh`, served at `http://dental.test`). Run artisan/migrations inside the `app` container.
- **Production** runs on a DigitalOcean VPS via Docker (`docker-compose.yml`) at `dental-lab.zoher-moslie.me`; a code change requires an image **rebuild** (composer install and `npm run build` happen at build time), never just a restart. Use the **`deploy` skill**; full reference in `DEPLOYMENT.md`.
- Single-tenant by design: one shared dataset, no per-user data scoping, public registration disabled.
- Nightly off-site DB backups go to Google Drive (spatie/laravel-backup); see `BACKUPS.md` and the `backups` skill.
- Laravel Boost MCP works only through the project `.mcp.json` env overrides (`APP_ENV=local`, `DB_HOST=127.0.0.1`, array session/cache); the plugin default is broken here.
- Dark mode support is built-in via `resources/js/hooks/use-appearance`.
- SSR support is available but optional.

