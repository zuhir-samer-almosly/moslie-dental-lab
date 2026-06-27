---
name: run-checks
description: Run this project's full quality gate — PHP tests (Pest), PHP style (Pint), and TypeScript types. Use before committing/pushing, when asked to "run the tests", "check it builds", or to verify a change. Encodes the non-obvious Docker/host split that makes the naive `php artisan test` fail here.
---

# Running checks for moslie-dental-lab

This project runs locally via **Docker** (not Herd, despite CLAUDE.md). The setup
has a gotcha that breaks the obvious commands — follow this exactly.

## Why the obvious commands fail
- `php artisan test` **in the container** → "Command not defined": the app image
  is built `--no-dev` (PHP 8.3; the dev tools need PHP 8.4), so Pest/Pint aren't there.
- `php artisan` / `vendor/bin/pest` **on the host** → fails two ways: (1) the shared
  `bootstrap/cache/{packages,services}.php` references `laravel/passkeys`, which is
  only in the container's vendor, not the host's; (2) `storage/` and `bootstrap/cache`
  are bind-mounted and owned by the container user (uid 82), so the host user can't
  write compiled views / logs / sessions there.

The host **does** have PHP 8.4 + full dev deps (Pest, Pint). The fix: run Pest on the
host with writes redirected to a scratchpad and the passkeys cache bypassed via the
framework's env hooks (`LARAVEL_STORAGE_PATH`, `APP_PACKAGES_CACHE`, `APP_SERVICES_CACHE`).

## 1. PHP tests (Pest) — run on the HOST
Tests use in-memory sqlite (see `phpunit.xml`), so they never touch real data.

```bash
S=$(mktemp -d)
mkdir -p "$S/storage/framework/views" "$S/storage/framework/cache/data" \
         "$S/storage/framework/sessions" "$S/storage/logs" "$S/bootcache"
LARAVEL_STORAGE_PATH="$S/storage" \
APP_PACKAGES_CACHE="$S/bootcache/packages.php" \
APP_SERVICES_CACHE="$S/bootcache/services.php" \
SESSION_DRIVER=array CACHE_STORE=array LOG_CHANNEL=single \
vendor/bin/pest
```

Filter to specific tests with `vendor/bin/pest --filter="EmployeeTest|FinanceTest"`.

## 2. PHP style (Pint) — run on the HOST
Pint is a standalone binary; it doesn't boot Laravel, so no env tricks needed:
```bash
vendor/bin/pint --test    # check only; drop --test to auto-fix
```

## 3. TypeScript types — run in the VITE CONTAINER
`node_modules` lives in a container volume, so run there:
```bash
docker exec moslie-vite-local npm run types
```

## Migrations (when a change adds tables)
Run in the app container (APP_ENV=production → needs `--force`; additive migrations
are safe):
```bash
docker exec moslie-dental-lab-local php artisan migrate --force
```

All three checks should be green before committing. The full container/host
rationale is in the agent memory note `local-runs-via-docker-not-herd`.
