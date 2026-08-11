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
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Carbon;
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
    use ConfirmableTrait;

    protected $signature = 'ledger:rebuild
                            {--cash-on-hand= : Real counted cash; the difference is posted to owner capital}
                            {--force : Force the operation to run when in production}';

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
     * Source model => the date column its posting rule checks. Used only to
     * *label* an already-established skip (see syncModel()) as "no date" vs
     * "other" — never to decide whether a row is skipped. That decision
     * always comes from asking the real posting rule's shouldPost(), so a
     * changed skip condition can never silently drift out of sync with what
     * gets reported: the totals stay true even if this label heuristic goes
     * stale.
     *
     * @var array<class-string, string>
     */
    private const DATE_COLUMNS = [
        EmployeePayment::class => 'payment_date',
        MaterialPurchase::class => 'purchase_date',
        Expense::class => 'expense_date',
    ];

    /**
     * Relations a posting rule reads, eager-loaded so a rebuild does not run
     * one query per row. OrderPosting splits an order across its items' dates.
     *
     * @var array<class-string, list<string>>
     */
    private const RELATIONS = [
        Order::class => ['items'],
    ];

    /** @var array<string, int> class_basename(model) => rows skipped for having no date */
    private array $skippedNoDate = [];

    /** @var array<string, int> class_basename(model) => rows skipped for any other reason (e.g. zero amount) */
    private array $skippedOther = [];

    /**
     * Owner capital as it stood *before* the wipe (credit-positive), or null
     * if no entry ever touched 3000. Captured because the wipe deletes it and
     * only --cash-on-hand puts it back — see reportDroppedCapital().
     */
    private ?int $capitalBefore = null;

    /** Cash box as it stood before the wipe, for the same warning. */
    private int $cashBefore = 0;

    public function handle(Ledger $ledger): int
    {
        $invalid = $this->invalidCashOnHand();

        if ($invalid !== null) {
            $this->error($invalid);

            return self::FAILURE;
        }

        if (! $this->confirmToProceed('Rebuilding the ledger replaces every existing journal entry. This is destructive.')) {
            return self::FAILURE;
        }

        $this->warn('Rebuilding the ledger — every existing entry will be replaced.');

        $this->captureCapitalBeforeWipe();

        DB::transaction(function () use ($ledger) {
            JournalEntry::query()->delete();

            foreach (self::SOURCES as $model) {
                $this->syncModel($ledger, $model);
            }

            $this->postOpeningCapital($ledger);
        });

        $status = $this->report();

        $this->reportSkipped();
        $this->reportDroppedCapital();

        return $status;
    }

    /**
     * Note the owner-capital position before the wipe removes it. Only
     * --cash-on-hand re-creates that entry, so a plain rerun silently drops
     * it; this is what lets reportDroppedCapital() say so afterwards.
     */
    private function captureCapitalBeforeWipe(): void
    {
        $hadCapital = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', AccountCode::CAPITAL->value)
            ->exists();

        // balance() is debit-minus-credit; capital is credit-natured, so flip
        // the sign to print the figure the operator recognises.
        $this->capitalBefore = $hadCapital ? -$this->balance(AccountCode::CAPITAL->value) : null;
        $this->cashBefore = $this->balance(AccountCode::CASH->value);
    }

    /**
     * Warn — loudly — when this rebuild deleted an opening capital entry and
     * did not put one back.
     *
     * The whole point of the command is that a corrected posting rule means
     * a rebuild, and the rerun is exactly when the operator forgets the flag
     * they used the first time. Without this, the opening balance vanishes
     * and the cash box quietly returns to its uncorrected (usually deeply
     * negative) figure with nothing on screen to say why.
     */
    private function reportDroppedCapital(): void
    {
        if ($this->capitalBefore === null || $this->option('cash-on-hand') !== null) {
            return;
        }

        $this->newLine();
        $this->warn('⚠ WARNING: an opening capital entry (رأس المال) existed before this rebuild');
        $this->warn('  and was NOT re-created, because --cash-on-hand was not supplied.');
        $this->warn('    Capital before:  '.number_format($this->capitalBefore));
        $this->warn('    Cash box before: '.number_format($this->cashBefore));
        $this->warn('    Cash box now:    '.number_format($this->balance(AccountCode::CASH->value)));
        $this->warn('Re-run as: php artisan ledger:rebuild --force --cash-on-hand=<real counted cash>');
        $this->warn('to restore it. Supply the flag on EVERY rebuild, not just the first.');
    }

    /**
     * Validate the raw --cash-on-hand string before it is ever cast to int.
     * `(int) '10,000'` silently truncates to 10 at the first non-digit — and
     * the report prints every figure through number_format, i.e. with
     * commas, which is exactly the format a user is likely to paste back in.
     * A bad cast here would still balance and exit 0, just wrong by orders
     * of magnitude with no signal. Reject anything that isn't a plain
     * (optionally negative) integer.
     *
     * @return string|null An error message if invalid, null if the value (or its absence) is fine.
     */
    private function invalidCashOnHand(): ?string
    {
        $raw = $this->option('cash-on-hand');

        if ($raw === null) {
            return null;
        }

        if (! preg_match('/^-?\d+$/', $raw)) {
            return "Invalid --cash-on-hand value [{$raw}]: expected a plain integer such as 10000 or -500 (no commas, no decimals).";
        }

        return null;
    }

    /** Re-sync every row of one source model, tallying posted rows and skips by reason. */
    private function syncModel(Ledger $ledger, string $model): void
    {
        $dateColumn = self::DATE_COLUMNS[$model] ?? null;
        $posted = 0;
        $skippedNoDate = 0;
        $skippedOther = 0;

        $model::query()->with(self::RELATIONS[$model] ?? [])->orderBy('id')->chunkById(500, function ($rows) use ($ledger, $dateColumn, &$posted, &$skippedNoDate, &$skippedOther) {
            foreach ($rows as $row) {
                // Ask the real posting rule, not a re-derived heuristic: a
                // row that fails shouldPost() for a reason other than a null
                // date (e.g. a zero amount) must still be accounted for
                // instead of vanishing from every count.
                $posting = $ledger->postingRuleFor($row);

                if ($posting !== null && $posting->shouldPost()) {
                    $posted++;
                } elseif ($dateColumn !== null && $row->{$dateColumn} === null) {
                    $skippedNoDate++;
                } else {
                    $skippedOther++;
                }

                $ledger->sync($row);
            }
        });

        $this->skippedNoDate[class_basename($model)] = $skippedNoDate;
        $this->skippedOther[class_basename($model)] = $skippedOther;

        $line = sprintf('  %-24s %d posted', class_basename($model), $posted);

        if ($skippedNoDate > 0) {
            $line .= sprintf(', %d skipped (no date)', $skippedNoDate);
        }

        if ($skippedOther > 0) {
            $line .= sprintf(', %d skipped (other)', $skippedOther);
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
            $this->openingDate(),
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

    /**
     * The date an opening balance belongs on: the day before the earliest
     * entry the rebuild just wrote.
     *
     * Dated `now()` instead — as this used to be — the capital injection
     * arrives *after* every historical movement it is supposed to precede,
     * so every as-of-month-end cash figure (FinanceController's رصيد الصندوق)
     * reads the accumulated negative for every past month and then jumps by
     * the whole injection in the current one. The money was in the box the
     * whole time; only the entry is new.
     *
     * The day *before* the first movement rather than the day of it, so the
     * opening balance sits outside every period a report can ask for rather
     * than inside the first one — the ordinary accounting convention, and it
     * keeps a first-day period movement report free of it. An empty ledger
     * has no first movement to precede, so today is the floor.
     */
    private function openingDate(): string
    {
        $earliest = JournalEntry::min('entry_date');

        return $earliest === null
            ? now()->toDateString()
            : Carbon::parse($earliest)->subDay()->toDateString();
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
        $totalNoDate = array_sum($this->skippedNoDate);
        $totalOther = array_sum($this->skippedOther);

        if ($totalNoDate > 0) {
            $this->newLine();
            $this->warn("⚠ WARNING: {$totalNoDate} row(s) were excluded because they carry no date:");

            foreach ($this->skippedNoDate as $name => $count) {
                if ($count > 0) {
                    $this->line("    {$name}: {$count}");
                }
            }

            $this->warn('That money is real but invisible: with no date, these rows are excluded from');
            $this->warn('the ledger for the same reason the existing reports already miss them (they');
            $this->warn('filter by a date range, and NULL never matches). Give each row a real date');
            $this->warn('and run ledger:rebuild again to include it.');
        }

        if ($totalOther > 0) {
            $this->newLine();
            $this->line("Note: {$totalOther} row(s) were also excluded for other reasons (e.g. a zero amount):");

            foreach ($this->skippedOther as $name => $count) {
                if ($count > 0) {
                    $this->line("    {$name}: {$count}");
                }
            }
        }
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
