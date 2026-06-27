---
name: add-section
description: Scaffold a new CRUD/management section (Arabic, RTL) following this project's established Dentist/Employee/Material pattern. Use when asked to add a new section, resource, page, or "module" (e.g. expenses, suppliers, equipment) with list + create/edit + optional finance roll-up.
---

# Adding a new section to moslie-dental-lab

Every section in this app follows one repeatable recipe. The cleanest templates to
copy are the **Employee** (entity with sub-records) and **Material** (flat log)
features — mirror whichever matches the request, then rename.

## Conventions that are NOT optional here
- **All UI text is Arabic, layout is RTL** (`resources/views/app.blade.php` sets
  `dir="rtl"`). Match the tone of existing pages.
- **Single-tenant, shared data**: no per-user scoping, **no policies**. Routes only
  use the `auth`+`verified` group in `routes/web.php`.
- Controllers use **Form Requests** for validation; flash Arabic success messages
  (e.g. `->with('success', 'تم إضافة ... بنجاح')`).
- Money is an **`integer` `amount`** column; dates are a nullable `date` column.
  Format numbers in React with `toLocaleString('en-US')`; income = emerald, expense = rose.
- Resource routes use `->except('show')` (no show pages in this app).

## Backend files (mirror `Employee*` / `MaterialPurchase*`)
1. **Migration** `database/migrations/<ts>_create_<table>_table.php`. For a record
   that belongs to a parent, use `foreignIdFor(Parent::class)->constrained()->cascadeOnDelete()`.
2. **Model** `app/Models/<Name>.php` — `$fillable`, casts, relationships.
3. **Controller** `app/Http/Controllers/<Name>Controller.php` — resource methods
   returning `inertia('<kebab>/index'|'create'|'edit', ...)`. For month-filtered
   logs, copy the `resolveMonth()` + `whereBetween(<date>, [$start,$end])` pattern
   from `EmployeePaymentController`/`MaterialPurchaseController` and return
   `month` + `total`.
4. **Form Requests** `Store<Name>Request` + `Update<Name>Request` (`authorize()` → true).
5. **Factory + Seeder**, register the seeder in `database/seeders/DatabaseSeeder.php`.
6. **Route** in `routes/web.php` inside the auth group:
   `Route::resource('<kebab>', <Name>Controller::class)->except('show')` (add
   `->parameters([...])` for multi-word names, see employee-payments).

## Frontend files (mirror `resources/js/pages/employees|material-purchases`)
1. **Type** in `resources/js/types/models.ts`.
2. **Pages** `resources/js/pages/<kebab>/index.tsx`, `create.tsx`, `edit.tsx`.
   Reuse `AppLayout`, `Card`, `Table`, `Button`, `Input`, `Select`, `Textarea`,
   `InputError`, breadcrumbs, and the `EmptyState` helper. For month logs, copy the
   `<input type="month">` + `shiftMonth()` + ◀▶ picker from `material-purchases/index.tsx`.
3. **Sidebar** add an item to `resources/js/components/app-sidebar.tsx` (Lucide icon,
   Arabic title, `href` to the index).

## Finance integration (if the section is income or expense)
Finance (`app/Http/Controllers/FinanceController.php`) is built to extend:
- Add a `<thing>ForMonth()` sum helper and a `<thing>By<X>()` grouped breakdown
  (`->map(fn ($row) => ['name' => ..., 'total' => (int) $row->total])` — cast SUM to int).
- For an expense, push `['key'=>..., 'label'=>'...', 'total'=>$thing]` into
  `$categories` (this auto-updates `$expenses` and `net`) and add it to `trend()`'s
  per-month expenses.
- Pass the breakdown to the view; add a `<BreakdownCard>` in
  `resources/js/pages/finance/index.tsx` (the category table renders generically).

## Tests
Add `tests/Feature/<Name>Test.php` mirroring `EmployeePaymentTest` /
`MaterialPurchaseTest`: store creates a row, index filters by month (total + count),
required fields error. Extend `FinanceTest.php` if you touched Finance.

## Finish
Run the **run-checks** skill (Pest + Pint + types), then
`docker exec moslie-dental-lab-local php artisan migrate --force`.
