<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\DentistPayment;
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

            $ordersTotal = $orders->sum('amount');
            $paymentsTotal = $payments->sum('amount');

            $totals = [
                'orders' => $ordersTotal,
                'payments' => $paymentsTotal,
                'balance' => $ordersTotal - $paymentsTotal,
            ];
        }

        $dentists = \App\Models\Dentist::all();

        return inertia('invoices/index', [
            'orders' => $orders,
            'payments' => $payments,
            'totals' => $totals,
            'dentists' => $dentists,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'dentist_id' => $dentistId,
            ],
        ]);
    }
}
