<?php

namespace App\Http\Controllers;

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\MaterialPurchase;
use App\Models\Order;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the home dashboard: this month's money, all-time receivables,
     * and the most recent orders and payments.
     */
    public function index()
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfMonth()->toDateString();
        $end = $now->copy()->endOfMonth()->toDateString();

        // This month's money
        $income = (int) DentistPayment::whereRaw('DATE(COALESCE(payment_date, created_at)) BETWEEN ? AND ?', [$start, $end])->sum('amount');
        $salaries = (int) EmployeePayment::whereBetween('payment_date', [$start, $end])->sum('amount');
        $materials = (int) MaterialPurchase::whereBetween('purchase_date', [$start, $end])->sum('amount');
        $expenses = $salaries + $materials;

        // Outstanding receivables (all time), cancelled orders excluded.
        $outstanding = (int) Order::billable()->sum('amount') - (int) DentistPayment::sum('amount');

        return inertia('dashboard', [
            'stats' => [
                'month' => $now->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
                'salaries' => $salaries,
                'materials' => $materials,
                'outstanding' => $outstanding,
                'pending_orders' => Order::where('status', 'pending')->count(),
                'dentists' => Dentist::count(),
                'employees' => Employee::count(),
            ],
            'recentOrders' => Order::with(['dentist', 'items'])->latest()->take(5)->get(),
            'recentPayments' => DentistPayment::with('dentist')->latest()->take(5)->get(),
        ]);
    }
}
