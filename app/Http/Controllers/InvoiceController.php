<?php

namespace App\Http\Controllers;

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function index(Request $request)
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

            // Opening balance: everything owed from BEFORE this period.
            // (all prior orders) - (all prior payments), per dentist, so
            // unpaid leftovers from earlier months carry into this invoice.
            //
            // Note the intentional date asymmetry: orders are bucketed by
            // their own date (`due_date`) while payments are bucketed by
            // `payment_date` (falling back to `created_at`). An order and the
            // payment settling it can therefore land in different periods —
            // that is by design and must stay consistent with the in-period
            // queries above. Keep both sides in sync if you ever change one.
            //
            // Alias must not be `total` — the Order model has a `total`
            // accessor that Eloquent's pluck would apply, clobbering the SUM.
            $priorOrders = Order::billable()
                ->where('due_date', '<', $from)
                ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId))
                ->selectRaw('dentist_id, SUM(amount) as amount_sum')
                ->groupBy('dentist_id')
                ->pluck('amount_sum', 'dentist_id');

            $priorPayments = DentistPayment::whereRaw('DATE(COALESCE(payment_date, created_at)) < ?', [$from])
                ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId))
                ->selectRaw('dentist_id, SUM(amount) as amount_sum')
                ->groupBy('dentist_id')
                ->pluck('amount_sum', 'dentist_id');

            $openingTotal = 0;
            foreach ($priorOrders->keys()->merge($priorPayments->keys())->unique() as $id) {
                $opening = (int) ($priorOrders[$id] ?? 0) - (int) ($priorPayments[$id] ?? 0);
                if ($opening === 0) {
                    continue; // fully settled, nothing to carry forward
                }
                $openingByDentist[$id] = $opening;
                $openingTotal += $opening;
            }

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

        return inertia('invoices/index', [
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
        ]);
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
