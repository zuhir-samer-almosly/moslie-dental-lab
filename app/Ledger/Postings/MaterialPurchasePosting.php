<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\MaterialPurchase;
use Illuminate\Support\Carbon;

/** A material purchase: money leaves the cash box as a materials expense. */
final class MaterialPurchasePosting implements Posting
{
    public function __construct(private readonly MaterialPurchase $purchase) {}

    public function shouldPost(): bool
    {
        return (int) $this->purchase->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->purchase->purchase_date)->toDateString();
    }

    public function description(): string
    {
        return "مواد: {$this->purchase->name}";
    }

    public function lines(): array
    {
        $amount = (int) $this->purchase->amount;

        return [
            Line::debit(AccountCode::MATERIALS->value, $amount),
            Line::credit(AccountCode::CASH->value, $amount),
        ];
    }
}
