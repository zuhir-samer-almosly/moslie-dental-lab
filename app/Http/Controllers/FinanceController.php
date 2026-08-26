<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesMonth;
use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Account;
use App\Money\Rate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    use ResolvesMonth;

    public function __construct(private readonly LedgerReports $reports) {}

    /**
     * The monthly financial summary. The headline is cash: money actually
     * collected from dentists less money actually paid out. Work earned and
     * receivables sit beside it, because a strong month of work is not the
     * same as money in hand.
     *
     * Expense rows are whatever expense accounts moved this month — there is
     * no hardcoded bucket list. Adding an account adds a row.
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));
        [$start, $end] = $this->range($month);

        $income = $this->reports->cashReceipts($start, $end);
        $categories = $this->reports->expenseBreakdown($start, $end)
            ->map(fn (array $row) => [
                'key' => $row['code'],
                'label' => $row['name'],
                'total' => $row['total'],
            ]);
        $expenses = (int) $categories->sum('total');

        return inertia('finance/index', [
            'month' => $month->format('Y-m'),
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses,
            'earned' => $this->reports->revenue($start, $end),
            'receivables' => $this->reports->balance(AccountCode::RECEIVABLE->value, $end),
            'cashBalance' => $this->reports->balance(AccountCode::CASH->value, $end),
            'categories' => $categories->values(),
            'incomeByDentist' => $this->incomeByDentist($start, $end),
            'expensesByEmployee' => $this->expensesByEmployee($start, $end),
            'expensesByMaterial' => $this->expensesByMaterial($start, $end),
            'expensesByCategory' => $this->expensesByCategory($categories),
            'trend' => $this->trend($month),
            'closingRate' => Rate::on($end),
        ]);
    }

    /**
     * General expenses only — accounts carrying a `category_key` (إيجار,
     * نقل, ...) — excluding the structural accounts (salaries, materials,
     * the catch-all), sorted biggest first. This is the pre-ledger meaning
     * of this prop; it is not a second copy of `categories`, which is every
     * expense account. The page renders both, so collapsing them into the
     * same list would show the same rows twice.
     *
     * Membership is decided from the unfiltered category set so a category
     * deactivated after the fact still labels its historical spend here,
     * matching the ruling already applied to expense posting.
     */
    private function expensesByCategory(\Illuminate\Support\Collection $categories): \Illuminate\Support\Collection
    {
        $categoryCodes = Account::allExpenseCategories()->pluck('code')->all();

        return $categories
            ->filter(fn (array $row) => in_array($row['key'], $categoryCodes, true))
            ->sortByDesc('total')
            ->map(fn (array $row) => ['name' => $row['label'], 'total' => $row['total']])
            ->values();
    }

    /**
     * Money collected per dentist. Read from receivable lines rather than the
     * payments table so it cannot disagree with the headline figure.
     *
     * Narrowed to the paired cash debit on the same entry (mirroring
     * `LedgerReports::movementBetween()`), not just "any receivable credit".
     * A bare `credit > 0` on the receivable account would also catch a
     * future write-off posting (credits receivable, debits `5900` bad debt,
     * not cash) and misreport it as money collected from the dentist.
     */
    private function incomeByDentist(string $start, string $end): \Illuminate\Support\Collection
    {
        return DB::table('journal_lines as cr')
            ->join('journal_entries as e', 'e.id', '=', 'cr.journal_entry_id')
            ->join('accounts as cra', 'cra.id', '=', 'cr.account_id')
            ->join('journal_lines as dr', 'dr.journal_entry_id', '=', 'e.id')
            ->join('accounts as dra', 'dra.id', '=', 'dr.account_id')
            ->join('dentists', 'dentists.id', '=', 'cr.dentist_id')
            ->where('cra.code', AccountCode::RECEIVABLE->value)
            ->where('dra.code', AccountCode::CASH->value)
            ->where('cr.credit', '>', 0)
            ->where('dr.debit', '>', 0)
            ->whereBetween('e.entry_date', [$start, $end])
            ->groupBy('dentists.id', 'dentists.name')
            ->orderByDesc('total')
            ->selectRaw('dentists.name, SUM(cr.credit) as total')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    /**
     * Salary detail still comes from the domain table: the ledger records the
     * money but not which employee it went to.
     */
    private function expensesByEmployee(string $start, string $end): \Illuminate\Support\Collection
    {
        return DB::table('employee_payments')
            ->join('employees', 'employees.id', '=', 'employee_payments.employee_id')
            ->whereBetween('employee_payments.payment_date', [$start, $end])
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('total')
            ->select('employees.name', DB::raw('SUM(employee_payments.amount) as total'))
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    /** Material detail, likewise, for the material name. */
    private function expensesByMaterial(string $start, string $end): \Illuminate\Support\Collection
    {
        return DB::table('material_purchases')
            ->whereBetween('purchase_date', [$start, $end])
            ->groupBy('name')
            ->orderByDesc('total')
            ->select('name', DB::raw('SUM(amount) as total'))
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total]);
    }

    /**
     * Last 6 months of cash in / cash out / net, oldest first.
     *
     * Six pairs of small aggregate queries rather than one grouped query,
     * because grouping by month needs a driver-specific date function and
     * this must run on both SQLite and MySQL.
     */
    private function trend(Carbon $month): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $bucket = $month->copy()->subMonths($i);
            [$start, $end] = $this->range($bucket);

            $income = $this->reports->cashReceipts($start, $end);
            $expenses = $this->reports->expensesTotal($start, $end);

            $trend[] = [
                'month' => $bucket->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
            ];
        }

        return $trend;
    }

    /** @return array{0: string, 1: string} */
    private function range(Carbon $month): array
    {
        return [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ];
    }
}
