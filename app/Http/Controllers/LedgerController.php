<?php

namespace App\Http\Controllers;

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use Illuminate\Http\Request;

/**
 * The read-only accounting views. Nothing here writes to the ledger — every
 * entry originates from a domain record.
 */
class LedgerController extends Controller
{
    public function __construct(private readonly LedgerReports $reports) {}

    /** ميزان المراجعة — proof that the books balance. */
    public function trialBalance(Request $request)
    {
        $asOf = $this->parseDate($request->query('as_of'));
        $accounts = $this->reports->trialBalance($asOf);

        $debit = (int) $accounts->sum('debit');
        $credit = (int) $accounts->sum('credit');

        return inertia('ledger/trial-balance', [
            'accounts' => $accounts,
            'totals' => ['debit' => $debit, 'credit' => $credit],
            'balanced' => $debit === $credit,
            'filters' => ['as_of' => $asOf],
        ]);
    }

    /** الصندوق — cash balance and every movement through it. */
    public function cash(Request $request)
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        return inertia('ledger/cash', [
            'balance' => $this->reports->balance(AccountCode::CASH->value, $to),
            'lines' => $this->reports->accountLines(AccountCode::CASH->value, $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    /** Accept only well-formed Y-m-d; anything else collapses to no filter. */
    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
