<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
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
    public function __construct(private readonly DentistPayment $payment) {}

    public function shouldPost(): bool
    {
        return (int) $this->payment->amount !== 0;
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
        $amount = (int) $this->payment->amount;

        return [
            Line::debit(AccountCode::CASH->value, $amount),
            Line::credit(AccountCode::RECEIVABLE->value, $amount, $this->payment->dentist_id),
        ];
    }
}
