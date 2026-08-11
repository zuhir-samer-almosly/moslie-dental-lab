<?php

namespace App\Ledger;

/**
 * One journal entry a posting rule implies: a business date, a description
 * and the lines that must balance on it.
 */
final class Entry
{
    /** @param  list<Line>  $lines */
    public function __construct(
        public readonly string $date,
        public readonly string $description,
        public readonly array $lines,
    ) {}
}
