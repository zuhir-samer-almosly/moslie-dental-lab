<?php

namespace App\Concerns;

use App\Models\Dentist;

/**
 * For a form request that submits `dentist_id` and whose rules depend on
 * whether that dentist is billed in dollars.
 */
trait ResolvesDentistCurrency
{
    /**
     * Whether the submitted dentist is billed in dollars.
     *
     * `dentist_id` has not been validated yet when this runs — rules() calls
     * it to build the rules themselves. A malformed request (`dentist_id[]=1`)
     * makes `input()` return an array; `find()` on an array id returns a
     * Collection, and `?->isDollar()` on that throws BadMethodCallException
     * before validation gets a chance to reject it with a 422. Guarding with
     * `is_numeric` lets a bad id fall through to the `exists:dentists,id`
     * rule instead, the same way it was meant to fail.
     */
    protected function dentistIsDollar(): bool
    {
        $dentistId = $this->input('dentist_id');

        if (! is_numeric($dentistId)) {
            return false;
        }

        return (bool) Dentist::find($dentistId)?->isDollar();
    }
}
