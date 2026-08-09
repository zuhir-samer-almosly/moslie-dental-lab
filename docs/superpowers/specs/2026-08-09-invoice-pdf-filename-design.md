# Invoice PDF download filename

**Date:** 2026-08-09
**Branch:** `feature/invoice-pdf-filename` (off `feature/combobox-refactor`)

## Problem

The invoices page downloads its PDF as `فاتورة-2026-07-31-2026-09-01.pdf`. When the
report is filtered to a single dentist, that name says nothing about who the invoice
is for, so the file has to be renamed by hand before it can be sent to the doctor.

## Goal

When the report is filtered to one dentist, the downloaded file is already named for
that dentist. Nothing else about the report changes — not the on-screen heading, not
the PDF's contents, not the totals.

## Format

| Case | Filename |
| --- | --- |
| One dentist, male | `الدكتور العلي المحترم 2026-07-31 - 2026-09-01.pdf` |
| One dentist, female | `الدكتورة سارة المحترمة 2026-07-31 - 2026-09-01.pdf` |
| No dentist filter | `فاتورة-2026-07-31-2026-09-01.pdf` (unchanged) |

The date range follows the name and uses the same `Y-m-d` bounds already sent to the
PDF endpoint. Dentist names are stripped of characters that are illegal or awkward in
filenames — `/ \ : * ? " < > |` and control characters — before being interpolated.

## Where the name is built

The filename exists in two places today:

- `app/Http/Controllers/InvoiceController.php:97` — the `streamDownload` name.
- `resources/js/pages/invoices/index.tsx:71` — the `link.download` on the blob URL.

The client fetches the PDF as a blob and sets `link.download`, so **the client name is
the one the user sees**. The server name only surfaces if `/invoices/pdf?...` is opened
directly.

**Decision: build the name on the client, and mirror the same format on the server.**

The client already has the `dentists` prop and the selected `dentist_id`, so it needs
no new data. The server gets the same format so a direct URL hit cannot contradict the
app. The rejected alternative — server as sole source, client parsing
`Content-Disposition` — would require RFC 5987 UTF-8 decoding of Arabic in JavaScript
for a string the user never sees.

## Honorific helper

The gendered honorific words are inline in `resources/js/components/invoice-report.tsx:160`:

```
{group.gender === 'female' ? 'الدكتورة' : 'الدكتور'} : {group.name} {group.gender === 'female' ? 'المحترمة' : 'المحترم'}
```

Export a `dentistHonorific(gender)` helper from that file returning `{ title, respect }`,
and have both the heading and the filename read from it, so the two cannot drift. The
heading keeps its exact current rendering, colon included — there is no visual change to
the report.

## Which dentist

The filename reads `data.dentist_id` — the live form state — not `filters.dentist_id`.
`data` is what the fetch actually sends to `/invoices/pdf`, so the file is named for the
report inside it rather than for whatever was last submitted. This matches how the
existing code already sources `data.from` / `data.to` for the filename.

## Testing

`tests/Feature/InvoiceTest.php` does not cover `pdf()` and cannot: the method shells out
to headless Chromium through Browsershot. This change therefore ships without a feature
test, verified instead by downloading a real invoice from the local Docker app — once
filtered to a male dentist, once to a female dentist, once with no dentist selected.

`npm run types`, ESLint, Prettier and Pint all run as usual.

## Out of scope

- The ledger statement PDF (`app/Http/Controllers/LedgerController.php:183`, hardcoded
  `statement.pdf`). It opens inline in a new tab rather than downloading as a blob — a
  different pattern, and not part of this request.
- The on-screen doctor heading format. It already reads correctly.
