<?php

namespace App\Ledger;

use App\Money\Currency;

/**
 * The structural accounts the posting rules reference by name. Expense
 * category accounts (5200–5290) are deliberately absent: they are resolved
 * at runtime from `accounts.category_key`, so adding a category is a row,
 * not a code change.
 */
enum AccountCode: string
{
    case CASH = '1000';
    case RECEIVABLE = '1100';
    case CAPITAL = '3000';
    case REVENUE = '4000';
    case SALARIES = '5000';
    case MATERIALS = '5100';
    case BAD_DEBT = '5900';
    case CASH_USD = '1001';
    case RECEIVABLE_USD = '1101';
    case REVENUE_USD = '4001';

    /**
     * The account a given currency's cash, receivables and revenue live in.
     * The ONLY place a currency maps to a code — nothing downstream should
     * ever hardcode '1101'.
     */
    public static function cashFor(Currency $currency): string
    {
        return match ($currency) {
            Currency::SYP => self::CASH->value,
            Currency::USD => self::CASH_USD->value,
        };
    }

    public static function receivableFor(Currency $currency): string
    {
        return match ($currency) {
            Currency::SYP => self::RECEIVABLE->value,
            Currency::USD => self::RECEIVABLE_USD->value,
        };
    }

    public static function revenueFor(Currency $currency): string
    {
        return match ($currency) {
            Currency::SYP => self::REVENUE->value,
            Currency::USD => self::REVENUE_USD->value,
        };
    }
}
