<?php

namespace App\Http\Controllers;

use App\Models\DentistPayment;
use App\Models\EmployeePayment;
use App\Models\MaterialPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * Display the monthly financial summary (income vs. expenses vs. net).
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));

        [$income, $incomeByDentist] = $this->incomeForMonth($month);
        $salaries = $this->salariesForMonth($month);
        $materials = $this->materialsForMonth($month);
        $expensesByEmployee = $this->expensesByEmployee($month);
        $expensesByMaterial = $this->expensesByMaterial($month);

        // Expense categories — extend here to add new expense buckets.
        $categories = [
            ['key' => 'salaries', 'label' => 'الرواتب', 'total' => $salaries],
            ['key' => 'materials', 'label' => 'المواد', 'total' => $materials],
        ];
        $expenses = array_sum(array_column($categories, 'total'));

        return inertia('finance/index', [
            'month' => $month->format('Y-m'),
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses,
            'categories' => $categories,
            'incomeByDentist' => $incomeByDentist,
            'expensesByEmployee' => $expensesByEmployee,
            'expensesByMaterial' => $expensesByMaterial,
            'trend' => $this->trend($month),
        ]);
    }

    /**
     * @return array{0: int, 1: \Illuminate\Support\Collection}
     */
    private function incomeForMonth(Carbon $month): array
    {
        [$start, $end] = $this->range($month);

        $income = (int) DentistPayment::query()
            ->whereRaw('DATE(COALESCE(payment_date, created_at)) BETWEEN ? AND ?', [$start, $end])
            ->sum('amount');

        $byDentist = DB::table('dentist_payments')
            ->join('dentists', 'dentists.id', '=', 'dentist_payments.dentist_id')
            ->whereRaw('DATE(COALESCE(dentist_payments.payment_date, dentist_payments.created_at)) BETWEEN ? AND ?', [$start, $end])
            ->groupBy('dentists.id', 'dentists.name')
            ->orderByDesc('total')
            ->select('dentists.name', DB::raw('SUM(dentist_payments.amount) as total'))
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);

        return [$income, $byDentist];
    }

    private function salariesForMonth(Carbon $month): int
    {
        [$start, $end] = $this->range($month);

        return (int) EmployeePayment::query()
            ->whereBetween('payment_date', [$start, $end])
            ->sum('amount');
    }

    private function expensesByEmployee(Carbon $month): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->range($month);

        return DB::table('employee_payments')
            ->join('employees', 'employees.id', '=', 'employee_payments.employee_id')
            ->whereBetween('employee_payments.payment_date', [$start, $end])
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('total')
            ->select('employees.name', DB::raw('SUM(employee_payments.amount) as total'))
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    private function materialsForMonth(Carbon $month): int
    {
        [$start, $end] = $this->range($month);

        return (int) MaterialPurchase::query()
            ->whereBetween('purchase_date', [$start, $end])
            ->sum('amount');
    }

    private function expensesByMaterial(Carbon $month): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->range($month);

        return DB::table('material_purchases')
            ->whereBetween('purchase_date', [$start, $end])
            ->groupBy('name')
            ->orderByDesc('total')
            ->select('name', DB::raw('SUM(amount) as total'))
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    /**
     * Last 6 months of income/expenses/net, oldest first.
     *
     * One query per source over the whole 6-month window, then bucketed by
     * month in PHP. This avoids per-month round-trips and stays portable
     * across sqlite (tests) and MySQL (prod) — unlike a GROUP BY on a
     * driver-specific month function.
     */
    private function trend(Carbon $month): array
    {
        $start = $month->copy()->subMonths(5)->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $incomeByMonth = DentistPayment::query()
            ->whereRaw('DATE(COALESCE(payment_date, created_at)) BETWEEN ? AND ?', [$start, $end])
            ->get(['amount', 'payment_date', 'created_at'])
            ->groupBy(fn ($p) => Carbon::parse($p->payment_date ?? $p->created_at)->format('Y-m'))
            ->map(fn ($rows) => (int) $rows->sum('amount'));

        $salariesByMonth = EmployeePayment::query()
            ->whereBetween('payment_date', [$start, $end])
            ->get(['amount', 'payment_date'])
            ->groupBy(fn ($p) => Carbon::parse($p->payment_date)->format('Y-m'))
            ->map(fn ($rows) => (int) $rows->sum('amount'));

        $materialsByMonth = MaterialPurchase::query()
            ->whereBetween('purchase_date', [$start, $end])
            ->get(['amount', 'purchase_date'])
            ->groupBy(fn ($p) => Carbon::parse($p->purchase_date)->format('Y-m'))
            ->map(fn ($rows) => (int) $rows->sum('amount'));

        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $key = $month->copy()->subMonths($i)->format('Y-m');
            $income = $incomeByMonth[$key] ?? 0;
            $expenses = ($salariesByMonth[$key] ?? 0) + ($materialsByMonth[$key] ?? 0);

            $trend[] = [
                'month' => $key,
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
            ];
        }

        return $trend;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Carbon $month): array
    {
        return [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function resolveMonth(?string $month): Carbon
    {
        if ($month) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // fall through to current month
            }
        }

        return Carbon::now()->startOfMonth();
    }
}
