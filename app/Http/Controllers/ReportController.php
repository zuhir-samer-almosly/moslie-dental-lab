<?php

namespace App\Http\Controllers;

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
     */
    public function index(Request $request)
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

        $income = (int) $payments->sum('amount');
        $ordersValue = (int) $orders->sum('amount');
        $salariesTotal = (int) $salaries->sum('amount');
        $materialsTotal = (int) $materials->sum('amount');
        $expensesTotal = (int) $expenses->sum('amount');
        $outgoing = $salariesTotal + $materialsTotal + $expensesTotal;

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
                'orders_value' => $ordersValue,
                'orders_count' => $orders->count(),
                'salaries' => $salariesTotal,
                'materials' => $materialsTotal,
                'general_expenses' => $expensesTotal,
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
