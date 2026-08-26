<?php

namespace App\Ledger;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ledger
{
    /**
     * Source model → posting rule. Extended as each rule lands.
     *
     * @var array<class-string, class-string<Posting>>
     */
    public const POSTINGS = [
        \App\Models\Order::class => \App\Ledger\Postings\OrderPosting::class,
        \App\Models\DentistPayment::class => \App\Ledger\Postings\DentistPaymentPosting::class,
        \App\Models\EmployeePayment::class => \App\Ledger\Postings\EmployeePaymentPosting::class,
        \App\Models\MaterialPurchase::class => \App\Ledger\Postings\MaterialPurchasePosting::class,
        \App\Models\Expense::class => \App\Ledger\Postings\ExpensePosting::class,
    ];

    /**
     * Records whose sync has been deferred, keyed by identity so a record
     * queued many times syncs once. Null when not deferring.
     *
     * @var array<string, array{class-string<Model>, mixed}>|null
     */
    private ?array $deferred = null;

    /**
     * Collapse every sync inside $work into one sync per distinct record.
     *
     * Writing an order's items fires the item observer once per item, and each
     * of those re-posts the whole order — so ten items wrote the entry set ten
     * times over, quadratic in the item count. Inside this scope those syncs
     * are collected instead and run once each after $work returns, still
     * inside the caller's transaction so the ledger and the domain tables
     * commit or roll back together.
     *
     * Deletes are not deferred: `forget()` is cheap, exact, and must take
     * effect immediately for a later sync in the same batch to see the truth.
     */
    public function defer(callable $work): mixed
    {
        if ($this->deferred !== null) {
            // Already inside a deferral — join it rather than flushing early,
            // or a nested scope would post a half-written batch.
            return $work();
        }

        $this->deferred = [];

        try {
            $result = $work();
        } catch (\Throwable $e) {
            // The transaction is rolling back; the queued syncs are moot.
            $this->deferred = null;

            throw $e;
        }

        $pending = $this->deferred;
        $this->deferred = null;

        foreach ($pending as [$class, $id]) {
            // Re-read rather than reuse the queued instance: that was a
            // snapshot from partway through the batch, and the posting has to
            // reflect what the batch finished with. A record deleted before
            // the flush has already been forgotten, so skipping it is right.
            if ($model = $class::query()->find($id)) {
                $this->sync($model);
            }
        }

        return $result;
    }

    /**
     * Rewrite a source record's entries from its current state. The ledger
     * mirrors what the records say now: an edit replaces the entries, a
     * cancel removes them. The domain record remains the history of what
     * happened.
     */
    public function sync(Model $source): void
    {
        if ($this->deferred !== null) {
            $this->deferred[$source->getMorphClass().':'.$source->getKey()] = [$source::class, $source->getKey()];

            return;
        }

        DB::transaction(function () use ($source) {
            $this->forget($source);

            $posting = $this->postingFor($source);

            if (! $posting || ! $posting->shouldPost()) {
                return;
            }

            foreach ($posting->entries() as $entry) {
                $this->post($entry->date, $entry->description, $entry->lines, $source);
            }
        });
    }

    /** Remove every entry a source record produced. Lines cascade. */
    public function forget(Model $source): void
    {
        JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->delete();
    }

    /**
     * Remove entries for many sources of one type at once. Used when a parent
     * row is deleted and the database cascades its children away without
     * Eloquent seeing it.
     *
     * @param  list<int>  $ids
     */
    public function forgetMany(string $sourceType, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        JournalEntry::query()
            ->where('source_type', $sourceType)
            ->whereIn('source_id', $ids)
            ->delete();
    }

    /**
     * @param  list<Line>  $lines
     *
     * @throws UnbalancedEntryException
     */
    public function post(string $date, string $description, array $lines, ?Model $source = null): JournalEntry
    {
        $this->assertBalanced($lines);

        return DB::transaction(function () use ($date, $description, $lines, $source) {
            $entry = JournalEntry::create([
                'entry_date' => $date,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => Account::idFor($line->accountCode),
                    'dentist_id' => $line->dentistId,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Public accessor for the posting rule resolved for a source model, so
     * callers outside the ledger (e.g. `ledger:rebuild`'s reporting) can ask
     * "would this row post, and why not if not" without re-deriving or
     * duplicating the model→posting mapping or any rule's skip condition.
     */
    public function postingRuleFor(Model $source): ?Posting
    {
        return $this->postingFor($source);
    }

    protected function postingFor(Model $source): ?Posting
    {
        $class = self::POSTINGS[$source::class] ?? null;

        return $class ? new $class($source) : null;
    }

    /** @param  list<Line>  $lines */
    private function assertBalanced(array $lines): void
    {
        if ($lines === []) {
            throw new UnbalancedEntryException('An entry must have at least one line.');
        }

        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            if ($line->debit !== 0 && $line->credit !== 0) {
                throw new UnbalancedEntryException('A line carries a debit or a credit, never both.');
            }

            if ($line->debit === 0 && $line->credit === 0) {
                throw new UnbalancedEntryException('A line must carry a debit or a credit.');
            }

            if ($line->debit < 0 || $line->credit < 0) {
                throw new UnbalancedEntryException('Line amounts must not be negative.');
            }

            $debits += $line->debit;
            $credits += $line->credit;
        }

        if ($debits === 0 || $credits === 0) {
            throw new UnbalancedEntryException('An entry must have at least one debit and one credit.');
        }

        if ($debits !== $credits) {
            throw new UnbalancedEntryException("Entry is unbalanced: debits {$debits}, credits {$credits}.");
        }
    }
}
