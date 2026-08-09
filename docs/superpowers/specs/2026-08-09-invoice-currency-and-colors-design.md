# Invoice report: currency label and corrected semantic colors

**Date:** 2026-08-09
**Scope:** `resources/js/components/invoice-report.tsx` only.

## Problem

Two separate complaints about the الفواتير report, both traceable to the
currency redenomination (`6cb7a7d`) that divided every stored amount by 100.

**1. The figures carry no unit.** A bare `157,310` is ambiguous in the months
right after a redenomination: a dentist holding an old paper invoice cannot
tell whether a number on screen is old lira or new. This is a real ambiguity,
not a cosmetic one.

**2. The colors are backwards, and provably so.** `outstanding/index.tsx:115`
paints a dentist's outstanding balance `text-red-600` unconditionally.
`invoice-report.tsx:443` paints *the same figure* `text-green-600`. Today
بلال's 157,310 renders red on الأرصدة المستحقة and green on الفواتير.

The mirror image holds for payments: `payments/index.tsx:92` already renders
payment amounts `text-emerald-600 dark:text-emerald-400`, while
`invoice-report.tsx:396` renders the same payments red.

The green came from colouring by arithmetic sign rather than by meaning: the
payment lines are rendered with a `−` prefix (`:347`, `:438`) because they are
subtracted from the total, so they were made red as "negative numbers", which
forced the balance to take the opposite colour. That spreadsheet convention
collides with the stronger convention the rest of the app already follows —
red means *owed / needs attention*, and the invoice is a document handed to
the dentist, who reads red as "this is what I owe".

## Decisions

Settled with the user during brainstorming:

- **No decimal places.** Every money column in the schema is `integer`
  (`orders.amount`, `order_items.price`, …) and `RedenominateMoney` aborts
  rather than round, so no code path can produce a fraction. A displayed
  `.00` would be a literal constant appended to every figure, advertising a
  precision the system cannot store. Rejected. Whole numbers stay.
- **Label the two *due* lines only** — `المستحق على الطبيب` (per dentist) and
  `الإجمالي المستحق` (summary grand total). These are the figures a reader
  actually takes off the invoice. Intermediate subtotals stay bare; repeating
  the unit on every row is the noise the label is meant to avoid.
- **Label text:** the full `ليرة جديدة`, not an abbreviation.
- **Scope is the invoice report only.** Other pages (dashboard, finance,
  outstanding, ledger) are explicitly out of scope for this change.

## Design

### Colour semantics

A single helper decides colour from meaning, replacing six inline colour
classes so the rule lives in one place:

- **Payments** — money received — always green.
- **Due / balance** — coloured by sign:
  - `> 0` → red (the dentist owes)
  - `= 0` → neutral (default foreground; a bold red `0` on a dentist who has
    settled in full reads as a false alarm)
  - `< 0` → green (the dentist overpaid; that is a credit in their favour,
    not a debt)

The `−` prefixes on the payment lines stay. A green `−157,310` reads correctly
as "credit applied" — the sign carries the arithmetic, the colour carries the
meaning, and they no longer have to agree.

### The six affected sites

| line | element | now | after |
|------|---------|-----|-------|
| 346 | مدفوعات الفترة (per dentist) | red `−N` | green `−N` |
| 355 | المستحق على الطبيب | green | by sign, **+ `ليرة جديدة`** |
| 396 | payment row amounts (table) | red | green |
| 410 | إجمالي مدفوعات الفترة | red | green |
| 437 | إجمالي المدفوعات (summary) | red `−N` | green `−N` |
| 443 | الإجمالي المستحق (summary) | green | by sign, **+ `ليرة جديدة`** |

Flipping only the two lines the user pointed at would leave `:396` and `:410`
red while the summary payments line turned green — visibly inconsistent within
one document. All six move together.

### Label rendering

The label sits after the number, in a smaller and muted style so the digits
stay dominant. The number keeps `tabular-nums`; the label must not, and must
not inherit the figure's `font-bold` / `text-lg`. Number and label are wrapped
so that RTL bidi reordering cannot split the digit group from its unit.

### Palette

The green is **emerald**, not `green-600`. The app's money-positive colour is
`text-emerald-600 dark:text-emerald-400` everywhere it appears —
`payments/index.tsx:92`, `finance/index.tsx:288`, `report/index.tsx:378`,
`ledger/statement.tsx:235`, `ledger/trial-balance.tsx:57`. The invoice's
`text-green-600` is the outlier and is replaced, not merely reassigned.

The red stays `text-red-600 dark:text-red-400`, matching
`outstanding/index.tsx:115`.

### Dark mode

The invoice currently hardcodes its colours with no dark counterpart, unlike
every page cited above which pairs each with a `dark:text-*-400`. The new
helper emits both variants, bringing the invoice in line.

This is safe for the PDF: `pages/invoices/print.tsx:21` strips the `dark`
class from the document element on mount precisely because paper is always
light, so the print path never sees the dark variants.

## Constraints

- `InvoiceReport` is shared by the interactive page (`pages/invoices/index.tsx`)
  and the headless print page that Browsershot renders into the PDF. Both must
  keep rendering from this one component — that shared source is what stops the
  on-screen totals and the PDF from drifting.
- Colour must survive the PDF. Text colour is not a background, so Chromium's
  background-graphics suppression does not apply, but the rendered PDF should
  be checked once by eye rather than assumed.

## Out of scope

- Any decimal/fractional-currency support (would require a schema migration,
  ledger changes, form and validation changes — a separate project).
- Currency labels on any other page.
- The `−` sign convention itself.
