<?php

namespace App\Ledger;

/**
 * Turns one domain record into the ledger entries it implies. Implementations
 * take the model in their constructor and touch nothing else — no requests,
 * no database, no other models — so they can be tested directly.
 */
interface Posting
{
    /** Whether this record should appear in the ledger at all. */
    public function shouldPost(): bool;

    /**
     * The journal entries this record implies, each balanced on its own.
     *
     * Almost always exactly one — the `PostsOneEntry` trait provides that
     * from a single date/description/lines triple. A record spanning several
     * business dates returns one entry per date, so that a report asking
     * "what was earned before the 1st" gets the true answer instead of the
     * whole record landing on its earliest date.
     *
     * @return list<Entry>
     */
    public function entries(): array;
}
