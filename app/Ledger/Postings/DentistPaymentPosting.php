<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Ledger\PostsOneEntry;
use App\Models\DentistPayment;
use Illuminate\Support\Carbon;

/**
 * Money in from a dentist: it lands in the cash box and reduces what that
 * dentist owes.
 *
 * Dated by `payment_date` falling back to `created_at`, matching the
 * COALESCE the existing reports use.
 */
final class DentistPaymentPosting implements Posting
{
    use PostsOneEntry;

    public function __construct(private readonly DentistPayment $payment) {}

    public function shouldPost(): bool
    {
        return $this->payment->valueInOwnCurrency() !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->payment->payment_date ?? $this->payment->created_at)->toDateString();
    }

    public function description(): string
    {
        return "دفعة #{$this->payment->id}";
    }

    public function lines(): array
    {
        $currency = $this->payment->dentist->billingCurrency();
        $amount = $this->payment->valueInOwnCurrency();

        return [
            Line::debit(AccountCode::cashFor($currency), $amount),
            Line::credit(AccountCode::receivableFor($currency), $amount, $this->payment->dentist_id),
        ];
    }
}
