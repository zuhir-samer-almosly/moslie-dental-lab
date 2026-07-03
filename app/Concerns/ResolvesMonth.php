<?php

namespace App\Concerns;

use Illuminate\Support\Carbon;

trait ResolvesMonth
{
    /**
     * Resolve a `Y-m` query value to the first day of that month, falling
     * back to the current month when the value is missing or unparseable.
     */
    protected function resolveMonth(?string $month): Carbon
    {
        if ($month) {
            try {
                // `!` resets unspecified parts (day, time) instead of filling
                // them from "now" — without it, parsing "2026-06" on July 31
                // produces June 31 → overflows into July.
                return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // fall through to current month
            }
        }

        return Carbon::now()->startOfMonth();
    }
}
