<?php

namespace App\Http\Controllers;

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\Order;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the home dashboard: this month's money, all-time receivables,
     * and the most recent orders and payments.
     */
    public function index(LedgerReports $reports)
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfMonth()->toDateString();
        $end = $now->copy()->endOfMonth()->toDateString();

        $income = $reports->cashReceipts($start, $end);
        $split = $reports->expenseSplit($start, $end);
        $expenses = $split['salaries'] + $split['materials'] + $split['other'];

        return inertia('dashboard', [
            'stats' => [
                'month' => $now->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
                'salaries' => $split['salaries'],
                'materials' => $split['materials'],
                'general_expenses' => $split['other'],
                'earned' => $reports->revenue($start, $end),
                'outstanding' => $reports->balance(AccountCode::RECEIVABLE->value),
                'cash_balance' => $reports->balance(AccountCode::CASH->value),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'dentists' => Dentist::count(),
                'employees' => Employee::count(),
            ],
            'recentOrders' => Order::with(['dentist', 'items'])->latest()->take(5)->get(),
            'recentPayments' => DentistPayment::with('dentist')->latest()->take(5)->get(),
        ]);
    }
}
