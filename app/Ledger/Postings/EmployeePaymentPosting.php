<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Ledger\PostsOneEntry;
use App\Models\EmployeePayment;
use Illuminate\Support\Carbon;

/** A salary payout: money leaves the cash box as a salary expense. */
final class EmployeePaymentPosting implements Posting
{
    use PostsOneEntry;

    public function __construct(private readonly EmployeePayment $payment) {}

    public function shouldPost(): bool
    {
        // Null-dated rows are invisible to existing reports (SQL WHERE doesn't match NULL),
        // so they remain invisible in the ledger to preserve historical accuracy.
        return $this->payment->payment_date !== null && (int) $this->payment->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->payment->payment_date)->toDateString();
    }

    public function description(): string
    {
        return "راتب #{$this->payment->id}";
    }

    public function lines(): array
    {
        $amount = (int) $this->payment->amount;

        return [
            Line::debit(AccountCode::SALARIES->value, $amount),
            Line::credit(AccountCode::CASH->value, $amount),
        ];
    }
}
