<?php

namespace App\Http\Controllers;

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $dentistId = $request->input('dentist_id');

        $orders = null;
        $payments = null;
        $totals = null;
        $openingByDentist = [];

        if ($from && $to) {
            $ordersQuery = Order::with(['dentist', 'items'])
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('due_date');

            $paymentsQuery = DentistPayment::with('dentist')
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('payment_date');

            if ($dentistId) {
                $ordersQuery->where('dentist_id', $dentistId);
                $paymentsQuery->where('dentist_id', $dentistId);
            }

            $orders = $ordersQuery->get();
            $payments = $paymentsQuery->get();

            // Opening balance: everything owed from BEFORE this period.
            // (all prior orders) - (all prior payments), per dentist, so
            // unpaid leftovers from earlier months carry into this invoice.
            // Alias must not be `total` — the Order model has a `total`
            // accessor that Eloquent's pluck would apply, clobbering the SUM.
            $priorOrders = Order::where('created_at', '<', $from)
                ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId))
                ->selectRaw('dentist_id, SUM(amount) as amount_sum')
                ->groupBy('dentist_id')
                ->pluck('amount_sum', 'dentist_id');

            $priorPayments = DentistPayment::where('created_at', '<', $from)
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
}
