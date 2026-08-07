<?php

namespace App\Ledger;

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
}
