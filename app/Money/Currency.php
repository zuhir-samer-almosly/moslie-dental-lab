<?php

namespace App\Money;

/**
 * The two currencies this lab's books are kept in.
 *
 * Every stored money figure is an integer in its currency's MINOR unit: whole
 * lira for SYP (which has no subunit in practice), cents for USD. `minorPerMajor`
 * is the only place that ratio is written down.
 */
enum Currency: string
{
    case SYP = 'SYP';
    case USD = 'USD';

    /** Minor units in one major unit — 1 lira is 1, 1 dollar is 100 cents. */
    public function minorPerMajor(): int
    {
        return match ($this) {
            self::SYP => 1,
            self::USD => 100,
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::SYP => 'ل.س',
            self::USD => '$',
        };
    }
}
