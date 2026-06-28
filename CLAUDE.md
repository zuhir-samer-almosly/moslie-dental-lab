# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a dental lab order management system built with Laravel 12, Inertia.js 2.0, React 19, and TypeScript. The application manages dentists, their orders, order items, and payments.

## Development Commands

### Setup
```bash
composer setup  # Installs dependencies, generates key, runs migrations, builds assets
```

### Development (Laravel Herd)
The project uses **Laravel Herd** for local development on Windows. Herd provides PHP, Nginx, Composer, and Node.js as native binaries and automatically serves the app at `http://moslie-dental-lab.test`.

```bash
# The app is always available at http://moslie-dental-lab.test (Herd serves it via Nginx)
composer dev  # Starts queue worker, pail logs, and Vite dev server concurrently
composer dev:ssr  # Same as above but with Inertia SSR enabled
```

### Herd CLI Commands
```bash
herd open          # Open the project in the browser
herd php -v        # Check which PHP version Herd is using
herd isolate 8.3   # Pin this project to PHP 8.3
herd secure        # Enable HTTPS with a trusted local certificate
herd share         # Share your local site publicly via Expose
```

### Testing
```bash
composer test  # Runs Pint linter check + Pest tests
php artisan test  # Run Pest tests only
php artisan test --filter=TestName  # Run specific test
npm run types  # TypeScript type checking
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
- Fields: dentist_id, amount

### Frontend Structure

**Pages** (`resources/js/pages/`)
- Inertia pages are auto-resolved from `./pages/{name}.tsx`
- Auth pages: login, register, forgot-password, reset-password, verify-email, two-factor-challenge
- Settings pages: profile, password, two-factor, appearance
- Main pages: welcome, dashboard

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
- Run tests with `composer test` which includes linting checks
- Tests are configured in `tests/Pest.php` with custom expectations available

## Key Conventions

- **Controllers**: Use Form Requests for validation (e.g., `StoreDentistRequest`, `UpdateDentistRequest`)
- **Factories**: All models have factories in `database/factories/`
- **Seeders**: All models have seeders in `database/seeders/`
- **Policies**: Authorization policies in `app/Policies/`
- **TypeScript**: Strict mode enabled, prefer explicit types
- **React**: Uses React 19 with the React Compiler enabled for automatic optimization
- **CSS**: Tailwind utility classes, no custom CSS unless necessary

## Notes

- The project uses **Laravel Herd** for local development on Windows
- Herd provides native PHP, Nginx, Composer, and Node.js — no need for `php artisan serve`
- The app is served at `http://moslie-dental-lab.test` (use `herd secure` for HTTPS)
- Docker setup (Dockerfile, docker-compose.yml) is for **production deployment only**
- SSR support is available but optional via `composer dev:ssr`
- Dark mode support is built-in via `resources/js/hooks/use-appearance`
- Concurrently runs multiple dev processes with color-coded output

