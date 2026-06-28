<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

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
