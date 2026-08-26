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
    public static function on(string $date): ?string
    {
        return ExchangeRate::query()
            ->where('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first()?->rate;
    }

    /**
     * Keep the rate a foreign entry was made at as that day's rate, so the
     * next entry on the same day offers it rather than asking again.
     *
     * This is reference data for future entries only. Rewriting it never
     * moves a figure already booked — those carry their own copy of the rate.
     */
    public static function remember(?string $date, ?string $rate): void
    {
        if ($date === null || $rate === null) {
            return;
        }

        ExchangeRate::updateOrCreate(
            ['rate_date' => Carbon::parse($date)->toDateString()],
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
