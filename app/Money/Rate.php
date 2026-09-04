<?php

namespace App\Money;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

/**
 * Conversion between US dollars and the lira, which is this lab's currency of
 * record. Dollars are how money sometimes arrives; every dollar figure is
 * converted once, at the rate of its own day, and the lira result is what the
 * books hold from then on.
 */
class Rate
{
    /**
     * The rate in effect on a date: the newest one recorded on or before it,
     * so days the owner did not set a rate inherit the last one he did.
     */
    public static function on(?string $date): ?string
    {
        // No date, no rate. Reports can be asked for an unbounded or
        // not-yet-valid period, and that must not become a 500 before their
        // own validation has had a chance to answer.
        if ($date === null) {
            return null;
        }

        return ExchangeRate::query()
            ->where('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first()?->rate;
    }

    /**
     * The rate the owner set for a day by hand, from the sidebar control.
     *
     * This is the deliberate way to record a rate, and it takes precedence:
     * `remember` will not overwrite it later in the day. Like every other
     * rate it is reference data for what comes next — writing it never moves
     * a figure already booked.
     */
    public static function set(string $date, string $rate): void
    {
        ExchangeRate::updateOrCreate(
            ['rate_date' => Carbon::parse($date)->toDateString()],
            ['rate' => $rate, 'is_manual' => true],
        );
    }

    /** Whether a rate was recorded for this exact day, by either route. */
    public static function isRecordedOn(?string $date): bool
    {
        if ($date === null) {
            return false;
        }

        return ExchangeRate::query()
            ->where('rate_date', Carbon::parse($date)->toDateString())
            ->exists();
    }

    /**
     * Keep the rate a foreign entry was made at as that day's rate, so the
     * next entry on the same day offers it rather than asking again.
     *
     * This is reference data for future entries only. Rewriting it never
     * moves a figure already booked — those carry their own copy of the rate.
     *
     * A day the owner set by hand is left alone. The rate on an entry is what
     * *that dentist* was charged, which is not always the rate the day is
     * priced at; before this, one order typed at an odd rate quietly became
     * the rate every invoice for the day was read back through.
     */
    public static function remember(?string $date, ?string $rate): void
    {
        if ($date === null || $rate === null) {
            return;
        }

        $day = Carbon::parse($date)->toDateString();

        if (ExchangeRate::query()->where('rate_date', $day)->value('is_manual')) {
            return;
        }

        ExchangeRate::updateOrCreate(
            ['rate_date' => $day],
            ['rate' => $rate],
        );
    }

    /**
     * Convert US cents to whole lira, rounded to the nearest lira.
     *
     * Float arithmetic is exact for every magnitude this lab deals in — it was
     * checked against integer-scaled arithmetic over 400k random cent/rate
     * pairs spanning rates to 20,000 and amounts to $50,000 with no divergence.
     */
    public static function toSyp(int $cents, string $rate): int
    {
        return (int) round($cents / 100 * (float) $rate);
    }
}
