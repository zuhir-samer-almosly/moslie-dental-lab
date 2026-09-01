# moslie-dental-lab

Order and finance management for a dental laboratory, with an Arabic (RTL) interface.

A lab receives orders from dentists, produces the work, and bills for it. This app tracks
that cycle end to end — dentists, orders and their line items, payments in, salaries and
material purchases going out — and rolls all of it into invoices, outstanding balances,
and monthly financial reports backed by a double-entry ledger.

Built with Laravel 12, Inertia.js 2, React 19 and TypeScript. Runs on Docker.

> Built for one specific lab, and shared in case the shape of it is useful to someone
> else. It is single-tenant by design (see [Design decisions](#design-decisions)), so it
> is a reference implementation rather than a product you can hand to several customers.

## Features

- **Dentists** — contact details plus a per-dentist price list
- **Orders** — line items, quantities, per-item pricing, status tracking; one running
  order per dentist can span weeks, with items appended over time
- **Money in** — dentist payments, invoices, outstanding balances per dentist
- **Money out** — employees and salary payouts, material purchases, general expenses
- **Multi-currency** — a dentist can be billed in Syrian lira or US dollars; the two
  never mix into a single total
- **Double-entry ledger** — every money event posts balanced journal entries, and all
  reported figures are read from the ledger rather than summed off domain tables
- **Arabic RTL UI** throughout (`lang/ar/`), with dark mode

## Stack

| Layer    | Choice                                                     |
| -------- | ---------------------------------------------------------- |
| Backend  | Laravel 12, Fortify (auth + 2FA), Wayfinder (typed routes) |
| Frontend | React 19, TypeScript strict, Inertia.js 2, Vite 7          |
| Styling  | Tailwind CSS 4, Radix UI primitives, Lucide icons          |
| Data     | MySQL in production, SQLite in tests                       |
| Tests    | Pest 4                                                     |

## Quick start

Requires Docker. There is no Herd/Valet path — the containers are the dev environment.

```bash
git clone git@github.com:zuhir-samer-almosly/moslie-dental-lab.git
cd moslie-dental-lab
cp .env.example .env

./docker-start-local.sh     # app + db + redis + vite, served at http://dental.test
```

The script runs migrations and creates an admin account. It defaults to
`admin@dental.test` / `password`; override with `ADMIN_EMAIL` and `ADMIN_NAME`.

Vite runs in its own container and writes `public/hot`, so `@vite` serves hot-reloaded
assets. Run artisan **inside** the container:

```bash
docker compose -f docker-compose.local.yml exec app php artisan migrate
```

**Public registration is disabled.** Accounts are created from the CLI:

```bash
docker compose -f docker-compose.local.yml exec app php artisan app:create-user
```

## Tests and checks

Pest runs on the **host**, not in the container: the app image is built `--no-dev` on
PHP 8.3 and has no Pest or Pint. The host also needs its writes redirected, because
`storage/` and `bootstrap/cache` are bind-mounted and owned by the container user, and a
cached config there would override the SQLite test settings.

```bash
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
```

Tests use in-memory SQLite and never touch real data. The rest:

```bash
vendor/bin/pint --test                          # PHP style (drop --test to fix)
docker exec moslie-vite-local npm run types     # TypeScript (node_modules is a volume)
npm run lint                                    # ESLint
npm run format                                  # Prettier
```

Note that the suite runs on SQLite while production is MySQL, so raw SQL can pass here
and still fail on the server.

## Design decisions

Things that look like bugs but are deliberate:

- **Single-tenant.** One shared dataset, no per-user scoping, registration disabled. The
  lab is the only user.
- **The ledger is the source of truth.** Reporting reads `App\Ledger\LedgerReports`,
  never `SUM(amount)` on a domain table. Entries are written by an observer on the five
  money models and mirror current state: editing rewrites, cancelling or deleting
  removes. `Ledger::post()` refuses an unbalanced entry.
- **Expense categories are database rows**, not a PHP or TS constant. Adding one is a
  single `accounts` row carrying a `category_key`.
- **`recieved` is misspelled** in the `orders.status` enum. It is baked into the column
  and the validation rules, so correcting it needs a data migration — not a rename.
- **Amounts were redenominated** (divided by 100) on 2026-08-09. Anything from before
  that date is 100× the current scale.

## Deployment

Runs on a DigitalOcean VPS behind Caddy, with automatic HTTPS. A code change requires an
image **rebuild** — `composer install` and `npm run build` happen at build time, so a
restart alone ships nothing. Set `ACME_EMAIL` in the production environment for
Let's Encrypt notices. Full runbook in [`DEPLOYMENT.md`](DEPLOYMENT.md); backups to
Google Drive are covered in [`BACKUPS.md`](BACKUPS.md).

## License

[MIT](LICENSE).
