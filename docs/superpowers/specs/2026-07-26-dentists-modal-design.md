# Dentists management modal on the order form

**Date:** 2026-07-26
**Status:** approved design, not yet implemented

## Problem

Adding an order (`/orders/create`) requires picking a dentist, and every item's
price is auto-filled from that dentist's `price_list`. When a price is wrong or
has changed — "خزف went up", "this dentist gets a lower rate now" — the only fix
today is to leave the half-typed order, go to `/dentists/{id}/edit`, save, and
start the order over. The draft is lost.

## Goal

Full dentist management (add / edit / delete, including the price list) from a
modal on the order page, without leaving the draft order. Prices raised *or*
lowered in the modal take effect immediately on the order being typed.

## Scope

In scope:

- A dentists-management dialog opened from the order form.
- Editing a dentist's `price_list` from that dialog, with live effect on the
  current draft.
- Rebuilding the two standalone dentist pages (`create`, `edit`) on top of the
  same form component, so one form is maintained instead of three.

Out of scope:

- Any change to how orders are validated or stored.
- Price history / audit of who changed a price when.
- Per-order price overrides as a distinct stored concept — the order item's
  `price` column already holds whatever price was used, and past orders keep
  their prices when a dentist's list changes later.

## Design

### Component structure

Three new files under `resources/js/components/dentists/`, splitting the form
body from the dialog chrome so exactly one dentist form exists in the codebase:

- **`dentist-form.tsx`** — fields only: الاسم / الهاتف / العنوان plus the
  existing `PriceListEditor`. Owns the `useForm` state and submits to
  `POST /dentists` or `PUT /dentists/{id}`. Reuses `findDuplicateNames` from
  `price-list-editor.tsx` to block submit on duplicate work-type names, and the
  same `transform()` that converts `PriceRow[]` to a `name → price` object while
  dropping empty-named rows (lifted from the current `dentists/edit.tsx`).
  Props: `dentist: Dentist | null`, `onSaved: () => void`, `onCancel: () => void`,
  `redirectToIndex?: boolean`. No Dialog markup, so it can be dropped into a
  dialog or into a full page alike.

- **`dentist-form-dialog.tsx`** — thin `Dialog` wrapper around `DentistForm`,
  following `components/employees/employee-form-dialog.tsx`. Used by
  `pages/dentists/index.tsx` for both add and edit.

- **`dentists-manager-dialog.tsx`** — the order-page modal. A **single** Dialog
  with an internal view state:
  - `list`: the dentists table (الاسم / الهاتف / العنوان / إجراءات) with
    "إضافة طبيب", an edit button and a delete button per row.
  - `form`: renders `DentistForm`; returns to `list` on save or cancel.

  One dialog with two views, **not** nested dialogs — nested Radix dialogs bring
  focus-trap and portal problems for no benefit here.

The standalone pages `pages/dentists/create.tsx` and `pages/dentists/edit.tsx`
**stay** (their URLs keep working for anyone who bookmarked them), but shrink to
`AppLayout` + header + `<DentistForm redirectToIndex />`. All the field markup
and submit logic they hold today moves into `DentistForm`, so there is still one
form to maintain, rendered three ways.

`OrderForm` renders `<DentistsManagerDialog>` and gains a button beside the
الطبيب select that opens it. The dialog renders from the `dentists` prop the
order page already receives, so there is **no new endpoint and no new controller
method**.

### Preserving the draft order

Every request issued from these dialogs must pass
`{ preserveState: true, preserveScroll: true }`.

Inertia defaults `preserveState` to **false** for `post`/`put`/`delete`. Without
the explicit flag, saving a dentist remounts `orders/create` and silently wipes
the items already typed. The existing employee dialogs pass only
`preserveScroll` — correct there, wrong here — so they cannot be copy-pasted
without this change.

With the flag set, the `back()` redirect re-runs `OrderController::create()`, so
a fresh `dentists` prop arrives while the React form state survives.

### Item prices are frozen once the item is added

**Reversed after hands-on use (2026-07-26).** The first build made price-list
edits flow onto the items already in the draft. In practice that was wrong: with
one item at 200, raising the list price to 300 for a *second* item silently
rewrote the first one to 300 too. The price on a line is the price that was
agreed for it.

The rule is now simply:

1. `addItem` fills the price from the dentist's list at the moment the item is
   added (`getDentistPrice`), and nothing rewrites it afterwards.
2. Editing the price list — from the dialog or anywhere else — affects only the
   items added **next**. The refreshed `dentists` prop feeds `getDentistPrice`,
   so the next item picks up the new price automatically.
3. To change an existing line, the user types in that line's own price field.
   That is the only thing that moves it.

There is no sync effect in `order-form.tsx`; the absence is the feature, and the
comment there says so to stop it being "helpfully" re-added.

Switching the selected dentist still refills every item's price via the
pre-existing `handleDentistChange` — a different dentist means a different price
list, and that behaviour predates this work.

Prices stay integers (`Math.round`), matching `price_list.* => integer|min:0`
and the order item price rule.

Renaming or deleting a work type does not touch existing draft items: the type
combobox is free text, so the old name still displays and still saves.

### Backend changes

- `DentistController::store/update/destroy` redirect with `back()` instead of
  `route('dentists.index')`, matching `EmployeeController`. Without this, saving
  from the order page navigates to the dentists list and loses the draft.
- The one exception: the standalone pages still want to land on the list after
  saving. `DentistForm` with `redirectToIndex` adds a `to_index: true` field to
  the payload, and the controller does
  `$request->boolean('to_index') ? redirect()->route('dentists.index') : back()`.
  A boolean form field, not a URL, so there is no open-redirect surface; it is
  absent from the request rules so `validated()` never passes it to the model.
  This keeps it to a single navigation instead of rendering the create page
  again before bouncing to the list.
- Routes and controller methods are otherwise unchanged: `create()` and `edit()`
  stay, `Route::resource('dentists', ...)->except('show')` stays, and
  `/dentists/create` and `/dentists/{id}/edit` keep working.
- `pages/dentists/index.tsx` opens `DentistFormDialog` for add and edit instead
  of linking to those pages.
- `StoreDentistRequest` / `UpdateDentistRequest` are unchanged — the existing
  rules already cover everything the dialog submits.

### Deletion

`DentistController::destroy` already refuses to delete a dentist who has orders
or payments (the FKs cascade). The dialog keeps the existing `confirm()` prompt
and surfaces the refusal through the app's flash messages. If the deleted
dentist is the one selected in the draft, the order form clears `dentist_id`;
the item rows and their prices stay as typed.

## Testing

Backend only — the project has no JavaScript test runner (`npm run types` is the
frontend gate).

Update `tests/Feature/DentistTest.php`: the three delete tests assert
`assertRedirect(route('dentists.index'))`, which no longer holds under `back()`;
they get `->from(route('dentists.index'))` so the assertion stays meaningful.

New cases:

- storing a dentist with a price list from a page other than the dentists index
  redirects back to that page and persists the price list;
- storing with `to_index` redirects to `dentists.index` instead, and `to_index`
  is not written to the dentist row;
- updating one work type's price changes only that key and leaves the rest of
  `price_list` intact;
- `GET /dentists/create` and `GET /dentists/{id}/edit` still return 200 for an
  authenticated user (the standalone pages are kept deliberately).

Then `npm run types` and the full gate via the `run-checks` skill.

## Portal events (found during implementation)

The dialog must be a **sibling** of the order `<form>`, never a child.

Radix moves the dialog's DOM to `<body>` via a portal, so there is no invalid
HTML nesting — but React still propagates events through the *React* tree. With
the dialog rendered inside `<form>`, pressing حفظ on the dentist form also fired
the order form's `onSubmit`: an accidental half-typed order was saved and the
app navigated to `/orders`, which presented as "the draft disappeared". Verified
in the browser: the request trace showed a `PUT /dentists/5` immediately
followed by a `POST /orders`.

`DentistForm` also calls `e.stopPropagation()` in its submit handler as a second
layer, since it is designed to be dropped into arbitrary hosts.

## Risks

- **Draft loss** if `preserveState` is forgotten on any one request in the
  dialogs. This is the single highest-value thing to verify by hand: type an
  order with items, open the modal, save a dentist, confirm the items are still
  there.
- **Silent price overwrite**: editing a work type's price in the modal
  overwrites hand-typed prices for that type in the draft. Accepted by the user
  as the desired behaviour.
