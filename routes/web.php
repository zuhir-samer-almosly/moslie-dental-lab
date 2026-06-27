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
        $dentistsCount = \App\Models\Dentist::count();
        $ordersCount = \App\Models\Order::count();
        $pendingOrdersCount = \App\Models\Order::where('status', 'pending')->count();
        $totalOrdersAmount = \App\Models\Order::sum('amount');
        $totalPaymentsAmount = \App\Models\DentistPayment::sum('amount');
        $balance = $totalOrdersAmount - $totalPaymentsAmount;

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
                'dentists' => $dentistsCount,
                'orders' => $ordersCount,
                'pending_orders' => $pendingOrdersCount,
                'total_orders_amount' => $totalOrdersAmount,
                'total_payments_amount' => $totalPaymentsAmount,
                'balance' => $balance,
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

    Route::resource('employees', App\Http\Controllers\EmployeeController::class)
        ->except('show');
    Route::resource('employee-payments', App\Http\Controllers\EmployeePaymentController::class)
        ->except('show')
        ->parameters(['employee-payments' => 'employeePayment']);
    Route::get('finance', [App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
});

require __DIR__.'/settings.php';
