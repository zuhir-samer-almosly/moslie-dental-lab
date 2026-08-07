<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * An order is a receivable the moment it exists. Cancelled orders post
 * nothing, matching Order::billable() — the scope every money report uses.
 *
 * Dated by `due_date`, and valued from `orders.amount` rather than the
 * `total` items accessor, because those are what the existing reports use.
 */
final class OrderPosting implements Posting
{
    public function __construct(private readonly Order $order) {}

    public function shouldPost(): bool
    {
        return $this->order->status !== 'cancelled' && (int) $this->order->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->order->due_date)->toDateString();
    }

    public function description(): string
    {
        return "طلب #{$this->order->id}";
    }

    public function lines(): array
    {
        $amount = (int) $this->order->amount;

        return [
            Line::debit(AccountCode::RECEIVABLE->value, $amount, $this->order->dentist_id),
            Line::credit(AccountCode::REVENUE->value, $amount),
        ];
    }
}
