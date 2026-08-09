<?php

namespace App\Ledger;

use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every read of the ledger. Controllers call this instead of summing the
 * domain tables, so no two reports can compute the same figure differently.
 *
 * Date bounds are inclusive `Y-m-d` strings and filter on
 * `journal_entries.entry_date` — the business date, not created_at.
 */
class LedgerReports
{
    /** Account types whose natural balance is on the debit side. */
    private const DEBIT_NATURED = ['asset', 'expense'];

    public function balance(string $code, ?string $asOf = null): int
    {
        $row = $this->linesForAccount($code)
            ->when($asOf, fn ($q) => $q->where('journal_entries.entry_date', '<=', $asOf))
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->first();

        $net = (int) $row->d - (int) $row->c;

        return in_array(Account::typeFor($code), self::DEBIT_NATURED, true) ? $net : -$net;
    }

    /** @return Collection<int, int> dentist_id => balance */
    public function receivablesByDentist(?string $asOf = null): Collection
    {
        return $this->linesForAccount(AccountCode::RECEIVABLE->value)
            ->whereNotNull('journal_lines.dentist_id')
            ->when($asOf, fn ($q) => $q->where('journal_entries.entry_date', '<=', $asOf))
            ->groupBy('journal_lines.dentist_id')
            ->selectRaw('journal_lines.dentist_id, COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
            ->pluck('balance', 'dentist_id')
            ->map(fn ($value) => (int) $value);
    }

    /**
     * Total of entries in the range that debit one account and credit
     * another. Every entry this app writes has exactly two lines, so this
     * isolates one specific kind of movement — cash in from dentists, say,
     * as opposed to cash in from an owner capital injection.
     */
    public function movementBetween(string $debitCode, string $creditCode, string $from, string $to): int
    {
        return (int) DB::table('journal_lines as dr')
            ->join('journal_entries as e', 'e.id', '=', 'dr.journal_entry_id')
            ->join('journal_lines as cr', 'cr.journal_entry_id', '=', 'e.id')
            ->join('accounts as dra', 'dra.id', '=', 'dr.account_id')
            ->join('accounts as cra', 'cra.id', '=', 'cr.account_id')
            ->where('dra.code', $debitCode)
            ->where('cra.code', $creditCode)
            ->where('dr.debit', '>', 0)
            ->where('cr.credit', '>', 0)
            ->whereBetween('e.entry_date', [$from, $to])
            ->sum('dr.debit');
    }

    /** Work earned in the range — orders, by due date. */
    public function revenue(string $from, string $to): int
    {
        return $this->movementBetween(
            AccountCode::RECEIVABLE->value,
            AccountCode::REVENUE->value,
            $from,
            $to,
        );
    }

    /** Cash actually collected from dentists in the range. */
    public function cashReceipts(string $from, string $to): int
    {
        return $this->movementBetween(
            AccountCode::CASH->value,
            AccountCode::RECEIVABLE->value,
            $from,
            $to,
        );
    }

    /**
     * One row per expense account with movement in the range. Replaces the
     * hardcoded category array the finance page used to carry — a new expense
     * account appears here automatically.
     *
     * @return Collection<int, array{code: string, name: string, total: int}>
     */
    public function expenseBreakdown(string $from, string $to): Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', 'expense')
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->groupBy('accounts.code', 'accounts.name', 'accounts.sort_order')
            ->orderBy('accounts.sort_order')
            ->selectRaw('accounts.code, accounts.name, COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as total')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'total' => (int) $row->total,
            ])
            ->filter(fn (array $row) => $row['total'] !== 0)
            ->values();
    }

    public function expensesTotal(string $from, string $to): int
    {
        return (int) $this->expenseBreakdown($from, $to)->sum('total');
    }

    /**
     * Splits the expense breakdown into the three buckets the dashboard and
     * report pages show: salaries, materials, and everything else — general
     * expenses, including the fallback account. Kept in one place so the
     * "neither salaries nor materials" classification can't drift between
     * the pages that render it.
     *
     * @return array{salaries: int, materials: int, other: int}
     */
    public function expenseSplit(string $from, string $to): array
    {
        $breakdown = $this->expenseBreakdown($from, $to);

        return [
            'salaries' => (int) ($breakdown->firstWhere('code', AccountCode::SALARIES->value)['total'] ?? 0),
            'materials' => (int) ($breakdown->firstWhere('code', AccountCode::MATERIALS->value)['total'] ?? 0),
            'other' => (int) $breakdown
                ->reject(fn (array $row) => in_array($row['code'], [
                    AccountCode::SALARIES->value,
                    AccountCode::MATERIALS->value,
                ], true))
                ->sum('total'),
        ];
    }

    /**
     * @return Collection<int, array{code: string, name: string, type: string, debit: int, credit: int}>
     */
    public function trialBalance(?string $asOf = null): Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->when($asOf, fn ($q) => $q->where('journal_entries.entry_date', '<=', $asOf))
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type', 'accounts.sort_order')
            ->orderBy('accounts.sort_order')
            ->selectRaw('accounts.code, accounts.name, accounts.type, COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debit' => (int) $row->debit,
                'credit' => (int) $row->credit,
            ])
            ->filter(fn (array $row) => $row['debit'] !== 0 || $row['credit'] !== 0)
            ->values();
    }

    /**
     * Movements on one account, newest first, each carrying its entry's date
     * and description.
     *
     * Paginated, like the journal page's entry list: an account's line list
     * grows monotonically with the business — every payment, salary,
     * purchase and expense is one cash line, forever — so an unbounded
     * `get()` here is a query that gets slower every day and can never get
     * faster. Balances are NOT derived from this list; `balance()` is a
     * separate aggregate over the whole account, so paginating the list
     * cannot move a figure.
     */
    public function accountLines(string $code, ?string $from = null, ?string $to = null, int $perPage = 50): LengthAwarePaginator
    {
        return $this->linesForAccount($code)
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('journal_entries.entry_date', '<=', $to))
            ->orderByDesc('journal_entries.entry_date')
            ->orderByDesc('journal_lines.id')
            ->select(
                'journal_lines.id',
                'journal_entries.entry_date',
                'journal_entries.description',
                'journal_lines.debit',
                'journal_lines.credit',
            )
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'date' => $row->entry_date,
                'description' => $row->description,
                'debit' => (int) $row->debit,
                'credit' => (int) $row->credit,
            ]);
    }

    /**
     * A dentist's receivable movements in a period, with the balance carried
     * in from before it and a running balance on each line.
     *
     * @return array{opening: int, lines: Collection, closing: int}
     */
    public function dentistStatement(int $dentistId, ?string $from = null, ?string $to = null): array
    {
        $opening = 0;

        if ($from) {
            $opening = (int) $this->linesForAccount(AccountCode::RECEIVABLE->value)
                ->where('journal_lines.dentist_id', $dentistId)
                ->where('journal_entries.entry_date', '<', $from)
                ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
                ->value('balance');
        }

        $running = $opening;

        $lines = $this->linesForAccount(AccountCode::RECEIVABLE->value)
            ->where('journal_lines.dentist_id', $dentistId)
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('journal_entries.entry_date', '<=', $to))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select(
                'journal_lines.id',
                'journal_entries.entry_date',
                'journal_entries.description',
                'journal_lines.debit',
                'journal_lines.credit',
            )
            ->get()
            ->map(function ($row) use (&$running) {
                $running += (int) $row->debit - (int) $row->credit;

                return [
                    'id' => (int) $row->id,
                    'date' => $row->entry_date,
                    'description' => $row->description,
                    'debit' => (int) $row->debit,
                    'credit' => (int) $row->credit,
                    'balance' => $running,
                ];
            });

        return ['opening' => $opening, 'lines' => $lines, 'closing' => $running];
    }

    private function linesForAccount(string $code): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', $code);
    }
}
