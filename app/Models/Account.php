<?php

namespace App\Models;

use App\Money\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type', 'currency', 'category_key', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Code → row cache. The chart of accounts is small, fixed, and read on
     * every ledger write, so it is loaded once per request.
     *
     * @var Collection<string, self>|null
     */
    private static ?Collection $cache = null;

    public static function chart(): Collection
    {
        return self::$cache ??= self::query()->get()->keyBy('code');
    }

    /**
     * Drop the cache. Tests refresh the database between cases, so the cached
     * ids from a previous case would otherwise be stale.
     */
    public static function flushChart(): void
    {
        self::$cache = null;
    }

    public static function idFor(string $code): int
    {
        $account = self::chart()->get($code);

        if (! $account) {
            throw new \InvalidArgumentException("Unknown account code [{$code}].");
        }

        return $account->id;
    }

    public static function typeFor(string $code): string
    {
        $account = self::chart()->get($code);

        if (! $account) {
            throw new \InvalidArgumentException("Unknown account code [{$code}].");
        }

        return $account->type;
    }

    public static function currencyFor(string $code): Currency
    {
        $account = self::chart()->get($code);

        if (! $account) {
            throw new \InvalidArgumentException("Unknown account code [{$code}].");
        }

        return Currency::from($account->currency);
    }

    /**
     * Expense accounts that map to an `expenses.category` value, in display
     * order. This is the single definition of the general-expense categories.
     */
    public static function expenseCategories(): Collection
    {
        return self::chart()
            ->filter(fn (self $a) => $a->category_key !== null && $a->is_active)
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * All expense category accounts including inactive ones. Used by the ledger
     * to post expenses to their original accounts even if deactivated later,
     * so they don't reclassify to 5290 (أخرى) just because the UI hid them.
     */
    public static function allExpenseCategories(): Collection
    {
        return self::chart()
            ->filter(fn (self $a) => $a->category_key !== null)
            ->sortBy('sort_order')
            ->values();
    }
}
