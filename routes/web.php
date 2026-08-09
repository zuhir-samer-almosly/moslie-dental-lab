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

    Route::resource('dentists', App\Http\Controllers\DentistController::class)
        ->except('show');
    Route::resource('orders', App\Http\Controllers\OrderController::class)
        ->except('show');
    Route::resource('payments', App\Http\Controllers\DentistPaymentController::class)
        ->except('show')
        ->parameters(['payments' => 'dentistPayment']);
    Route::get('invoices', [App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/pdf', [App\Http\Controllers\InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('outstanding', [App\Http\Controllers\OutstandingController::class, 'index'])->name('outstanding.index');

    Route::resource('employees', App\Http\Controllers\EmployeeController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('employee-payments', App\Http\Controllers\EmployeePaymentController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['employee-payments' => 'employeePayment']);
    Route::resource('material-purchases', App\Http\Controllers\MaterialPurchaseController::class)
        ->except('show')
        ->parameters(['material-purchases' => 'materialPurchase']);
    Route::resource('expenses', App\Http\Controllers\ExpenseController::class)
        ->except('show');
    Route::get('finance', [App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
    Route::get('report', [App\Http\Controllers\ReportController::class, 'index'])->name('report.index');

    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('trial-balance', [App\Http\Controllers\LedgerController::class, 'trialBalance'])->name('trial-balance');
        Route::get('cash', [App\Http\Controllers\LedgerController::class, 'cash'])->name('cash');
    });
});

// Rendered by headless Chromium to build the invoice PDF. It sits outside the
// auth group because the browser has no session; the `signed` middleware is the
// gate instead, and InvoiceController::pdf mints a URL valid for two minutes.
Route::get('invoices/print-view', [App\Http\Controllers\InvoiceController::class, 'printView'])
    // `relative`: the URL is signed as a path so it stays valid when Chromium
    // requests it on the container-internal host instead of APP_URL.
    ->middleware('signed:relative')
    ->name('invoices.print-view');

require __DIR__.'/settings.php';
