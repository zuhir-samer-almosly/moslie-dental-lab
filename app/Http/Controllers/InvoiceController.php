<?php

namespace App\Http\Controllers;

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Browsershot\Browsershot;

class InvoiceController extends Controller
{
    public function __construct(private readonly LedgerReports $reports) {}

    public function index(Request $request)
    {
        return inertia('invoices/index', $this->buildReport($request));
    }

    /**
     * The same report with no app chrome, rendered by headless Chromium on its
     * way to a PDF. Reached only through the short-lived signed URL minted in
     * pdf() below — the browser has no session to authenticate with.
     */
    public function printView(Request $request)
    {
        return inertia('invoices/print', $this->buildReport($request));
    }

    /**
     * Render the invoice as a real A4 landscape PDF.
     *
     * Printing from the browser cannot be made to work: CSS `@page { size:
     * landscape }` sets the layout box, but the sheet orientation comes from
     * the print dialog's Layout dropdown, which no stylesheet can touch. Left
     * on Portrait — the default — the driver rotates the wide render onto a
     * narrow sheet and the invoice comes out sideways and shrunken.
     *
     * Driving Chromium ourselves means passing landscape explicitly, so the
     * output is correct no matter what any dialog says. It renders the live
     * print page rather than a separate PHP template so the figures can never
     * diverge from what the user saw on screen.
     */
    public function pdf(Request $request)
    {
        $report = $this->buildReport($request);

        abort_if($report['orders'] === null, 422, 'حدد فترة صالحة أولاً.');

        // Signed as a relative URL, then pointed at the container-internal
        // host: nginx and php-fpm share this container, so there is no reason
        // to send the request back out through the public domain.
        $path = URL::temporarySignedRoute(
            'invoices.print-view',
            now()->addMinutes(config('pdf.signed_url_ttl')),
            array_filter($report['filters'], fn ($value) => $value !== null),
            absolute: false,
        );

        $browsershot = Browsershot::url(rtrim(config('pdf.internal_url'), '/').$path)
            ->noSandbox() // the container runs as root
            // Chromium's default /dev/shm is tiny in Docker and it crashes
            // mid-render without this.
            ->addChromiumArguments(['disable-dev-shm-usage'])
            // Apply the tuned @media print rules from app.css, so the PDF and
            // the browser's own print preview render identically.
            ->emulateMedia('print')
            ->format('A4')
            ->landscape()
            // Zero here on purpose: the page margin is the `main` padding that
            // those same print rules already set. Adding margins on top would
            // double it.
            ->margins(0, 0, 0, 0)
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

        $contents = $browsershot->pdf();

        return response()->streamDownload(
            fn () => print ($contents),
            "فاتورة-{$report['filters']['from']}-{$report['filters']['to']}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Build the invoice payload shared by the on-screen page, the print page
     * and the PDF, so all three always agree.
     *
     * @return array<string, mixed>
     */
    private function buildReport(Request $request): array
    {
        // Parse the bounds defensively: garbage dates would otherwise slip
        // straight into the queries and produce a silently wrong report. Only
        // build the invoice when BOTH bounds parse; an inverted range is
        // swapped so the query stays valid.
        $start = $this->parseDate($request->input('from'));
        $end = $this->parseDate($request->input('to'));

        if ($start && $end && $start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $from = $start?->toDateString();
        $to = $end?->toDateString();
        $dentistId = $request->input('dentist_id');

        $orders = null;
        $payments = null;
        $totals = null;
        $openingByDentist = [];

        if ($from && $to) {
            // Filter by the order's own date (due_date) and the payment's
            // date, NOT created_at — an order can be entered today but dated
            // for a different month, and it belongs in that month's invoice.
            $ordersQuery = Order::with(['dentist', 'items'])
                ->billable()
                ->whereBetween('due_date', [$from, $to])
                ->orderBy('due_date');

            $paymentsQuery = DentistPayment::with('dentist')
                ->whereRaw('DATE(COALESCE(payment_date, created_at)) BETWEEN ? AND ?', [$from, $to])
                ->orderByRaw('COALESCE(payment_date, created_at)');

            if ($dentistId) {
                $ordersQuery->where('dentist_id', $dentistId);
                $paymentsQuery->where('dentist_id', $dentistId);
            }

            $orders = $ordersQuery->get();
            $payments = $paymentsQuery->get();

            // Opening balance: what each dentist owed the day before this
            // period began, read as their receivable balance as of that date.
            // This supersedes the old two-query subtraction, which had to
            // reproduce the same date asymmetry by hand.
            //
            // Note the intentional date asymmetry that formula used to
            // encode by hand: orders are bucketed by their own date
            // (`due_date`) while payments are bucketed by `payment_date`
            // (falling back to `created_at`). An order and the payment
            // settling it can therefore land in different periods — that is
            // by design and must stay consistent with the in-period queries
            // above. The ledger's postings preserve this (see OrderPosting
            // and DentistPaymentPosting), so reading `asOf` here keeps it.
            $asOf = Carbon::parse($from)->subDay()->toDateString();

            $openingByDentist = $this->reports->receivablesByDentist($asOf)
                ->when($dentistId, fn ($balances) => $balances->only([$dentistId]))
                ->reject(fn (int $balance) => $balance === 0)
                ->all();

            $openingTotal = array_sum($openingByDentist);

            $ordersTotal = $orders->sum('amount');
            $paymentsTotal = $payments->sum('amount');

            $totals = [
                'opening' => $openingTotal,
                'orders' => $ordersTotal,
                'payments' => $paymentsTotal,
                'balance' => $openingTotal + $ordersTotal - $paymentsTotal,
            ];
        }

        $dentists = Dentist::all();

        return [
            'orders' => $orders,
            'payments' => $payments,
            'totals' => $totals,
            'openingByDentist' => (object) $openingByDentist,
            'dentists' => $dentists,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'dentist_id' => $dentistId,
            ],
        ];
    }

    /**
     * Parse a Y-m-d bound, returning null for anything missing or malformed
     * so a bad value collapses to "no report" instead of a broken query.
     */
    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
