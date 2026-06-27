<?php

namespace App\Http\Controllers;

use App\Models\DentistPayment;
use App\Models\EmployeePayment;
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
        $expensesByEmployee = $this->expensesByEmployee($month);

        // Expense categories — materials slots in here later as another entry.
        $categories = [
            ['key' => 'salaries', 'label' => 'الرواتب', 'total' => $salaries],
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

    /**
     * Last 6 months of income/expenses/net, oldest first.
     */
    private function trend(Carbon $month): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $m = $month->copy()->subMonths($i);
            $income = $this->incomeForMonth($m)[0];
            $expenses = $this->salariesForMonth($m);

            $trend[] = [
                'month' => $m->format('Y-m'),
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
