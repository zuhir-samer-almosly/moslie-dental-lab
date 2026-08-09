# Invoice PDF Filename Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When the invoices report is filtered to a single dentist, the downloaded PDF is already named for that dentist instead of `فاتورة-<from>-<to>.pdf`.

**Architecture:** The filename is built in two places that must agree. The client sets `link.download` on the fetched blob, which is the name the user actually sees; the server's `streamDownload` name only surfaces on a direct hit to `/invoices/pdf`. Task 1 puts the server format in a testable class. Task 2 mirrors it on the client and pulls the Arabic honorific words into one exported helper so the report heading and the filename can never drift apart.

**Tech Stack:** Laravel 12, Pest 4, Inertia 2, React 19 + TypeScript (strict), Tailwind 4.

## Global Constraints

- UI language is Arabic / RTL. All user-facing strings in this plan are Arabic and must be copied verbatim — do not transliterate, translate, or reorder the words.
- Target filename format, single dentist: `<honorific> <name> <respect> <from> - <to>.pdf` — e.g. `الدكتور العلي المحترم 2026-07-31 - 2026-09-01.pdf`.
- Target filename format, no dentist filter: `فاتورة-<from>-<to>.pdf` — unchanged from today.
- `<from>` and `<to>` are the `Y-m-d` bounds already sent to the PDF endpoint. Do not reformat them.
- Honorifics: male → title `الدكتور`, respect `المحترم`. Female → title `الدكتورة`, respect `المحترمة`.
- Dentist names are stripped of `/ \ : * ? " < > |` and control characters before interpolation; a name that sanitizes down to nothing falls back to the no-dentist filename.
- The on-screen report must render **identically** to before — the doctor heading keeps its current `الدكتور : العلي المحترم` form, colon included.
- `pdf()` shells out to headless Chromium through Browsershot and cannot be feature-tested. Do not add a test that calls the `invoices.pdf` route.
- Run Pest through the `run-checks` skill (host execution with storage/cache env redirects), never bare `php artisan test`.

---

### Task 1: Server-side filename class

**Files:**
- Create: `app/Support/InvoiceFilename.php`
- Create: `tests/Feature/InvoiceFilenameTest.php`
- Modify: `app/Http/Controllers/InvoiceController.php` (imports near line 12; the `streamDownload` call at lines 95-99)

**Interfaces:**
- Consumes: `App\Models\Dentist` (has `name: string` and `gender: 'male'|'female'`, both fillable).
- Produces: `App\Support\InvoiceFilename::for(?Dentist $dentist, string $from, string $to): string`. Task 2 mirrors this format in TypeScript but does not import from it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/InvoiceFilenameTest.php`. It lives in `Feature` so the app is booted (Pest binds `Tests\TestCase` + `RefreshDatabase` only to that directory), but it never touches the database — the dentists are unsaved model instances.

```php
<?php

use App\Models\Dentist;
use App\Support\InvoiceFilename;

function dentistNamed(string $name, string $gender = 'male'): Dentist
{
    return new Dentist(['name' => $name, 'gender' => $gender]);
}

it('names the file after a male dentist', function () {
    expect(InvoiceFilename::for(dentistNamed('العلي'), '2026-07-31', '2026-09-01'))
        ->toBe('الدكتور العلي المحترم 2026-07-31 - 2026-09-01.pdf');
});

it('uses the feminine honorific for a female dentist', function () {
    expect(InvoiceFilename::for(dentistNamed('سارة', 'female'), '2026-07-31', '2026-09-01'))
        ->toBe('الدكتورة سارة المحترمة 2026-07-31 - 2026-09-01.pdf');
});

it('keeps the generic name when no dentist is selected', function () {
    expect(InvoiceFilename::for(null, '2026-07-31', '2026-09-01'))
        ->toBe('فاتورة-2026-07-31-2026-09-01.pdf');
});

it('strips path separators and other filename-hostile characters', function () {
    expect(InvoiceFilename::for(dentistNamed('أحمد/علي: "ب"'), '2026-07-31', '2026-09-01'))
        ->toBe('الدكتور أحمد علي ب 2026-07-31 - 2026-09-01.pdf');
});

it('falls back to the generic name when the dentist name sanitizes to nothing', function () {
    expect(InvoiceFilename::for(dentistNamed('///'), '2026-07-31', '2026-09-01'))
        ->toBe('فاتورة-2026-07-31-2026-09-01.pdf');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run the suite via the `run-checks` skill, filtered:

```
php artisan test --filter=InvoiceFilename
```

Expected: FAIL — `Class "App\Support\InvoiceFilename" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/InvoiceFilename.php`:

```php
<?php

namespace App\Support;

use App\Models\Dentist;

/**
 * The name the invoice PDF downloads as.
 *
 * Filtered to a single dentist, the file names itself after them — these get
 * forwarded straight to the doctor, so a name you would fix by hand is a name
 * you would fix every month.
 *
 * `resources/js/pages/invoices/index.tsx` mirrors this format: it sets
 * `link.download` on the fetched blob, and that is the name the user actually
 * sees. This copy covers a direct hit on /invoices/pdf, and the two must stay
 * in step.
 */
class InvoiceFilename
{
    public static function for(?Dentist $dentist, string $from, string $to): string
    {
        $generic = "فاتورة-{$from}-{$to}.pdf";

        if (! $dentist) {
            return $generic;
        }

        $name = self::sanitize($dentist->name);

        if ($name === '') {
            return $generic;
        }

        [$title, $respect] = $dentist->gender === 'female'
            ? ['الدكتورة', 'المحترمة']
            : ['الدكتور', 'المحترم'];

        return "{$title} {$name} {$respect} {$from} - {$to}.pdf";
    }

    /**
     * Drop the characters that break a filename on the platforms these
     * invoices land on, then collapse the whitespace that removing them
     * leaves behind.
     */
    private static function sanitize(string $name): string
    {
        $stripped = preg_replace('#[/\\\\:*?"<>|\x00-\x1F]+#u', ' ', $name) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```
php artisan test --filter=InvoiceFilename
```

Expected: PASS, 5 tests.

- [ ] **Step 5: Wire it into the controller**

In `app/Http/Controllers/InvoiceController.php`, add the import alongside the existing `use` block (after `use App\Models\Order;`):

```php
use App\Support\InvoiceFilename;
```

Then replace the `streamDownload` call at the end of `pdf()`:

```php
        $contents = $browsershot->pdf();

        return response()->streamDownload(
            fn () => print ($contents),
            "فاتورة-{$report['filters']['from']}-{$report['filters']['to']}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
```

with:

```php
        $contents = $browsershot->pdf();

        $dentistId = $report['filters']['dentist_id'];

        return response()->streamDownload(
            fn () => print ($contents),
            InvoiceFilename::for(
                $dentistId ? $report['dentists']->firstWhere('id', (int) $dentistId) : null,
                $report['filters']['from'],
                $report['filters']['to'],
            ),
            ['Content-Type' => 'application/pdf'],
        );
```

`$report['dentists']` is the `Dentist::all()` collection already built by `buildReport()`, so this adds no query.

- [ ] **Step 6: Run the full invoice suite and Pint**

```
php artisan test --filter=Invoice
composer lint
```

Expected: all invoice tests pass; Pint reports no style changes needed (or fixes them, in which case re-run the tests).

- [ ] **Step 7: Commit**

```bash
git add app/Support/InvoiceFilename.php tests/Feature/InvoiceFilenameTest.php app/Http/Controllers/InvoiceController.php
git commit -m "feat(invoices): name the PDF after the dentist it bills"
```

---

### Task 2: Client-side filename and shared honorific helper

**Files:**
- Modify: `resources/js/components/invoice-report.tsx` (add exported helper near the top; the heading at lines 156-162)
- Modify: `resources/js/pages/invoices/index.tsx` (import at line 5; `handleDownloadPdf` around lines 51-81)

**Interfaces:**
- Consumes: nothing from Task 1 — this is the mirrored copy of the same format.
- Produces: `dentistHonorific(gender: 'male' | 'female'): { title: string; respect: string }`, exported from `@/components/invoice-report`.

- [ ] **Step 1: Add the honorific helper to `invoice-report.tsx`**

Insert this immediately after the `InvoiceData` type (after the closing `};` of `export type InvoiceData`, before `groupByDentist`):

```tsx
/**
 * The gendered Arabic honorific that wraps a dentist's name. Exported because
 * the PDF names its file with the same words the heading prints, and the two
 * drifting apart is exactly the kind of thing nobody notices until a doctor
 * gets a file addressed to the wrong gender.
 */
export function dentistHonorific(gender: 'male' | 'female'): {
    title: string;
    respect: string;
} {
    return gender === 'female'
        ? { title: 'الدكتورة', respect: 'المحترمة' }
        : { title: 'الدكتور', respect: 'المحترم' };
}
```

- [ ] **Step 2: Point the heading at the helper**

In the same file, the groups map currently opens at line 156 with an expression body:

```tsx
                    groups.map((group) => (
                        <div key={group.id} className="space-y-2">
                            <div className="text-center">
                                <h4 className="text-2xl font-bold">
                                    {group.gender === 'female' ? 'الدكتورة' : 'الدكتور'} : {group.name} {group.gender === 'female' ? 'المحترمة' : 'المحترم'}
                                </h4>
                            </div>
```

Convert it to a block body so the honorific is destructured once, keeping the rendered text byte-for-byte identical:

```tsx
                    groups.map((group) => {
                        const { title, respect } = dentistHonorific(group.gender);

                        return (
                        <div key={group.id} className="space-y-2">
                            <div className="text-center">
                                <h4 className="text-2xl font-bold">
                                    {title} : {group.name} {respect}
                                </h4>
                            </div>
```

Then close the block body. The map currently ends (just before the `)}` that closes the ternary) with:

```tsx
                        </div>
                    ))
```

which becomes:

```tsx
                        </div>
                        );
                    })
```

Indentation will be off after this edit — Step 5 runs Prettier, which fixes it. Do not hand-reindent.

- [ ] **Step 3: Build the filename in `index.tsx`**

Extend the existing import on line 5:

```tsx
import { InvoiceReport, type InvoiceData } from '@/components/invoice-report';
```

to:

```tsx
import {
    dentistHonorific,
    InvoiceReport,
    type InvoiceData,
} from '@/components/invoice-report';
```

Then add this helper inside the component, immediately above `handleDownloadPdf` (i.e. after `handlePrint`, before the `handleDownloadPdf` doc comment):

```tsx
    /**
     * What lands in the downloads folder. Filtered to one dentist, the file
     * names itself after them — these get forwarded straight to the doctor, so
     * a name you would fix by hand is a name you would fix every month.
     * Mirrors App\Support\InvoiceFilename on the server.
     *
     * Reads `data`, not `filters`: `data` is what the fetch below actually
     * sends, so the file is named for the report inside it rather than for
     * whatever was last submitted.
     */
    const pdfFilename = () => {
        const generic = `فاتورة-${data.from}-${data.to}.pdf`;

        const dentist = props.dentists.find(
            (candidate) => candidate.id.toString() === data.dentist_id,
        );

        if (!dentist) {
            return generic;
        }

        const name = dentist.name
            // eslint-disable-next-line no-control-regex
            .replace(/[/\\:*?"<>|\u0000-\u001F]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        if (!name) {
            return generic;
        }

        const { title, respect } = dentistHonorific(dentist.gender);

        return `${title} ${name} ${respect} ${data.from} - ${data.to}.pdf`;
    };
```

- [ ] **Step 4: Use it for the download**

In `handleDownloadPdf`, replace line 71:

```tsx
            link.download = `فاتورة-${data.from}-${data.to}.pdf`;
```

with:

```tsx
            link.download = pdfFilename();
```

- [ ] **Step 5: Type-check, lint, format**

```bash
npm run types
npm run lint
npm run format
```

Expected: `tsc --noEmit` clean; ESLint clean (the `no-control-regex` disable comment is what keeps Step 3's regex from erroring); Prettier reformats `invoice-report.tsx` after the Step 2 block-body change.

- [ ] **Step 6: Verify the report renders unchanged**

The app runs in Docker, not Herd. Bring it up if it is not already running:

```bash
./docker-start-local.sh
```

Open `http://dental.test/invoices`, set a date range covering some orders, and confirm the doctor heading still reads `الدكتور : <name> المحترم` exactly as before — this step is a regression check on Step 2, not a new feature.

- [ ] **Step 7: Verify the three filenames**

Still on `http://dental.test/invoices`, click **تحميل PDF** three times and check the downloaded filename each time:

1. Filtered to a male dentist → `الدكتور <name> المحترم <from> - <to>.pdf`
2. Filtered to a female dentist → `الدكتورة <name> المحترمة <from> - <to>.pdf`
3. الطبيب (اختياري) cleared → `فاتورة-<from>-<to>.pdf`

If no female dentist exists in the local data, set one temporarily from the أطباء الأسنان page.

- [ ] **Step 8: Commit**

```bash
git add resources/js/components/invoice-report.tsx resources/js/pages/invoices/index.tsx
git commit -m "feat(invoices): download the PDF under the dentist's name"
```

---

## Not in scope

- The ledger statement PDF (`app/Http/Controllers/LedgerController.php:183`, hardcoded `statement.pdf`). It opens inline in a new tab rather than downloading as a blob — a different pattern, and not part of this request.
- Any change to the report's contents, totals, or heading format.
- A frontend unit test for `pdfFilename` — the project has no JavaScript test runner, and adding one for this is out of proportion. Task 1's Pest suite is the executable spec for the format; Task 2 is verified by hand in Step 7.
