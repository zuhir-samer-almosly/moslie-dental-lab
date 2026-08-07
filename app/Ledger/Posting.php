<?php

namespace App\Ledger;

/**
 * Turns one domain record into the ledger lines it implies. Implementations
 * take the model in their constructor and touch nothing else — no requests,
 * no database, no other models — so they can be tested directly.
 */
interface Posting
{
    /** Whether this record should appear in the ledger at all. */
    public function shouldPost(): bool;

    /** Business date, `Y-m-d`. */
    public function date(): string;

    public function description(): string;

    /** @return list<Line> */
    public function lines(): array;
}
