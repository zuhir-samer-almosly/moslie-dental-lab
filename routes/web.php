<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $now = \Illuminate\Support\Carbon::now();
        $start = $now->copy()->startOfMonth()->toDateString();
        $end = $now->copy()->endOfMonth()->toDateString();

        // This month's money
        $income = (int) \App\Models\DentistPayment::whereRaw('DATE(COALESCE(payment_date, created_at)) BETWEEN ? AND ?', [$start, $end])->sum('amount');
        $salaries = (int) \App\Models\EmployeePayment::whereBetween('payment_date', [$start, $end])->sum('amount');
        $materials = (int) \App\Models\MaterialPurchase::whereBetween('purchase_date', [$start, $end])->sum('amount');
        $expenses = $salaries + $materials;

        // Outstanding receivables (all time)
        $outstanding = (int) \App\Models\Order::sum('amount') - (int) \App\Models\DentistPayment::sum('amount');

        $recentOrders = \App\Models\Order::with(['dentist', 'items'])
            ->latest()
            ->take(5)
            ->get();

        $recentPayments = \App\Models\DentistPayment::with('dentist')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('dashboard', [
            'stats' => [
                'month' => $now->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
                'salaries' => $salaries,
                'materials' => $materials,
                'outstanding' => $outstanding,
                'pending_orders' => \App\Models\Order::where('status', 'pending')->count(),
                'dentists' => \App\Models\Dentist::count(),
                'employees' => \App\Models\Employee::count(),
            ],
            'recentOrders' => $recentOrders,
            'recentPayments' => $recentPayments,
        ]);
    })->name('dashboard');

    Route::resource('dentists', App\Http\Controllers\DentistController::class);
    Route::resource('orders', App\Http\Controllers\OrderController::class);
    Route::resource('payments', App\Http\Controllers\DentistPaymentController::class)
        ->parameters(['payments' => 'dentistPayment']);
    Route::get('invoices', [App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('outstanding', [App\Http\Controllers\OutstandingController::class, 'index'])->name('outstanding.index');

    Route::resource('employees', App\Http\Controllers\EmployeeController::class)
        ->except('show');
    Route::resource('employee-payments', App\Http\Controllers\EmployeePaymentController::class)
        ->except('show')
        ->parameters(['employee-payments' => 'employeePayment']);
    Route::resource('material-purchases', App\Http\Controllers\MaterialPurchaseController::class)
        ->except('show')
        ->parameters(['material-purchases' => 'materialPurchase']);
    Route::get('finance', [App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
});

require __DIR__.'/settings.php';
