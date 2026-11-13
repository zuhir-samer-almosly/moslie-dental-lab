<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    // The tablet's `entry` login has no dashboard; its home is the daybook.
    return redirect()->route(Auth::user()->isAdmin() ? 'dashboard' : 'daybook.index');
})->name('home');

// Open to every login, the lab tablet's `entry` account included: the daybook
// and the dentist notebooks, and nothing else.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('daybook', [App\Http\Controllers\DaybookController::class, 'index'])->name('daybook.index');
    Route::get('daybook/pdf', [App\Http\Controllers\DaybookController::class, 'pdf'])->name('daybook.pdf');

    // A daybook line is saved by the ordinary order and payment actions — the
    // same validation, currency handling and ledger posting — which send the
    // user back here instead of to their own list pages (RedirectsToDaybook).
    // `daybook.once` makes a save safe to retry on the lab's slow connection.
    Route::post('daybook/orders', [App\Http\Controllers\OrderController::class, 'store'])
        ->middleware('daybook.once')->name('daybook.orders.store');
    Route::put('daybook/orders/{order}', [App\Http\Controllers\OrderController::class, 'update'])
        ->name('daybook.orders.update');
    Route::delete('daybook/orders/{order}', [App\Http\Controllers\OrderController::class, 'destroy'])
        ->name('daybook.orders.destroy');
    Route::post('daybook/payments', [App\Http\Controllers\DentistPaymentController::class, 'store'])
        ->middleware('daybook.once')->name('daybook.payments.store');
    Route::put('daybook/payments/{dentistPayment}', [App\Http\Controllers\DentistPaymentController::class, 'update'])
        ->name('daybook.payments.update');
    Route::delete('daybook/payments/{dentistPayment}', [App\Http\Controllers\DentistPaymentController::class, 'destroy'])
        ->name('daybook.payments.destroy');

    Route::get('notebook', [App\Http\Controllers\NotebookController::class, 'index'])->name('notebook.index');
    Route::get('notebook/{dentist}', [App\Http\Controllers\NotebookController::class, 'show'])->name('notebook.show');
    Route::get('notebook/{dentist}/pdf', [App\Http\Controllers\NotebookController::class, 'pdf'])->name('notebook.pdf');
});

// Everything else is the full app, for `admin` logins only.
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    // The header search box; answers with JSON from any page.
    Route::get('search', App\Http\Controllers\SearchController::class)->name('search');

    Route::resource('dentists', App\Http\Controllers\DentistController::class)
        ->except('show');
    Route::resource('orders', App\Http\Controllers\OrderController::class)
        ->except('show');
    Route::get('work-types', [App\Http\Controllers\WorkTypeController::class, 'index'])->name('work-types.index');
    Route::post('work-types/merge', [App\Http\Controllers\WorkTypeController::class, 'merge'])->name('work-types.merge');
    Route::post('dentists/{dentist}/price-list', [App\Http\Controllers\WorkTypeController::class, 'storePrice'])
        ->name('dentists.price-list.store');
    Route::post('payments/batch', [App\Http\Controllers\DentistPaymentController::class, 'storeMany'])
        ->name('payments.store-many');
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
    // Today's lira-per-dollar rate, set by hand from the sidebar.
    Route::post('exchange-rate', [App\Http\Controllers\ExchangeRateController::class, 'store'])
        ->name('exchange-rate.store');

    Route::get('finance', [App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
    Route::get('report', [App\Http\Controllers\ReportController::class, 'index'])->name('report.index');

    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('trial-balance', [App\Http\Controllers\LedgerController::class, 'trialBalance'])->name('trial-balance');
        Route::get('cash', [App\Http\Controllers\LedgerController::class, 'cash'])->name('cash');
        Route::get('journal', [App\Http\Controllers\LedgerController::class, 'journal'])->name('journal');
        Route::get('statement', [App\Http\Controllers\LedgerController::class, 'statement'])->name('statement');
        Route::get('statement/pdf', [App\Http\Controllers\LedgerController::class, 'statementPdf'])->name('statement.pdf');
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

// Same pattern as invoices/print-view above, for the dentist statement's PDF.
Route::get('ledger/statement/print-view', [App\Http\Controllers\LedgerController::class, 'statementPrintView'])
    ->middleware('signed:relative')
    ->name('ledger.statement.print-view');

// Same pattern again, for the daybook's daily sheet and a dentist's notebook.
Route::get('daybook/print-view', [App\Http\Controllers\DaybookController::class, 'printView'])
    ->middleware('signed:relative')
    ->name('daybook.print-view');
Route::get('notebook/{dentist}/print-view', [App\Http\Controllers\NotebookController::class, 'printView'])
    ->middleware('signed:relative')
    ->name('notebook.print-view');

require __DIR__.'/settings.php';
