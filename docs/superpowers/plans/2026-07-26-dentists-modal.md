# Dentists Management Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the user add, edit (including prices) and delete dentists from a modal on the order form, without losing the order draft, with price changes applying immediately to the items already typed.

**Architecture:** One shared `DentistForm` component holds the fields and the save logic; it is rendered three ways — inside a dialog on the dentists page, inside a dialog on the order page, and inside the two existing standalone pages. The `DentistController` write actions redirect `back()` so a save never navigates away from a draft order, except when the payload carries `to_index` (only the standalone pages send it). `OrderForm` diffs the refreshed `dentists` prop against a snapshot and rewrites the prices of matching draft items.

**Tech Stack:** Laravel 12, Inertia.js 2.0, React 19 (React Compiler on), TypeScript strict, Tailwind 4, Radix dialog primitives, Pest 4.

Design spec: `docs/superpowers/specs/2026-07-26-dentists-modal-design.md`

## Global Constraints

- The UI is **Arabic and RTL**. All user-facing strings in this plan are already Arabic — copy them verbatim, do not translate or invent new ones.
- Prices are **integers** everywhere. `price_list.* => ['integer', 'min:0']` in the requests, and the frontend rounds with `Math.round` before it puts a price into a form field. A decimal that reaches an order item fails validation on save.
- **Every Inertia request issued from a dialog must pass `{ preserveState: true, preserveScroll: true }`.** Inertia defaults `preserveState` to `false` for `post`/`put`/`delete`; omitting it remounts the page and silently wipes the order draft the dialog floats over. This is the single most important line in the plan.
- The order status enum contains the misspelling `recieved`. Do not touch it.
- **There is no JavaScript test runner in this project.** The frontend gate is `npm run types` (tsc --noEmit) plus `npm run lint`, and Task 6 is a real manual browser check. Do not invent vitest/jest tests.
- PHP tests run **on the host** via the `run-checks` skill (the container is read-only PHP 8.3). Bare `php artisan test` fails here. Artisan/migrations run **inside** the `app` container.
- Existing patterns to follow, not reinvent: `app/Http/Controllers/EmployeeController.php` for `back()` redirects, `resources/js/components/employees/employee-form-dialog.tsx` for dialog structure.

---

### Task 1: Backend — redirect back, with an opt-out for the standalone pages

**Files:**
- Modify: `app/Http/Controllers/DentistController.php`
- Test: `tests/Feature/DentistTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: `POST /dentists` and `PUT /dentists/{dentist}` redirect to the previous URL with a `success` flash, unless the payload contains `to_index` truthy, in which case they redirect to `route('dentists.index')`. `DELETE /dentists/{dentist}` always redirects back. `to_index` is never persisted to the model. Routes `dentists.create` and `dentists.edit` remain registered and continue to render `dentists/create` / `dentists/edit`.

- [ ] **Step 1: Update the three existing delete tests so they still assert something real**

`back()` with no referer redirects to `/`, so the current `assertRedirect(route('dentists.index'))` assertions only pass by accident of the old code. Give each of the three delete tests a `from()`. In `tests/Feature/DentistTest.php`, change all three occurrences of:

```php
    $this->delete(route('dentists.destroy', $dentist))
        ->assertRedirect(route('dentists.index'))
```

to:

```php
    $this->from(route('dentists.index'))
        ->delete(route('dentists.destroy', $dentist))
        ->assertRedirect(route('dentists.index'))
```

There are three of them: in `'a dentist without financial history can be deleted'`, `'a dentist with orders cannot be deleted'`, and `'a dentist with payments cannot be deleted'`. Leave the rest of each test alone.

- [ ] **Step 2: Write the failing tests for the new redirect behaviour**

Append to `tests/Feature/DentistTest.php`:

```php
test('storing a dentist from the order page redirects back and saves the price list', function () {
    $this->actingAs(User::factory()->create());

    $this->from(route('orders.create'))
        ->post(route('dentists.store'), [
            'name' => 'د. خالد',
            'price_list' => ['خزف' => 90000, 'زيركون' => 150000],
        ])
        ->assertRedirect(route('orders.create'))
        ->assertSessionHas('success');

    expect(Dentist::firstWhere('name', 'د. خالد')->price_list)
        ->toBe(['خزف' => 90000, 'زيركون' => 150000]);
});

test('storing a dentist from the standalone page redirects to the index', function () {
    $this->actingAs(User::factory()->create());

    $this->from(route('dentists.create'))
        ->post(route('dentists.store'), [
            'name' => 'د. ليلى',
            'to_index' => true,
        ])
        ->assertRedirect(route('dentists.index'))
        ->assertSessionHas('success');

    $dentist = Dentist::firstWhere('name', 'د. ليلى');

    expect($dentist)->not->toBeNull()
        ->and($dentist->getAttributes())->not->toHaveKey('to_index');
});

test('updating one work type price leaves the rest of the price list intact', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create([
        'name' => 'د. سامر',
        'price_list' => ['خزف' => 90000, 'زيركون' => 150000],
    ]);

    $this->from(route('orders.create'))
        ->put(route('dentists.update', $dentist), [
            'name' => 'د. سامر',
            'price_list' => ['خزف' => 110000, 'زيركون' => 150000],
        ])
        ->assertRedirect(route('orders.create'))
        ->assertSessionHas('success');

    expect($dentist->fresh()->price_list)
        ->toBe(['خزف' => 110000, 'زيركون' => 150000]);
});

test('the standalone dentist pages still load', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);

    $this->get(route('dentists.create'))->assertOk();
    $this->get(route('dentists.edit', $dentist))->assertOk();
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run the PHP suite through the `run-checks` skill, or replicate its env redirects, filtered to this file:

```
php artisan test --filter=DentistTest
```

Expected: the two redirect tests FAIL with the response redirecting to `http://localhost/dentists` instead of the `from()` URL. `'the standalone dentist pages still load'` should already PASS (the pages exist today) — that is fine, it is a regression guard for Task 2.

- [ ] **Step 4: Implement the redirect helper**

In `app/Http/Controllers/DentistController.php`, add the `Request` import under the existing `use` statements:

```php
use Illuminate\Http\Request;
```

Replace the bodies of `store` and `update`, and the `destroy` redirects, so the file's write actions read:

```php
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDentistRequest $request)
    {
        Dentist::create($request->validated());

        return $this->redirectAfterWrite($request, 'تم إضافة الطبيب بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dentist $dentist)
    {
        return inertia('dentists/edit', [
            'dentist' => $dentist,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDentistRequest $request, Dentist $dentist)
    {
        $dentist->update($request->validated());

        return $this->redirectAfterWrite($request, 'تم تحديث الطبيب بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dentist $dentist)
    {
        // The FKs cascade: deleting a dentist would wipe all their orders and
        // payments. Never allow that once financial history exists.
        if ($dentist->orders()->exists() || $dentist->payments()->exists()) {
            return back()
                ->with('error', 'لا يمكن حذف الطبيب لوجود طلبات أو دفعات مسجلة باسمه.');
        }

        $dentist->delete();

        return back()
            ->with('success', 'تم حذف الطبيب بنجاح');
    }

    /**
     * Dentists are edited from a dialog that can float over any page — most
     * importantly a half-typed order — so a write must land the user back
     * where they were. Only the standalone create/edit pages ask to be sent
     * to the list, via a `to_index` flag on the payload. It is a boolean
     * form field rather than a URL, so there is no open-redirect surface,
     * and it is absent from the request rules so `validated()` never hands
     * it to the model.
     */
    private function redirectAfterWrite(Request $request, string $message)
    {
        $redirect = $request->boolean('to_index')
            ? redirect()->route('dentists.index')
            : back();

        return $redirect->with('success', $message);
    }
```

Leave `index()` and `create()` exactly as they are.

- [ ] **Step 5: Run the tests to verify they pass**

```
php artisan test --filter=DentistTest
```

Expected: PASS, all cases green.

- [ ] **Step 6: Check style and commit**

```bash
composer lint
git add app/Http/Controllers/DentistController.php tests/Feature/DentistTest.php
git commit -m "feat(dentists): redirect writes back so a dialog save keeps the current page"
```

---

### Task 2: Extract the shared `DentistForm` and rebuild the standalone pages on it

**Files:**
- Create: `resources/js/components/dentists/dentist-form.tsx`
- Modify: `resources/js/pages/dentists/create.tsx` (replace the whole form body)
- Modify: `resources/js/pages/dentists/edit.tsx` (replace the whole form body)

**Interfaces:**
- Consumes: Task 1's `to_index` flag and `back()` redirects.
- Produces: `resources/js/components/dentists/dentist-form.tsx` default-exports

  ```ts
  export default function DentistForm(props: {
      dentist?: Dentist | null;      // null/absent = create
      redirectToIndex?: boolean;     // sends to_index:true; standalone pages only
      onSaved?: () => void;          // fired after a successful save
      onCancel?: () => void;         // when provided, an إلغاء button is rendered
  }): JSX.Element
  ```

  It renders a `<form>` with الاسم / الهاتف / العنوان / قائمة الأسعار and its own action row. It has **no** dialog or layout chrome, so it drops into either. It does **not** self-reset when `dentist` changes — callers must remount it with a `key` (Task 3 and 4 do).

- [ ] **Step 1: Create the shared form component**

Create `resources/js/components/dentists/dentist-form.tsx`:

```tsx
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PriceListEditor, {
    DEFAULT_WORK_TYPES,
    findDuplicateNames,
    type PriceRow,
} from '@/components/price-list-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Dentist } from '@/types';

/** A new dentist starts from the default work types; an existing one from theirs. */
const toRows = (dentist?: Dentist | null): PriceRow[] =>
    dentist
        ? Object.entries(dentist.price_list ?? {}).map(([name, price]) => ({
              name,
              price,
          }))
        : DEFAULT_WORK_TYPES.map((name) => ({ name, price: 0 }));

/**
 * The one dentist form in the app. Rendered three ways: the standalone
 * create/edit pages, the dialog on the dentists list, and the manager dialog
 * that floats over the order form. It owns no chrome — no Dialog, no
 * AppLayout — so each caller supplies its own.
 *
 * It has no reset-on-prop-change effect on purpose: callers remount it with a
 * `key` when they switch which dentist is being edited, which is simpler and
 * cannot go stale.
 */
export default function DentistForm({
    dentist = null,
    redirectToIndex = false,
    onSaved,
    onCancel,
}: {
    dentist?: Dentist | null;
    redirectToIndex?: boolean;
    onSaved?: () => void;
    onCancel?: () => void;
}) {
    const isEdit = Boolean(dentist);

    const {
        data,
        setData,
        transform,
        post,
        put,
        processing,
        errors,
        setError,
        clearErrors,
    } = useForm({
        name: dentist?.name ?? '',
        phone: dentist?.phone ?? '',
        address: dentist?.address ?? '',
        price_list: toRows(dentist),
    });

    transform((payload) => ({
        name: payload.name,
        phone: payload.phone,
        address: payload.address,
        price_list: Object.fromEntries(
            payload.price_list
                .filter((row) => row.name.trim() !== '')
                .map((row) => [row.name.trim(), row.price]),
        ),
        // Only the standalone pages want to land on the list afterwards; the
        // dialogs must stay on whatever page they opened over.
        ...(redirectToIndex ? { to_index: true } : {}),
    }));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();

        const duplicates = findDuplicateNames(data.price_list);
        if (duplicates.length > 0) {
            setError(
                'price_list',
                `أنواع عمل مكررة: ${duplicates.join('، ')}. لكل نوع سطر واحد فقط.`,
            );
            return;
        }

        // preserveState is false by default for post/put. Without it Inertia
        // remounts the page underneath — which, when this form is in a dialog
        // over a half-typed order, throws the whole draft away.
        const options = {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
        };

        if (dentist) {
            put(`/dentists/${dentist.id}`, options);
        } else {
            post('/dentists', options);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">الاسم *</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">الهاتف</Label>
                <Input
                    id="phone"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                />
                <InputError message={errors.phone} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="address">العنوان</Label>
                <Textarea
                    id="address"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    rows={3}
                />
                <InputError message={errors.address} />
            </div>

            <div className="space-y-3">
                <Label>قائمة الأسعار</Label>
                <p className="text-sm text-muted-foreground">
                    حدد أنواع العمل وأسعارها لهذا الطبيب. ستظهر هذه الأنواع عند
                    إضافة عناصر الطلب ويُملأ سعرها تلقائياً.
                </p>
                <PriceListEditor
                    value={data.price_list}
                    onChange={(rows) => setData('price_list', rows)}
                />
                <InputError message={errors.price_list} />
            </div>

            <div className="flex justify-end gap-2">
                {onCancel && (
                    <Button type="button" variant="outline" onClick={onCancel}>
                        إلغاء
                    </Button>
                )}
                <Button type="submit" disabled={processing}>
                    {isEdit ? 'تحديث' : 'حفظ'}
                </Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 2: Rebuild the standalone create page on it**

Replace the entire contents of `resources/js/pages/dentists/create.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import DentistForm from '@/components/dentists/dentist-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'أطباء الأسنان',
        href: '/dentists',
    },
    {
        title: 'إضافة طبيب',
        href: '/dentists/create',
    },
];

export default function DentistsCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إضافة طبيب" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-fit gap-2 text-muted-foreground"
                        onClick={() => window.history.back()}
                    >
                        <ArrowRight className="size-4" />
                        رجوع
                    </Button>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            إضافة طبيب جديد
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            أدخل بيانات الطبيب وقائمة أسعاره
                        </p>
                    </div>
                </div>

                <div className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="p-5 md:p-6">
                            <DentistForm redirectToIndex />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Rebuild the standalone edit page on it**

Replace the entire contents of `resources/js/pages/dentists/edit.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import DentistForm from '@/components/dentists/dentist-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Dentist } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'أطباء الأسنان',
        href: '/dentists',
    },
    {
        title: 'تعديل طبيب',
        href: '#',
    },
];

export default function DentistsEdit({ dentist }: { dentist: Dentist }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تعديل طبيب" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-fit gap-2 text-muted-foreground"
                        onClick={() => window.history.back()}
                    >
                        <ArrowRight className="size-4" />
                        رجوع
                    </Button>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            تعديل طبيب
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            تحديث بيانات الطبيب وقائمة أسعاره
                        </p>
                    </div>
                </div>

                <div className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="p-5 md:p-6">
                            <DentistForm dentist={dentist} redirectToIndex />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 4: Verify types, lint and formatting**

```bash
npm run types && npm run lint && npm run format
```

Expected: `tsc --noEmit` exits 0 with no output; eslint reports no errors.

- [ ] **Step 5: Verify the standalone pages still work end to end**

With the app running (`./docker-start-local.sh`), open `http://dental.test/dentists/create`, add a dentist named `اختبار` with one work type priced `1000`, submit. Expected: you land on `/dentists`, the success flash shows, and `اختبار` is in the table. Then open its ✏ (still `/dentists/{id}/edit` at this point), change the price to `1500`, submit — back to the list, flash shown. Delete `اختبار` afterwards to keep the data clean.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/dentists/dentist-form.tsx resources/js/pages/dentists/create.tsx resources/js/pages/dentists/edit.tsx
git commit -m "refactor(dentists): extract one shared dentist form from the create/edit pages"
```

---

### Task 3: `DentistFormDialog` and the dentists list page

**Files:**
- Create: `resources/js/components/dentists/dentist-form-dialog.tsx`
- Modify: `resources/js/pages/dentists/index.tsx`

**Interfaces:**
- Consumes: `DentistForm` from Task 2.
- Produces: `resources/js/components/dentists/dentist-form-dialog.tsx` default-exports

  ```ts
  export default function DentistFormDialog(props: {
      open: boolean;
      onOpenChange: (open: boolean) => void;
      dentist?: Dentist | null;
  }): JSX.Element
  ```

  It closes itself on a successful save.

- [ ] **Step 1: Create the dialog wrapper**

Create `resources/js/components/dentists/dentist-form-dialog.tsx`:

```tsx
import DentistForm from '@/components/dentists/dentist-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Dentist } from '@/types';

/**
 * Add/edit a dentist in a dialog. The form is remounted per dentist via `key`,
 * so switching rows always shows that row's data with no reset effect.
 */
export default function DentistFormDialog({
    open,
    onOpenChange,
    dentist = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    dentist?: Dentist | null;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {/* Price lists get long — let the dialog scroll instead of the page. */}
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {dentist ? 'تعديل طبيب' : 'إضافة طبيب جديد'}
                    </DialogTitle>
                    <DialogDescription>
                        {dentist
                            ? 'تحديث بيانات الطبيب وقائمة أسعاره'
                            : 'أدخل بيانات الطبيب وقائمة أسعاره'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <DentistForm
                        key={dentist?.id ?? 'new'}
                        dentist={dentist}
                        onSaved={() => onOpenChange(false)}
                        onCancel={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Point the dentists list page at the dialog**

In `resources/js/pages/dentists/index.tsx`, change the imports at the top from:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
```

to:

```tsx
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import DentistFormDialog from '@/components/dentists/dentist-form-dialog';
import { Button } from '@/components/ui/button';
```

Then, inside `DentistsIndex`, add the dialog state above `handleDelete`:

```tsx
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Dentist | null>(null);

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const openEdit = (dentist: Dentist) => {
        setEditing(dentist);
        setDialogOpen(true);
    };
```

and change `handleDelete` to preserve scroll (the list can be long):

```tsx
    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الطبيب؟')) {
            router.delete(`/dentists/${id}`, { preserveScroll: true });
        }
    };
```

- [ ] **Step 3: Swap the two links for buttons and mount the dialog**

In the same file, replace the header button:

```tsx
                    <Button asChild size="lg" className="gap-2 sm:w-auto">
                        <Link href="/dentists/create">
                            <Plus className="size-4" />
                            إضافة طبيب
                        </Link>
                    </Button>
```

with:

```tsx
                    <Button
                        size="lg"
                        className="gap-2 sm:w-auto"
                        onClick={openCreate}
                    >
                        <Plus className="size-4" />
                        إضافة طبيب
                    </Button>
```

replace the row edit button:

```tsx
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/dentists/${dentist.id}/edit`}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
```

with:

```tsx
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEdit(dentist)
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
```

and mount the dialog just before the closing `</div>` of the page wrapper (immediately after the closing `</Card>`):

```tsx
                <DentistFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    dentist={editing}
                />
```

- [ ] **Step 4: Verify types, lint and formatting**

```bash
npm run types && npm run lint && npm run format
```

Expected: exit 0. If `Link` is now unused, eslint's import rules will flag it — remove the import (Step 2 already does).

- [ ] **Step 5: Verify in the browser**

At `http://dental.test/dentists`: click **إضافة طبيب** → dialog opens with the default work types → add `اختبار` with one price → save → dialog closes, the row appears, **the page never navigated**. Click ✏ on `اختبار` → dialog opens pre-filled → change the price → تحديث → dialog closes, flash shows. Click ✏ on a *different* dentist → confirm it shows **that** dentist's data, not the previous one (this is what the `key` remount guards).

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/dentists/dentist-form-dialog.tsx resources/js/pages/dentists/index.tsx
git commit -m "feat(dentists): edit dentists in a dialog on the list page"
```

---

### Task 4: The manager dialog on the order form

**Files:**
- Create: `resources/js/components/dentists/dentists-manager-dialog.tsx`
- Modify: `resources/js/components/order-form.tsx`

**Interfaces:**
- Consumes: `DentistForm` from Task 2.
- Produces: `resources/js/components/dentists/dentists-manager-dialog.tsx` default-exports

  ```ts
  export default function DentistsManagerDialog(props: {
      open: boolean;
      onOpenChange: (open: boolean) => void;
      dentists: Dentist[];
  }): JSX.Element
  ```

  A single `Dialog` with an internal `list` ⇄ `form` view. It renders from the `dentists` array it is given — no fetching. Task 5 adds the price-sync effect that reacts to that array changing.

- [ ] **Step 1: Create the manager dialog**

Create `resources/js/components/dentists/dentists-manager-dialog.tsx`:

```tsx
import { router } from '@inertiajs/react';
import { ArrowRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import DentistForm from '@/components/dentists/dentist-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Dentist } from '@/types';

/**
 * Full dentist management from inside the order form: add, edit (including the
 * price list) and delete without leaving a half-typed order.
 *
 * One Dialog with two views rather than a dialog opening another dialog —
 * nested Radix dialogs bring focus-trap and portal problems for no benefit.
 */
export default function DentistsManagerDialog({
    open,
    onOpenChange,
    dentists,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    dentists: Dentist[];
}) {
    const [view, setView] = useState<'list' | 'form'>('list');
    const [editing, setEditing] = useState<Dentist | null>(null);

    // Always reopen on the list, never on whichever form was left behind.
    useEffect(() => {
        if (open) {
            setView('list');
            setEditing(null);
        }
    }, [open]);

    const showList = () => {
        setView('list');
        setEditing(null);
    };

    const handleDelete = (dentist: Dentist) => {
        if (!confirm(`هل أنت متأكد من حذف ${dentist.name}؟`)) {
            return;
        }
        // preserveState keeps the order draft underneath this dialog alive.
        // The server refuses the delete if the dentist has orders or payments
        // and flashes an error instead — the refreshed list below is the
        // source of truth either way.
        router.delete(`/dentists/${dentist.id}`, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {view === 'list'
                            ? 'إدارة الأطباء'
                            : editing
                              ? 'تعديل طبيب'
                              : 'إضافة طبيب جديد'}
                    </DialogTitle>
                    <DialogDescription>
                        {view === 'list'
                            ? 'أضف طبيباً أو عدّل بياناته وأسعاره دون مغادرة الطلب'
                            : 'تنطبق الأسعار الجديدة على عناصر الطلب الحالي فوراً'}
                    </DialogDescription>
                </DialogHeader>

                {view === 'list' ? (
                    <div className="space-y-4">
                        <Button
                            type="button"
                            variant="outline"
                            className="gap-2"
                            onClick={() => {
                                setEditing(null);
                                setView('form');
                            }}
                        >
                            <Plus className="size-4" />
                            إضافة طبيب
                        </Button>

                        {dentists.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                لا يوجد أطباء بعد
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>الاسم</TableHead>
                                            <TableHead>الهاتف</TableHead>
                                            <TableHead>أنواع العمل</TableHead>
                                            <TableHead className="text-end">
                                                الإجراءات
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {dentists.map((dentist) => (
                                            <TableRow key={dentist.id}>
                                                <TableCell className="font-medium">
                                                    {dentist.name}
                                                </TableCell>
                                                <TableCell>
                                                    {dentist.phone || '-'}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {
                                                        Object.keys(
                                                            dentist.price_list ??
                                                                {},
                                                        ).length
                                                    }
                                                </TableCell>
                                                <TableCell className="text-end">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            title="تعديل الطبيب وأسعاره"
                                                            onClick={() => {
                                                                setEditing(
                                                                    dentist,
                                                                );
                                                                setView('form');
                                                            }}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            title="حذف الطبيب"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    dentist,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="space-y-4">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-fit gap-2 text-muted-foreground"
                            onClick={showList}
                        >
                            <ArrowRight className="size-4" />
                            رجوع إلى القائمة
                        </Button>

                        <DentistForm
                            key={editing?.id ?? 'new'}
                            dentist={editing}
                            onSaved={showList}
                            onCancel={showList}
                        />
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Add the trigger button to the order form**

In `resources/js/components/order-form.tsx`, extend the lucide import on line 2 and the react import on line 3:

```tsx
import { Check, Info, Plus, Trash2, Users } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
```

Add the component import next to the `DentalChart` import:

```tsx
import DentistsManagerDialog from '@/components/dentists/dentists-manager-dialog';
```

Add the open state next to the other hooks, just above `const total = ...`:

```tsx
    const [dentistsDialogOpen, setDentistsDialogOpen] = useState(false);
```

- [ ] **Step 3: Wrap the dentist select so the button sits beside it**

Still in `order-form.tsx`, replace the الطبيب `<Select>` block (currently lines 201–218) so the select and the new button share a row. The `<Label>` above and the `<InputError>` below stay untouched:

```tsx
                        <div className="flex items-center gap-2">
                            <Select
                                value={data.dentist_id}
                                onValueChange={handleDentistChange}
                            >
                                <SelectTrigger
                                    className={cn(fieldClass, 'flex-1')}
                                >
                                    <SelectValue placeholder="اختر الطبيب" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dentists.map((dentist) => (
                                        <SelectItem
                                            key={dentist.id}
                                            value={dentist.id.toString()}
                                        >
                                            {dentist.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                variant="outline"
                                title="إدارة الأطباء والأسعار"
                                onClick={() => setDentistsDialogOpen(true)}
                                className="size-11 shrink-0 rounded-[10px] p-0"
                            >
                                <Users className="size-4" />
                            </Button>
                        </div>
```

- [ ] **Step 4: Mount the dialog**

Still in `order-form.tsx`, add the dialog as the last child inside the `<form>`, immediately before its closing `</form>` tag (after the total/submit `</section>`):

```tsx
            <DentistsManagerDialog
                open={dentistsDialogOpen}
                onOpenChange={setDentistsDialogOpen}
                dentists={dentists}
            />
```

- [ ] **Step 5: Verify types, lint and formatting**

```bash
npm run types && npm run lint && npm run format
```

Expected: exit 0.

- [ ] **Step 6: Verify the draft survives a save — the critical check**

At `http://dental.test/orders/create`: pick a dentist, add two items, type a patient name into each. Now click the **إدارة الأطباء** button, open ✏ on any dentist, change nothing, press تحديث. Expected: the dialog returns to the list, the success flash shows, and **both items and their patient names are still there**. If the items vanish, `preserveState` is missing somewhere — fix that before continuing; nothing else in this feature matters if the draft is lost.

Then, still in the dialog: add a brand-new dentist and confirm it appears in the list **and** in the الطبيب dropdown after closing.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/dentists/dentists-manager-dialog.tsx resources/js/components/order-form.tsx
git commit -m "feat(orders): manage dentists and their prices from a dialog on the order form"
```

---

### Task 5: Live price sync onto the open draft

**Files:**
- Modify: `resources/js/components/order-form.tsx`

**Interfaces:**
- Consumes: the `dentists` prop refreshed by Task 1's `back()` redirect, and the dialog from Task 4.
- Produces: no new exports. Two effects inside `OrderForm`: prices of draft items follow price-list edits, and a deleted dentist clears the selection.

- [ ] **Step 1: Add the price-sync effect**

In `resources/js/components/order-form.tsx`, add this immediately after the existing `scrollToLastItem` effect (the one ending on line 124) so the item-mutating logic stays together:

```tsx
    // A price edited in the dentists dialog must land on the items already
    // typed — that is the whole point of being able to open it mid-order.
    // Snapshot the selected dentist's list, and when a refreshed `dentists`
    // prop arrives, rewrite only the items whose work type actually changed
    // price. Types that did not change are left alone, so a one-off price
    // typed by hand survives opening and closing the dialog.
    const priceSnapshot = useRef<{
        dentistId: string;
        priceList: Record<string, number>;
    } | null>(null);

    useEffect(() => {
        const priceList =
            dentists.find((d) => d.id.toString() === data.dentist_id)
                ?.price_list ?? {};
        const previous = priceSnapshot.current;
        priceSnapshot.current = { dentistId: data.dentist_id, priceList };

        // First run, or the user switched dentist — handleDentistChange has
        // already refilled the prices, so there is nothing to diff against.
        if (!previous || previous.dentistId !== data.dentist_id) {
            return;
        }

        const changed = new Map<string, number>();
        for (const [type, price] of Object.entries(priceList)) {
            const before = previous.priceList[type];
            // `undefined` covers a work type newly added to the list: an item
            // typed free-hand under that name adopts the list price too.
            if (before === undefined || Math.round(before) !== Math.round(price)) {
                changed.set(type, Math.round(price));
            }
        }

        if (changed.size === 0) {
            return;
        }

        setData((prev) => ({
            ...prev,
            items: prev.items.map((item) => {
                const price = changed.get(item.type);
                return price === undefined ? item : { ...item, price };
            }),
        }));
    }, [dentists, data.dentist_id, setData]);
```

- [ ] **Step 2: Add the deleted-dentist guard**

Directly below the effect from Step 1, add:

```tsx
    // If the selected dentist is deleted from the dialog, drop the dangling
    // selection rather than submitting an id the server will reject. Driven by
    // the refreshed list, not by the delete callback, so a delete the server
    // refused (dentist has orders or payments) correctly changes nothing.
    useEffect(() => {
        if (
            data.dentist_id &&
            !dentists.some((d) => d.id.toString() === data.dentist_id)
        ) {
            setData('dentist_id', '');
        }
    }, [dentists, data.dentist_id, setData]);
```

- [ ] **Step 3: Verify types, lint and formatting**

```bash
npm run types && npm run lint && npm run format
```

Expected: exit 0. `npm run lint` runs `eslint-plugin-react-hooks` — if it complains about the dependency arrays, do **not** silence it with a disable comment; the arrays above are already exhaustive. Listing `setData` is safe even if Inertia gives it a fresh identity each render: the effect would then run every render, but it rewrites the snapshot ref each time, so every run after the first finds nothing changed and does nothing. There is no update loop.

- [ ] **Step 4: Verify the four price-sync behaviours by hand**

At `http://dental.test/orders/create`, pick a dentist who has `خزف` in their price list:

1. **Raise:** add an item of type `خزف`, note the auto-filled price. Open the dialog → ✏ that dentist → raise `خزف` by 20000 → تحديث. Expected: the item's price and the المجموع الكلي both go up immediately.
2. **Lower:** repeat, lowering the price. Expected: it follows down too.
3. **Untouched types survive:** add a second item of a different type and type a custom price into it by hand. Now change only `خزف`'s price in the dialog. Expected: the hand-typed price on the other item is **unchanged**.
4. **Deleted dentist:** select a dentist with no orders or payments, then delete them from the dialog. Expected: the الطبيب field goes back to اختر الطبيب, and the items stay as typed. Then try deleting a dentist who *does* have orders. Expected: an error flash, the dentist stays in the list, and the selection is untouched.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/order-form.tsx
git commit -m "feat(orders): apply dentist price edits to the items already in the draft"
```

---

### Task 6: Full gate and regression sweep

**Files:**
- No source changes expected. If the gate fails, fix in place and note what changed.

**Interfaces:**
- Consumes: Tasks 1–5.
- Produces: a green quality gate and a verified feature.

- [ ] **Step 1: Run the full quality gate**

Use the **`run-checks` skill** — it encodes the host/container split that makes bare `php artisan test` fail here. It covers Pest, Pint and `tsc --noEmit`.

Expected: all three green. Do not claim completion on a partial run; if something fails, report the actual output.

- [ ] **Step 2: Regression-check the order flow end to end**

At `http://dental.test/orders/create`: build a real order — dentist, two items with teeth selected on the dental chart, patient names, notes — and **save it**. Expected: it lands in `/orders` with the right total. Open it for edit (`/orders/{id}/edit`), open the dentists dialog from there too, change a price, and confirm the edit form's items update the same way and the order still saves.

- [ ] **Step 3: Regression-check the untouched paths**

Confirm these still behave as before: `/dentists` list and delete, `/dentists/create`, `/dentists/{id}/edit`, and the الفواتير and الأرصدة المستحقة reports for a dentist whose price you changed (past orders must keep their original prices — the price list is a default for new items, never a retroactive rewrite).

- [ ] **Step 4: Commit anything the gate forced**

```bash
git add -A
git commit -m "chore(dentists): fixes from the full check gate"
```

Skip this step if the working tree is clean.

---

## Notes for the implementer

- **Deploying:** production needs an image **rebuild**, not a restart (`npm run build` happens at build time). Use the `deploy` skill; the user runs the VPS commands themselves.
- **If the order draft is ever lost during a dialog save,** the cause is almost always a missing `preserveState: true` on an Inertia request — check `dentist-form.tsx` and `dentists-manager-dialog.tsx` first.
- The `to_index` field is deliberately not in `StoreDentistRequest`/`UpdateDentistRequest` rules. Do not "fix" that by adding it — `validated()` excluding it is what keeps it out of the model.
