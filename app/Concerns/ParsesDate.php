<?php

namespace App\Concerns;

use Illuminate\Support\Carbon;

trait ParsesDate
{
    /**
     * Accept only a well-formed `Y-m-d` value; anything missing, malformed,
     * or an overflow date (e.g. `2026-02-31`) collapses to null — "no
     * filter" — rather than throwing or silently rolling over to a
     * different date.
     *
     * `createFromFormat` only throws on outright parse errors, not on
     * out-of-range components, so an overflow date parses without error and
     * has to be caught separately by re-formatting and comparing back
     * against the original input.
     */
    protected function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            // `!` resets unspecified parts (time) instead of filling them
            // from "now".
            $parsed = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $parsed->format('Y-m-d') === $value ? $value : null;
    }
}
