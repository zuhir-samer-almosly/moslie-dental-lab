<?php

namespace App\Http\Controllers;

use App\Concerns\ParsesDate;
use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Account;
use App\Models\Dentist;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Spatie\Browsershot\Browsershot;

/**
 * The read-only accounting views. Nothing here writes to the ledger — every
 * entry originates from a domain record.
 */
class LedgerController extends Controller
{
    use ParsesDate;

    public function __construct(private readonly LedgerReports $reports) {}

    /** ميزان المراجعة — proof that the books balance. */
    public function trialBalance(Request $request)
    {
        $asOf = $this->parseDate($request->query('as_of'));
        $accounts = $this->reports->trialBalance($asOf);

        $debit = (int) $accounts->sum('debit');
        $credit = (int) $accounts->sum('credit');

        return inertia('ledger/trial-balance', [
            'accounts' => $accounts,
            'totals' => ['debit' => $debit, 'credit' => $credit],
            'balanced' => $debit === $credit,
            'filters' => ['as_of' => $asOf],
        ]);
    }

    /** الصندوق — cash balance and every movement through it. */
    public function cash(Request $request)
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        return inertia('ledger/cash', [
            'balance' => $this->reports->balance(AccountCode::CASH->value, $to),
            'lines' => $this->reports->accountLines(AccountCode::CASH->value, $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    /** قيود اليومية — every entry, for tracing a figure back to its source. */
    public function journal(Request $request)
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));
        $account = $request->query('account');

        $entries = JournalEntry::query()
            ->with(['lines.account', 'lines.dentist'])
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->when($account, fn ($q) => $q->whereHas(
                'lines.account',
                fn ($a) => $a->where('code', $account),
            ))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // `entry_date` carries a `date` cast, so Eloquent's default array
        // serialization would render it as an ISO-8601 timestamp
        // (`2026-06-15T00:00:00.000000Z`) instead of the bare `Y-m-d` every
        // other ledger page shows. Reformat it here, on the response array,
        // rather than touching the model's cast or its set-mutator — both of
        // which other posting paths and LedgerReports depend on.
        $entries->getCollection()->transform(fn (JournalEntry $entry) => [
            ...$entry->toArray(),
            'entry_date' => $entry->entry_date->toDateString(),
        ]);

        return inertia('ledger/journal', [
            'entries' => $entries,
            'accounts' => Account::orderBy('sort_order')->get(['code', 'name']),
            'filters' => ['from' => $from, 'to' => $to, 'account' => $account],
        ]);
    }

    /** كشف حساب — one dentist's receivable movements with a running balance. */
    public function statement(Request $request)
    {
        return inertia('ledger/statement', [
            ...$this->buildStatement($request),
            // Only the authenticated page needs the picker list — the
            // print-view/PDF path is reached over a signed URL with no auth
            // boundary but the signature, so it gets just the one dentist
            // buildStatement() already narrowed, not the whole roster.
            'dentists' => Dentist::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * The same statement without app chrome, rendered by headless Chromium.
     * Outside the auth group — the browser has no session — with the signed
     * middleware as the gate, exactly as invoices.print-view works.
     */
    public function statementPrintView(Request $request)
    {
        return inertia('ledger/statement-print', $this->buildStatement($request));
    }

    /** Render the statement as an A4 portrait PDF. */
    public function statementPdf(Request $request)
    {
        $data = $this->buildStatement($request);

        abort_if($data['statement'] === null, 422, 'اختر طبيباً أولاً.');

        // Signed as a relative URL, then pointed at the container-internal
        // host, exactly as InvoiceController::pdf does.
        $path = URL::temporarySignedRoute(
            'ledger.statement.print-view',
            now()->addMinutes(config('pdf.signed_url_ttl')),
            array_filter($data['filters'], fn ($value) => $value !== null),
            absolute: false,
        );

        $browsershot = Browsershot::url(rtrim(config('pdf.internal_url'), '/').$path)
            ->noSandbox() // the container runs as root
            // Chromium's default /dev/shm is tiny in Docker and it crashes
            // mid-render without this.
            ->addChromiumArguments(['disable-dev-shm-usage'])
            ->emulateMedia('print')
            ->format('A4')
            // The print page has no `<main>` padding to fall back on (it's a
            // bare div, not the invoice's layout), and puppeteer's actual
            // default margin isn't something we can verify in this
            // environment — so set it explicitly rather than trust a
            // default. 15mm ~= the invoice's 1.5cm.
            ->margins(15, 15, 15, 15)
            ->showBackground()
            ->waitUntilNetworkIdle(strict: false)
            ->timeout(120);

        if ($chromePath = config('pdf.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        if ($node = config('pdf.node_binary')) {
            $browsershot->setNodeBinary($node);
        }

        if ($npm = config('pdf.npm_binary')) {
            $browsershot->setNpmBinary($npm);
        }

        return response(
            $browsershot->pdf(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="statement.pdf"',
            ],
        );
    }

    /** @return array{statement: array|null, dentist: Dentist|null, filters: array} */
    private function buildStatement(Request $request): array
    {
        $dentistId = $request->integer('dentist_id') ?: null;
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        // Narrowed to the two fields the page actually renders — the full
        // model carries price_list, phone and address, which have no
        // business traveling into the signed print-view URL's payload. The
        // full dentist roster is narrower still: it isn't rendered by the
        // print view/PDF at all, so it lives in statement() instead of here
        // — shared here, it would ride along on every unauthenticated
        // signed-URL request too.
        $dentist = $dentistId ? Dentist::find($dentistId, ['id', 'name']) : null;

        return [
            'statement' => $dentist
                ? $this->reports->dentistStatement($dentist->id, $from, $to)
                : null,
            'dentist' => $dentist,
            'filters' => ['dentist_id' => $dentistId, 'from' => $from, 'to' => $to],
        ];
    }
}
