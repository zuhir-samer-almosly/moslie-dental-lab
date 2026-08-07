<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\Account;
use App\Models\Expense;
use Illuminate\Support\Carbon;

/**
 * A general expense. The debit account is resolved from the account whose
 * `category_key` matches, so adding a category means inserting an account
 * row — no code change here.
 */
final class ExpensePosting implements Posting
{
    /** Account code used when a category has no matching account. */
    private const FALLBACK = '5290'; // أخرى

    public function __construct(private readonly Expense $expense) {}

    public function shouldPost(): bool
    {
        return (int) $this->expense->amount !== 0;
    }

    public function date(): string
    {
        return Carbon::parse($this->expense->expense_date)->toDateString();
    }

    public function description(): string
    {
        return $this->expense->description ?: 'مصروف عام';
    }

    public function lines(): array
    {
        $amount = (int) $this->expense->amount;

        return [
            Line::debit($this->accountCode(), $amount),
            Line::credit(AccountCode::CASH->value, $amount),
        ];
    }

    private function accountCode(): string
    {
        return Account::expenseCategories()
            ->firstWhere('category_key', $this->expense->category)
            ?->code ?? self::FALLBACK;
    }
}
