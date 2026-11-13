<?php

namespace App\Http\Controllers;

use App\Concerns\ParsesDate;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Money\Currency;
use App\Support\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * دفتر الطبيب — one dentist's notebook: every piece of work and every payment
 * in date order with the balance after each, the way the paper notebooks read.
 *
 * The detail (patient, work type, teeth) comes from the domain tables, which
 * are the only place it exists. The money that matters — the opening and
 * closing balance — is read from the ledger like every other figure.
 */
class NotebookController extends Controller
{
    use ParsesDate;

    public function __construct(private readonly LedgerReports $reports) {}

    public function index()
    {
        $balances = [
            Currency::SYP->value => $this->reports->receivablesByDentist(null, Currency::SYP),
            Currency::USD->value => $this->reports->receivablesByDentist(null, Currency::USD),
        ];

        return inertia('notebook/index', [
            'dentists' => Dentist::orderBy('name')
                ->get(['id', 'name', 'currency'])
                ->map(fn (Dentist $dentist) => [
                    'id' => $dentist->id,
                    'name' => $dentist->name,
                    'currency' => $dentist->billingCurrency()->value,
                    'balance' => (int) ($balances[$dentist->billingCurrency()->value][$dentist->id] ?? 0),
                ]),
        ]);
    }

    public function show(Request $request, Dentist $dentist)
    {
        return inertia('notebook/show', $this->build($request, $dentist));
    }

    /** The notebook without app chrome, rendered by headless Chromium. */
    public function printView(Request $request, Dentist $dentist)
    {
        return inertia('notebook/print', $this->build($request, $dentist));
    }

    public function pdf(Request $request, Dentist $dentist)
    {
        $filters = $this->build($request, $dentist)['filters'];

        return response(
            PdfRenderer::render('notebook.print-view', [
                'dentist' => $dentist->id,
                'from' => $filters['from'],
                'to' => $filters['to'],
                'all' => $filters['all'] ? 1 : null,
            ]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="notebook-'.$dentist->id.'.pdf"',
            ],
        );
    }

    /**
     * @return array{dentist: array<string, mixed>, opening: int, closing: int, lines: list<array<string, mixed>>, filters: array<string, mixed>}
     */
    private function build(Request $request, Dentist $dentist): array
    {
        [$from, $to, $all] = $this->range($request);
        $currency = $dentist->billingCurrency();

        // The ledger decides what was owed going in and coming out.
        $statement = $this->reports->dentistStatement($dentist->id, $from, $to, $currency);

        $running = $statement['opening'];

        $lines = $this->movements($dentist)
            ->filter(fn (array $line) => ($from === null || $line['date'] >= $from)
                && ($to === null || $line['date'] <= $to))
            ->sortBy([['date', 'asc'], ['created_at', 'asc']])
            ->values()
            ->map(function (array $line) use (&$running) {
                $running += $line['kind'] === 'payment' ? -$line['value'] : $line['value'];

                return [...$line, 'balance' => $running];
            })
            ->all();

        return [
            'dentist' => [
                'id' => $dentist->id,
                'name' => $dentist->name,
                'currency' => $currency->value,
            ],
            'opening' => $statement['opening'],
            'closing' => $statement['closing'],
            'lines' => $lines,
            'filters' => ['from' => $from, 'to' => $to, 'all' => $all],
        ];
    }

    /**
     * The period shown: the whole history when asked for, a given range, or
     * — by default — the current month, so the page opens small.
     *
     * @return array{0: ?string, 1: ?string, 2: bool}
     */
    private function range(Request $request): array
    {
        if ($request->boolean('all')) {
            return [null, null, true];
        }

        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        if ($from === null && $to === null) {
            return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), false];
        }

        return [$from, $to, false];
    }

    /**
     * Every line that moved this dentist's balance, dated the way the ledger
     * dates it (see OrderPosting): each item on its own date, an order with no
     * items on its due date, and any difference between an order's total and
     * its items as a line of its own — so the lines add up to the ledger.
     *
     * `value` is in the dentist's own currency: whole lira, or cents.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function movements(Dentist $dentist)
    {
        $lines = collect();

        $orders = Order::billable()->where('dentist_id', $dentist->id)->with('items')->get();

        foreach ($orders as $order) {
            $order->setRelation('dentist', $dentist);
            $dueDate = $order->due_date->toDateString();
            $total = $order->valueInOwnCurrency();

            // An order worth nothing posts nothing, whatever its items say.
            if ($total === 0) {
                continue;
            }

            $itemsTotal = 0;

            foreach ($order->items as $item) {
                $item->setRelation('order', $order);
                $value = $item->quantity * $item->valueInOwnCurrency();
                $itemsTotal += $value;

                $lines->push([
                    'kind' => 'work',
                    'key' => 'work-'.$item->id,
                    'date' => $item->meta['date'] ?? $dueDate,
                    'created_at' => $item->created_at?->toIso8601String(),
                    'value' => $value,
                    'type' => $item->type,
                    'patient_name' => $item->meta['patient_name'] ?? '',
                    'selected_teeth' => $item->meta['selected_teeth'] ?? [],
                    'quantity' => (int) $item->quantity,
                    'notes' => $item->notes,
                ]);
            }

            if ($total !== $itemsTotal) {
                $lines->push([
                    'kind' => 'work',
                    'key' => 'order-'.$order->id,
                    'date' => $dueDate,
                    'created_at' => $order->created_at?->toIso8601String(),
                    'value' => $total - $itemsTotal,
                    'type' => "طلب #{$order->id}",
                    'patient_name' => '',
                    'selected_teeth' => [],
                    'quantity' => 1,
                    'notes' => $order->notes,
                ]);
            }
        }

        foreach (DentistPayment::where('dentist_id', $dentist->id)->get() as $payment) {
            $payment->setRelation('dentist', $dentist);
            $value = $payment->valueInOwnCurrency();

            if ($value === 0) {
                continue;
            }

            $lines->push([
                'kind' => 'payment',
                'key' => 'payment-'.$payment->id,
                'date' => Carbon::parse($payment->payment_date ?? $payment->created_at)->toDateString(),
                'created_at' => $payment->created_at?->toIso8601String(),
                'value' => $value,
                'notes' => $payment->notes,
                // What the money was handed over as, when it was converted.
                'payment_currency' => $payment->currency ?? 'SYP',
                'original_amount' => $payment->original_amount,
                'rate' => $payment->rate,
            ]);
        }

        return $lines;
    }
}
