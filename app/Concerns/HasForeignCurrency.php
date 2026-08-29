<?php

namespace App\Concerns;

use App\Money\Currency;
use App\Money\MissingRateException;
use App\Money\Rate;

/**
 * Money that may have arrived in a currency other than the row's own.
 *
 * A row is in one of three states:
 *
 *   Lira            — `amount` holds lira; no provenance.
 *   Dollars, converted — `original_amount` and `rate` say what it was, and
 *                     `amount` holds the lira it became. Conversion happens
 *                     once, on write, at the rate stored on the row, so a
 *                     rate recorded tomorrow never moves a figure booked
 *                     today.
 *   Native dollars  — the owner (a dollar dentist) is denominated in dollars,
 *                     so nothing converts: `original_amount` holds the cents,
 *                     `rate` stays NULL, and the lira column is 0. He owes no
 *                     lira, and every lira SUM() in the codebase stays right
 *                     without being touched.
 *
 * Which state a row is in is decided by its OWNER, never by inspecting the
 * row: `nativeCurrency()` asks the dentist. `rate` NULL is the row's own
 * marker of the third state, and the two are kept from diverging by the
 * exception below.
 */
trait HasForeignCurrency
{
    public static function bootHasForeignCurrency(): void
    {
        static::saving(fn ($model) => $model->applyExchangeRate());
    }

    /**
     * The column holding the value in the row's own currency. Every money
     * table calls it `amount` except `order_items`, which calls it `price`.
     */
    protected function liraColumn(): string
    {
        return 'amount';
    }

    /**
     * The currency this row's OWNER is denominated in. SYP for everything
     * that has no dollar owner — the expense tables, and every lira dentist.
     */
    protected function nativeCurrency(): Currency
    {
        return Currency::SYP;
    }

    /** Is this row dollars that were never converted, and never will be? */
    public function isNativeUsd(): bool
    {
        return $this->nativeCurrency() === Currency::USD;
    }

    /** Did this money arrive as something other than the lira? */
    public function isForeign(): bool
    {
        return $this->currency !== null && $this->currency !== 'SYP';
    }

    /**
     * This row's value in its own currency's minor unit: cents for a native
     * dollar row, whole lira for everything else. What the ledger posts.
     */
    public function valueInOwnCurrency(): int
    {
        return $this->isNativeUsd()
            ? (int) $this->original_amount
            : (int) $this->{$this->liraColumn()};
    }

    protected function applyExchangeRate(): void
    {
        if ($this->isNativeUsd()) {
            // Nothing to convert, and nothing to convert AT: a dollar
            // dentist's money never touches a rate.
            if ($this->rate !== null) {
                throw new \InvalidArgumentException(
                    static::class.' belongs to a dollar dentist and must not carry an exchange rate; '
                    .'his money is never converted.'
                );
            }

            $this->currency = Currency::USD->value;
            $this->{$this->liraColumn()} = 0;

            if ($this->original_amount === null) {
                throw new MissingRateException(
                    static::class.' for a dollar dentist needs an original_amount; '
                    .'there is no lira figure to fall back on.'
                );
            }

            return;
        }

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
