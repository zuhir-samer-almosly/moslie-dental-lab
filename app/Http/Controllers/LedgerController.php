<?php

namespace App\Http\Controllers;

use App\Concerns\ParsesDate;
use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

/**
 * The read-only accounting views. Nothing here writes to the ledger — every
 * entry originates from a domain record.
 */
class LedgerController extends Controller
{
    use ParsesDate;

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

    /** قيود اليومية — every entry, for tracing a figure back to its source. */
    public function journal(Request $request)
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));
        $account = $request->query('account');

        $entries = JournalEntry::query()
            ->with(['lines.account', 'lines.dentist'])
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->when($account, fn ($q) => $q->whereHas(
                'lines.account',
                fn ($a) => $a->where('code', $account),
            ))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // `entry_date` carries a `date` cast, so Eloquent's default array
        // serialization would render it as an ISO-8601 timestamp
        // (`2026-06-15T00:00:00.000000Z`) instead of the bare `Y-m-d` every
        // other ledger page shows. Reformat it here, on the response array,
        // rather than touching the model's cast or its set-mutator — both of
        // which other posting paths and LedgerReports depend on.
        $entries->getCollection()->transform(fn (JournalEntry $entry) => [
            ...$entry->toArray(),
            'entry_date' => $entry->entry_date->toDateString(),
        ]);

        return inertia('ledger/journal', [
            'entries' => $entries,
            'accounts' => Account::orderBy('sort_order')->get(['code', 'name']),
            'filters' => ['from' => $from, 'to' => $to, 'account' => $account],
        ]);
    }
}
