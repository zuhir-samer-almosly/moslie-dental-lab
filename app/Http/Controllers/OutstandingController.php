<?php

namespace App\Http\Controllers;

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;

class OutstandingController extends Controller
{
    /**
     * What each dentist owes, read as their balance on the receivables
     * account rather than by subtracting two tables. `orders_total` and
     * `payments_total` remain for display, but the balance itself is the
     * ledger's.
     */
    public function index(LedgerReports $reports)
    {
        $balances = $reports->receivablesByDentist();

        $dentists = Dentist::query()
            ->withSum(['orders as orders_total' => fn ($q) => $q->billable()], 'amount')
            ->withSum('payments as payments_total', 'amount')
            ->get()
            ->map(fn (Dentist $dentist) => [
                'id' => $dentist->id,
                'name' => $dentist->name,
                'phone' => $dentist->phone,
                'orders_total' => (int) $dentist->orders_total,
                'payments_total' => (int) $dentist->payments_total,
                'outstanding' => $balances[$dentist->id] ?? 0,
            ])
            ->sortByDesc('outstanding')
            ->values();

        return inertia('outstanding/index', [
            'dentists' => $dentists,
            'totalOutstanding' => $reports->balance(AccountCode::RECEIVABLE->value),
        ]);
    }
}
