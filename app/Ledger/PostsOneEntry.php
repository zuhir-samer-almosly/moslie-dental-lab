<?php

namespace App\Ledger;

/**
 * The common case: a record that is a single dated event — a payment, a
 * purchase, an expense — and therefore implies exactly one journal entry.
 *
 * Orders are the exception (see OrderPosting): their items carry their own
 * dates, so one order can be earned across several of them.
 */
trait PostsOneEntry
{
    /** Business date, `Y-m-d`. */
    abstract public function date(): string;

    abstract public function description(): string;

    /** @return list<Line> */
    abstract public function lines(): array;

    /** @return list<Entry> */
    public function entries(): array
    {
        return [new Entry($this->date(), $this->description(), $this->lines())];
    }
}
