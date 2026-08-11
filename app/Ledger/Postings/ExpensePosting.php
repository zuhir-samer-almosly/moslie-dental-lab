<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Ledger\PostsOneEntry;
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
    use PostsOneEntry;

    /** Account code used when a category has no matching account. */
    private const FALLBACK = '5290'; // أخرى

    public function __construct(private readonly Expense $expense) {}

    public function shouldPost(): bool
    {
        // Null-dated rows are invisible to existing reports (SQL WHERE doesn't match NULL),
        // so they remain invisible in the ledger to preserve historical accuracy.
        return $this->expense->expense_date !== null && (int) $this->expense->amount !== 0;
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
        // Use allExpenseCategories() to include deactivated accounts. A deactivated
        // account should still receive postings for expenses that point to it; only
        // genuine unrecognised categories fall back to 5290.
        return Account::allExpenseCategories()
            ->firstWhere('category_key', $this->expense->category)
            ?->code ?? self::FALLBACK;
    }
}
