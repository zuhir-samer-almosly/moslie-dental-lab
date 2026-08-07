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
     * Rewrite a source record's entries from its current state. The ledger
     * mirrors what the records say now: an edit replaces the entry, a cancel
     * removes it. The domain record remains the history of what happened.
     */
    public function sync(Model $source): void
    {
        DB::transaction(function () use ($source) {
            $this->forget($source);

            $posting = $this->postingFor($source);

            if (! $posting || ! $posting->shouldPost()) {
                return;
            }

            $this->post($posting->date(), $posting->description(), $posting->lines(), $source);
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
