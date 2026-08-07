<?php

namespace App\Console\Commands;

use App\Ledger\AccountCode;
use App\Ledger\Ledger;
use App\Ledger\Line;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MaterialPurchase;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild every journal entry from the domain tables.
 *
 * This is a command rather than a migration because it has to stay
 * rerunnable: if a posting rule is ever corrected, the fix is a rebuild, not
 * a hand-patch of live rows.
 */
class RebuildLedger extends Command
{
    protected $signature = 'ledger:rebuild
                            {--cash-on-hand= : Real counted cash; the difference is posted to owner capital}';

    protected $description = 'Rebuild the double-entry ledger from the domain tables';

    /** Source models, posted oldest-concept-first for a readable journal. */
    private const SOURCES = [
        Order::class,
        DentistPayment::class,
        EmployeePayment::class,
        MaterialPurchase::class,
        Expense::class,
    ];

    /**
     * Source model => the date column its posting rule requires. A row with
     * a null value there is skipped by the posting rule itself (see
     * EmployeePaymentPosting / MaterialPurchasePosting / ExpensePosting),
     * deliberately: the existing reports filter with whereBetween(date, ...)
     * which never matches NULL, so those rows are already invisible to
     * today's totals and must stay that way here too. This map exists only
     * so the rebuild can *count and report* what it skipped — never to
     * change what gets posted.
     *
     * @var array<class-string, string>
     */
    private const DATE_COLUMNS = [
        EmployeePayment::class => 'payment_date',
        MaterialPurchase::class => 'purchase_date',
        Expense::class => 'expense_date',
    ];

    /** @var array<string, int> class_basename(model) => rows skipped for having no date */
    private array $skipped = [];

    public function handle(Ledger $ledger): int
    {
        $this->warn('Rebuilding the ledger — every existing entry will be replaced.');

        DB::transaction(function () use ($ledger) {
            JournalEntry::query()->delete();

            foreach (self::SOURCES as $model) {
                $this->syncModel($ledger, $model);
            }

            $this->postOpeningCapital($ledger);
        });

        $status = $this->report();

        $this->reportSkipped();

        return $status;
    }

    /** Re-sync every row of one source model, tallying rows and no-date skips. */
    private function syncModel(Ledger $ledger, string $model): void
    {
        $dateColumn = self::DATE_COLUMNS[$model] ?? null;
        $total = 0;
        $skipped = 0;

        $model::query()->orderBy('id')->chunkById(500, function ($rows) use ($ledger, $dateColumn, &$total, &$skipped) {
            foreach ($rows as $row) {
                $total++;

                if ($dateColumn !== null && $row->{$dateColumn} === null) {
                    $skipped++;
                }

                $ledger->sync($row);
            }
        });

        $this->skipped[class_basename($model)] = $skipped;

        $line = sprintf('  %-24s %d', class_basename($model), $total);

        if ($skipped > 0) {
            $line .= sprintf('  (%d skipped — no date)', $skipped);
        }

        $this->line($line);
    }

    /**
     * If the operator supplied a counted cash figure, post the gap to owner
     * capital. A negative computed balance is the expected case: it means
     * money entered the business without ever being recorded.
     */
    private function postOpeningCapital(Ledger $ledger): void
    {
        $target = $this->option('cash-on-hand');

        if ($target === null) {
            return;
        }

        $difference = (int) $target - $this->balance(AccountCode::CASH->value);

        if ($difference === 0) {
            return;
        }

        $ledger->post(
            now()->toDateString(),
            'رصيد افتتاحي — رأس المال',
            $difference > 0
                ? [
                    Line::debit(AccountCode::CASH->value, $difference),
                    Line::credit(AccountCode::CAPITAL->value, $difference),
                ]
                : [
                    Line::debit(AccountCode::CAPITAL->value, -$difference),
                    Line::credit(AccountCode::CASH->value, -$difference),
                ],
        );
    }

    /** Print the verification report and fail loudly if the books do not balance. */
    private function report(): int
    {
        $totals = JournalLine::selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();
        $difference = (int) $totals->d - (int) $totals->c;

        $this->newLine();
        $this->line('  الصندوق (cash box):    '.number_format($this->balance(AccountCode::CASH->value)));
        $this->line('  الذمم المدينة (A/R):   '.number_format($this->balance(AccountCode::RECEIVABLE->value)));
        $this->newLine();

        $this->line('  Receivables vs the old outstanding formula:');
        $this->table(
            ['Dentist', 'Ledger A/R', 'Old formula', 'Diff'],
            $this->receivableComparison(),
        );

        if ($difference !== 0) {
            $this->error("Trial balance is OFF by {$difference}. The ledger was not rebuilt correctly.");

            return self::FAILURE;
        }

        $this->info('Trial balance: BALANCED');

        return self::SUCCESS;
    }

    /**
     * Print a highly visible warning when rows were excluded for having no
     * date. That money is real but will not appear in the ledger or in any
     * existing report until the row is given a date and the rebuild is run
     * again — the operator needs to know this rather than have it vanish
     * silently.
     */
    private function reportSkipped(): void
    {
        $total = array_sum($this->skipped);

        if ($total === 0) {
            return;
        }

        $this->newLine();
        $this->warn("⚠ WARNING: {$total} row(s) were excluded because they carry no date:");

        foreach ($this->skipped as $name => $count) {
            if ($count > 0) {
                $this->line("    {$name}: {$count}");
            }
        }

        $this->warn('That money is real but invisible: with no date, these rows are excluded from');
        $this->warn('the ledger for the same reason the existing reports already miss them (they');
        $this->warn('filter by a date range, and NULL never matches). Give each row a real date');
        $this->warn('and run ledger:rebuild again to include it.');
    }

    /**
     * Per-dentist comparison of the new A/R balance against the subtraction
     * the outstanding page used before the ledger existed. Any non-zero Diff
     * is a real discrepancy in the source data and must be understood before
     * the rebuild is accepted.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function receivableComparison(): array
    {
        $ledgerBalances = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', AccountCode::RECEIVABLE->value)
            ->whereNotNull('journal_lines.dentist_id')
            ->groupBy('journal_lines.dentist_id')
            ->selectRaw('journal_lines.dentist_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
            ->pluck('balance', 'dentist_id');

        return Dentist::query()
            ->withSum(['orders as orders_total' => fn ($q) => $q->billable()], 'amount')
            ->withSum('payments as payments_total', 'amount')
            ->get()
            ->map(function (Dentist $dentist) use ($ledgerBalances) {
                $old = (int) $dentist->orders_total - (int) $dentist->payments_total;
                $new = (int) ($ledgerBalances[$dentist->id] ?? 0);

                return [
                    $dentist->name,
                    number_format($new),
                    number_format($old),
                    $new === $old ? '—' : number_format($new - $old),
                ];
            })
            ->all();
    }

    private function balance(string $code): int
    {
        return (int) JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', $code)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
            ->value('balance');
    }
}
