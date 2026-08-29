# moslie-dental-lab

A dental laboratory order and finance management system with an Arabic (RTL) user interface.

This repository contains a Laravel + Inertia + React application used to manage dentists, laboratory orders, payments, and expenses — with financial reporting and invoices.

## Highlights

- Backend: Laravel 12 with Fortify for authentication
- Frontend: React 19 + TypeScript, Inertia.js, Vite
- Styling: Tailwind CSS, Radix UI primitives
- Dev environment: Docker (see Quick start)

## Features

- Dentist and order management (orders, items, status tracking)
- Payments and receipts for dentists, employees, and suppliers
- Expense tracking and financial reports (invoices, outstanding balances)
- RTL Arabic UI; translations under `lang/ar/`

## Quick start (local, Docker)

Run the app locally with Docker (recommended):

```bash
./docker-start-local.sh                                  # Bring up app + db + redis + vite (http://dental.test)
# or
docker compose -f docker-compose.local.yml up -d

# Run migrations inside the container
docker compose -f docker-compose.local.yml exec app php artisan migrate
```

Notes:
- The Vite dev server runs in its own container and writes `public/hot` so `@vite` serves hot-reloaded assets.
- Public registration is disabled by default. Use `php artisan app:create-user` to add or reset test accounts.

## Testing & checks

Pest tests and runtime checks can be run with the repository helpers:

```bash
./run-checks
npm run types   # TypeScript type checks
composer lint   # PHP lint (Pint)
npm run lint    # ESLint
npm run format  # Prettier
```

## Notes

- The app is RTL/Arabic: `<html lang="ar-SY" dir="rtl">` and translations live under `lang/ar/`.
- There is a historical typo in the `orders.status` enum: `recieved`. Keep it for backward compatibility or migrate carefully.

---

*Maintained by @zuhir-samer-almosly*
