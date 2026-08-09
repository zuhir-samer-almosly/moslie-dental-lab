<?php

namespace App\Http\Controllers;

use App\Ledger\LedgerReports;
use App\Models\DentistPayment;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * Display everything that happened in a free date range: the work
     * (orders) plus money in (dentist payments) and money out (salaries,
     * materials, general expenses), with headline totals.
     *
     * Each stream is bucketed by its own date column, consistent with the
     * other reporting controllers: orders by `due_date`, payments by
     * `payment_date` (fallback `created_at`), salaries by `payment_date`,
     * materials by `purchase_date`, expenses by `expense_date`.
     *
     * Totals come from the ledger so they cannot disagree with the finance
     * page; the detail lists below stay on the domain tables because only
     * they carry employee names, material names and notes.
     */
    public function index(Request $request, LedgerReports $reports)
    {
        [$from, $to] = $this->resolveRange($request->query('from'), $request->query('to'));

        $orders = Order::with(['dentist', 'items'])
            ->billable()
            ->whereBetween('due_date', [$from, $to])
            ->orderBy('due_date')
            ->get();

        $payments = DentistPayment::with('dentist')
            ->whereRaw('DATE(COALESCE(payment_date, created_at)) BETWEEN ? AND ?', [$from, $to])
            ->orderByRaw('COALESCE(payment_date, created_at)')
            ->get();

        $salaries = EmployeePayment::with('employee')
            ->whereBetween('payment_date', [$from, $to])
            ->orderByDesc('payment_date')
            ->get();

        $materials = MaterialPurchase::query()
            ->whereBetween('purchase_date', [$from, $to])
            ->orderByDesc('purchase_date')
            ->get();

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->orderByDesc('expense_date')
            ->get();

        $income = $reports->cashReceipts($from, $to);
        $split = $reports->expenseSplit($from, $to);
        $outgoing = $split['salaries'] + $split['materials'] + $split['other'];

        return inertia('report/index', [
            'orders' => $orders,
            'payments' => $payments,
            'salaries' => $salaries,
            'materials' => $materials,
            'expenses' => $expenses,
            'totals' => [
                'income' => $income,
                'expenses' => $outgoing,
                'net' => $income - $outgoing,
                'earned' => $reports->revenue($from, $to),
                'orders_value' => (int) $orders->sum('amount'),
                'orders_count' => $orders->count(),
                'salaries' => $split['salaries'],
                'materials' => $split['materials'],
                'general_expenses' => $split['other'],
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Resolve the [from, to] range, defaulting to the current month when
     * either bound is missing or unparseable. Bounds are normalised to
     * Y-m-d date strings; an inverted range is swapped so the query stays
     * valid.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        $start = $this->parseDate($from) ?? Carbon::now()->startOfMonth();
        $end = $this->parseDate($to) ?? Carbon::now()->endOfMonth();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->toDateString(), $end->toDateString()];
    }

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
