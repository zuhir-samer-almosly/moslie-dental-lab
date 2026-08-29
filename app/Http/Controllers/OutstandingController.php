<?php

namespace App\Http\Controllers;

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Money\Currency;

class OutstandingController extends Controller
{
    /**
     * What each dentist owes, read as their balance on the receivables
     * account rather than by subtracting two tables. `orders_total` and
     * `payments_total` remain for display, but the balance itself is the
     * ledger's.
     *
     * A dollar dentist's balance lives in the dollar receivable account and
     * is never added to the lira total — the two currencies get their own
     * columns and their own grand total, never one blended figure.
     */
    public function index(LedgerReports $reports)
    {
        $balances = [
            Currency::SYP->value => $reports->receivablesByDentist(null, Currency::SYP),
            Currency::USD->value => $reports->receivablesByDentist(null, Currency::USD),
        ];

        $dentists = Dentist::query()
            ->withSum(['orders as orders_total' => fn ($q) => $q->billable()], 'amount')
            // A dollar dentist's orders and payments hold zero lira and their
            // real value in `original_amount`, so both display columns need
            // the second sum to have anything to show.
            ->withSum(['orders as orders_total_usd' => fn ($q) => $q->billable()], 'original_amount')
            ->withSum('payments as payments_total', 'amount')
            ->withSum('payments as payments_total_usd', 'original_amount')
            ->get()
            ->map(function (Dentist $dentist) use ($balances) {
                $currency = $dentist->billingCurrency();
                $dollar = $currency === Currency::USD;

                return [
                    'id' => $dentist->id,
                    'name' => $dentist->name,
                    'phone' => $dentist->phone,
                    'currency' => $currency->value,
                    'orders_total' => (int) ($dollar ? $dentist->orders_total_usd : $dentist->orders_total),
                    'payments_total' => (int) ($dollar ? $dentist->payments_total_usd : $dentist->payments_total),
                    'outstanding' => $balances[$currency->value][$dentist->id] ?? 0,
                ];
            })
            // Sorted within currency, then by size. Sorting the two together
            // would rank cents against lira, which means nothing.
            ->sortBy([['currency', 'asc'], ['outstanding', 'desc']])
            ->values();

        return inertia('outstanding/index', [
            'dentists' => $dentists,
            'totalOutstanding' => $reports->balance(AccountCode::RECEIVABLE->value),
            'totalOutstandingUsd' => $reports->balance(AccountCode::RECEIVABLE_USD->value),
        ]);
    }
}
