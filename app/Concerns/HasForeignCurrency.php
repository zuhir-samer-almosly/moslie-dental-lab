<?php

namespace App\Concerns;

use App\Money\MissingRateException;
use App\Money\Rate;

/**
 * Money that may have arrived in a currency other than the lira.
 *
 * The lira is this lab's currency of record. A row's lira column is the single
 * figure the ledger and every report read; `currency`, `original_amount` and
 * `rate` are provenance beside it — what the money was before it was converted.
 *
 * Conversion happens once, on write, at the rate stored on the row. Nothing
 * re-derives it on read, so a rate recorded tomorrow never moves a figure
 * booked today.
 */
trait HasForeignCurrency
{
    public static function bootHasForeignCurrency(): void
    {
        static::saving(fn ($model) => $model->applyExchangeRate());
    }

    /**
     * The column holding the lira value. Every money table calls it `amount`
     * except `order_items`, which calls it `price`.
     */
    protected function liraColumn(): string
    {
        return 'amount';
    }

    /** Did this money arrive as something other than lira? */
    public function isForeign(): bool
    {
        return $this->currency !== null && $this->currency !== 'SYP';
    }

    protected function applyExchangeRate(): void
    {
        if (! $this->isForeign()) {
            // A lira row carries no conversion; clear any provenance left
            // behind by an edit that switched the currency back.
            $this->original_amount = null;
            $this->rate = null;

            return;
        }

        if ($this->original_amount === null || $this->rate === null) {
            throw new MissingRateException(
                static::class.' in '.$this->currency.' needs both an original_amount and a rate; '
                .'converting without them would book it as nothing.'
            );
        }

        $this->{$this->liraColumn()} = Rate::toSyp((int) $this->original_amount, (string) $this->rate);
    }
}
