# Invoice Currency Label and Colour Flip — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** In the الفواتير report, colour payments green and amounts-owed red (instead of the reverse), and label the two "due" figures with `ليرة جديدة`.

**Architecture:** All changes live in one presentational component, `resources/js/components/invoice-report.tsx`, which is shared by the on-screen page and the headless page Browsershot renders into the PDF. Six inline colour classes are replaced by two small module-level helpers at the top of that file: a `TONE` map plus a `dueTone(value)` function that picks a colour from the sign of a balance, and a `DueAmount` component that renders a figure with its unit. No backend, schema, or route changes.

**Tech Stack:** React 19 + TypeScript (strict), Tailwind CSS 4, Inertia 2, `cn()` from `@/lib/utils` (clsx + tailwind-merge).

## Global Constraints

- **Scope is `resources/js/components/invoice-report.tsx` only.** Do not touch other pages, the backend, migrations, or the ledger.
- **No decimal places anywhere.** Money columns are `integer`; keep `.toLocaleString('en-US')` with no `minimumFractionDigits`. Never render `.00`.
- **Label text is exactly `ليرة جديدة`** — full words, no abbreviation, no `ل.ج`.
- **The label goes on exactly two lines:** `المستحق على الطبيب` (per-dentist, currently line 355) and `الإجمالي المستحق` (summary, currently line 443). Nowhere else.
- **Green is emerald:** `text-emerald-600 dark:text-emerald-400`. Do **not** use `text-green-600` — that class is the outlier this change removes. Red is `text-red-600 dark:text-red-400`.
- **Keep the `−` prefixes** on the payment lines (currently lines 347 and 438). A green `−157,310` is correct and intended.
- **Keep `tabular-nums` on every numeric span.** The label span must not carry it.
- **No new dependencies.** Do not add a JS test runner, a component library, or an i18n package.

## Testing reality — read before starting

**This project has no JavaScript test runner.** There is no vitest, no jest, no
`@testing-library`, and no `*.test.tsx` file anywhere in `resources/js`. The
Pest suite in `tests/Feature` tests PHP controllers and cannot observe a
Tailwind class in a React component.

Adding a JS test framework was explicitly ruled out of scope, so the
per-task verification cycle for this plan is:

1. `npm run types` — must pass (`tsc --noEmit`).
2. `npm run lint` and `npm run format:check` — must pass.
3. A **visual check in a real browser**, which for a colour-and-label change is
   the verification that actually carries information.

Task 3 is a dedicated verification task covering the on-screen page, dark mode,
and the generated PDF. Do not skip it — it is the only step that can catch a
label that bidi-reorders wrongly or a colour that vanishes in the PDF.

The existing Pest suite still gets run once at the end to prove nothing
server-side regressed.

---

## File Structure

| File | Change | Responsibility |
|------|--------|----------------|
| `resources/js/components/invoice-report.tsx` | Modify | The invoice document. Gains `TONE`, `dueTone()`, `DueAmount` near the top; six colour sites updated in the JSX below. |

No files are created. No files are deleted.

---

### Task 1: Colour helpers, and payments turn green

**Files:**
- Modify: `resources/js/components/invoice-report.tsx` (imports at 1-18; new helpers after the `InvoiceData` type block; JSX at 346, 396, 410, 437)

**Interfaces:**
- Consumes: `cn` from `@/lib/utils` (signature `cn(...inputs: ClassValue[]): string`).
- Produces, for Task 2:
  - `TONE` — `{ readonly owed: string; readonly credit: string; readonly settled: string }`
  - `dueTone(due: number): string`

- [ ] **Step 1: Add the `cn` import**

`invoice-report.tsx` does not currently import `cn`. Add it after the existing `@/components/ui/table` import block (line 17), keeping import order consistent with the file:

```tsx
import type { Dentist, DentistPayment, Order, OrderItem } from '@/types';
import { cn } from '@/lib/utils';
```

- [ ] **Step 2: Add the tone helpers**

Place this immediately **after** the `export type InvoiceData = {...}` block and **before** the `InvoiceReport` component. Copy the comments verbatim — they explain a decision that looks arbitrary otherwise.

```tsx
/**
 * Money here is coloured by what it means, not by the sign it is printed with.
 *
 * The payment lines carry a `−` because they are subtracted from the total,
 * and colouring by that sign is what used to paint payments red and the
 * balance green — the exact inverse of every other page. `payments/index.tsx`
 * shows payments in emerald and `outstanding/index.tsx` shows balances owed in
 * red; this report now agrees with both.
 */
const TONE = {
    owed: 'text-red-600 dark:text-red-400',
    credit: 'text-emerald-600 dark:text-emerald-400',
    settled: '',
} as const;

/**
 * Money the lab received. Always a credit, whatever sign it is printed with.
 */
const PAYMENT_TONE = TONE.credit;

/**
 * A balance owed, coloured by which way it points. Neutral at zero: a bold red
 * `0` on the one dentist who has settled in full reads as an alarm. Green when
 * negative, because an overpayment is a credit in the dentist's favour, not a
 * debt — `InvoiceTest` covers that case ("a credit balance is not clamped to
 * zero"), so it does reach the screen.
 */
function dueTone(due: number): string {
    if (due > 0) return TONE.owed;
    if (due < 0) return TONE.credit;

    return TONE.settled;
}
```

- [ ] **Step 3: Turn مدفوعات الفترة green (per-dentist block)**

At line 346, replace:

```tsx
                                        <span className="text-red-600 tabular-nums">
```

with:

```tsx
                                        <span
                                            className={cn(
                                                PAYMENT_TONE,
                                                'tabular-nums',
                                            )}
                                        >
```

- [ ] **Step 4: Turn the payment table rows green**

At line 396, replace:

```tsx
                                        <TableCell className="font-semibold text-red-600">
```

with:

```tsx
                                        <TableCell
                                            className={cn(
                                                'font-semibold',
                                                PAYMENT_TONE,
                                            )}
                                        >
```

- [ ] **Step 5: Turn إجمالي مدفوعات الفترة green**

At line 410, replace:

```tsx
                        <span className="text-red-600 tabular-nums">
```

with:

```tsx
                        <span className={cn(PAYMENT_TONE, 'tabular-nums')}>
```

- [ ] **Step 6: Turn إجمالي المدفوعات green (summary)**

At line 437, replace:

```tsx
                        <span className="font-semibold text-red-600 tabular-nums">
```

with:

```tsx
                        <span
                            className={cn(
                                'font-semibold',
                                PAYMENT_TONE,
                                'tabular-nums',
                            )}
                        >
```

- [ ] **Step 7: Confirm no red remains on a payment figure**

Run:

```bash
grep -n "text-red-600\|text-green-600\|text-emerald" resources/js/components/invoice-report.tsx
```

Expected: `text-red-600` and `text-emerald-600` appear **only** inside the `TONE` map. Two `text-green-600` occurrences remain (lines ~355 and ~443, the due figures) — Task 2 removes those. If any other `text-red-600` survives outside `TONE`, a payment site was missed.

- [ ] **Step 8: Typecheck and lint**

```bash
npm run types && npm run lint && npm run format:check
```

Expected: all three pass. `dueTone` is defined but not yet used — this is fine for `tsc`, but if ESLint flags it as unused, leave the code as written and let Task 2 resolve it rather than deleting the function.

- [ ] **Step 9: Commit**

```bash
git add resources/js/components/invoice-report.tsx
git commit -m "fix(invoices): colour payments as credits, not negatives"
```

---

### Task 2: Amounts owed turn red and gain the ليرة جديدة label

**Files:**
- Modify: `resources/js/components/invoice-report.tsx` (new `DueAmount` component beside the helpers from Task 1; JSX at ~355 and ~443)

**Interfaces:**
- Consumes from Task 1: `dueTone(due: number): string`, `TONE`, `cn`.
- Produces: `DueAmount` — `({ value, className }: { value: number; className?: string }) => JSX.Element`

- [ ] **Step 1: Add the `DueAmount` component**

Place it directly after `dueTone` from Task 1:

```tsx
/**
 * A balance owed, with its unit. Since the redenomination divided every stored
 * amount by 100, a bare figure is ambiguous against any invoice printed before
 * it — the unit is what disambiguates, so it rides along with the two figures
 * a reader actually takes off the page.
 *
 * `inline-flex` keeps the digits and the unit in one bidi run: RTL reordering
 * would otherwise be free to move the Arabic label away from the Latin digit
 * group it belongs to. The unit resets `font-normal text-sm` so it stays quiet
 * beside the bold, enlarged grand total, and `whitespace-nowrap` stops it
 * wrapping mid-phrase in a narrow column.
 */
function DueAmount({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-baseline gap-1',
                dueTone(value),
                className,
            )}
        >
            <span className="tabular-nums">
                {value.toLocaleString('en-US')}
            </span>
            <span className="text-sm font-normal whitespace-nowrap text-muted-foreground">
                ليرة جديدة
            </span>
        </span>
    );
}
```

- [ ] **Step 2: Replace المستحق على الطبيب (per-dentist)**

Around line 355 (shifted by Task 1's edits), replace this whole span:

```tsx
                                        <span className="text-green-600 tabular-nums">
                                            {group.due.toLocaleString('en-US')}
                                        </span>
```

with:

```tsx
                                        <DueAmount value={group.due} />
```

The surrounding `<div className="... font-bold">` already supplies the weight, and `DueAmount` resets the label back to `font-normal`.

- [ ] **Step 3: Replace الإجمالي المستحق (summary)**

Around line 443, replace this whole span:

```tsx
                        <span className="text-lg font-bold text-green-600 tabular-nums">
                            {totals.balance.toLocaleString('en-US')}
                        </span>
```

with:

```tsx
                        <DueAmount
                            value={totals.balance}
                            className="text-lg font-bold"
                        />
```

- [ ] **Step 4: Confirm every colour now flows through the helpers**

```bash
grep -n "text-red-600\|text-green-600\|text-emerald" resources/js/components/invoice-report.tsx
```

Expected: exactly two hits, both inside the `TONE` map. Zero occurrences of `text-green-600`. If `text-green-600` still appears, a due site was missed.

- [ ] **Step 5: Confirm the label appears exactly twice, and no decimals crept in**

```bash
grep -c "ليرة جديدة" resources/js/components/invoice-report.tsx
grep -n "minimumFractionDigits\|toFixed(2)" resources/js/components/invoice-report.tsx
```

Expected: the first prints `1` (the string is written once, inside `DueAmount`, and rendered at two call sites). The second prints nothing — no decimals.

- [ ] **Step 6: Typecheck, lint, format**

```bash
npm run types && npm run lint && npm run format:check
```

Expected: all pass, and any "unused `dueTone`" warning from Task 1 is now gone.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/invoice-report.tsx
git commit -m "feat(invoices): label amounts owed in ليرة جديدة and colour them red"
```

---

### Task 3: Verify on screen, in dark mode, and in the PDF

This is the task that actually validates the change. A Tailwind class that
typechecks can still render an unreadable label or a colour that drops out of
the PDF.

**Files:**
- Modify: none (verification only)

**Interfaces:**
- Consumes: the rendered app at `http://dental.test`.

- [ ] **Step 1: Bring the app up**

```bash
./docker-start-local.sh
```

Expected: app, db, redis and vite containers running; `http://dental.test` serves the app.

- [ ] **Step 2: Confirm there is invoice data to look at**

The report is only meaningful with a dentist who has both orders and payments in the period. Check quickly:

```bash
docker compose -f docker-compose.local.yml exec app php artisan tinker --execute="echo App\Models\Dentist::count().' dentists, '.App\Models\Order::count().' orders, '.App\Models\DentistPayment::count().' payments';"
```

Expected: non-zero counts. If all zero, stop and report back rather than seeding — the `seeded-dataset-is-not-buildable` note says the income-side factories are empty stubs and faker is absent from the container, so seeding is not a quick fix.

- [ ] **Step 3: Screenshot the report on screen**

Use the `webapp-testing` skill (Playwright) to log in, open `http://dental.test/invoices`, pick a period containing data, and capture the per-dentist block and the الملخص block.

Verify by eye, against the spec:
- `مدفوعات الفترة` and `إجمالي المدفوعات` are **green**, still prefixed `−`.
- Payment rows in the المدفوعات table are **green**.
- `المستحق على الطبيب` and `الإجمالي المستحق` are **red** when positive, and each is followed by `ليرة جديدة` in a smaller, muted style.
- The digits and the label are not separated or reversed by RTL reordering — the label sits beside its number, reading `157,310 ليرة جديدة`.
- Numbers still align in their columns (`tabular-nums` intact).

- [ ] **Step 4: Check the zero and credit cases**

Find or produce a dentist whose balance is `0`, and one whose balance is negative (overpaid — `InvoiceTest` line 145 confirms this case is real and is not clamped).

Verify:
- A `0` balance renders in the **default foreground colour**, not red.
- A negative balance renders **green**, with the label still present.

If no such dentist exists in local data, record a payment larger than the dentist's outstanding total through the UI to create one, then check.

- [ ] **Step 5: Check dark mode**

Toggle appearance to dark (the app has `use-appearance` built in) and re-check the same screens.

Expected: red reads as `red-400` and green as `emerald-400` — both legible against the dark surface. Neither renders as the dim `-600` shade that was unreadable before this change added the dark variants.

- [ ] **Step 6: Check the PDF**

Download the invoice PDF from the report page (the الفواتير download button, served by `InvoiceController::pdf`).

Expected:
- The PDF renders in **light** mode regardless of the browser's current appearance — `pages/invoices/print.tsx:21` strips the `dark` class on mount.
- The red and green text colours are present in the PDF. Text colour is not a background, so Chromium's background-graphics suppression does not apply; if the colours are nevertheless missing, report it rather than working around it.
- `ليرة جديدة` appears on both due lines and the Arabic glyphs are shaped correctly.

- [ ] **Step 7: Run the full quality gate**

Use the `run-checks` skill (it encodes the host-vs-container split — a bare `php artisan test` fails in this environment).

Expected: Pest, Pint, and `tsc` all pass. Nothing server-side changed, so any Pest failure is pre-existing and should be reported as such, not "fixed" inside this change.

- [ ] **Step 8: Commit any formatting fallout**

Only if `npm run format` altered files:

```bash
git add -A
git commit -m "style: format invoice report"
```

---

## Self-Review

**Spec coverage:**

| Spec requirement | Task |
|---|---|
| Payments green (4 sites: 346, 396, 410, 437) | Task 1, steps 3-6 |
| Due red by sign (2 sites: 355, 443) | Task 2, steps 2-3 (`dueTone` from Task 1, step 2) |
| Zero neutral, negative green | Task 1 step 2 (`dueTone`); verified Task 3 step 4 |
| `ليرة جديدة` on the two due lines only | Task 2, steps 1-3, 5 |
| Label smaller and muted, number dominant | Task 2 step 1 (`text-sm font-normal text-muted-foreground`) |
| Bidi keeps number and label together | Task 2 step 1 (`inline-flex`); verified Task 3 step 3 |
| `−` prefixes retained | Untouched by every edit; verified Task 3 step 3 |
| No decimals | Global constraint; verified Task 2 step 5 |
| Emerald not `green-600` | Task 1 step 2 (`TONE`); verified Task 2 step 4 |
| `dark:` variants added | Task 1 step 2 (`TONE`); verified Task 3 step 5 |
| One helper, not six inline ternaries | Task 1 step 2, Task 2 step 1; verified Task 2 step 4 |
| PDF stays light and keeps colour | Task 3 step 6 |
| Shared component still serves screen and PDF | No structural change; both paths verified in Task 3 |

**Placeholder scan:** No TBD/TODO. Every code step carries the literal code. Every verification step names the command and the expected result.

**Type consistency:** `TONE` / `PAYMENT_TONE` / `dueTone` / `DueAmount` are defined once in Task 1 step 2 and Task 2 step 1, and used under exactly those names in Tasks 1 and 2. `DueAmount`'s props (`value: number`, `className?: string`) match both call sites — `group.due` and `totals.balance` are both `number` per the `DentistGroup` type at line 29 and the totals type below it.

**Known deviation from the skill's default:** steps are not written test-first, because the project has no JavaScript test runner and adding one was ruled out of scope. Task 3 substitutes browser and PDF verification, which is the verification that carries real information for a colour-and-label change.
