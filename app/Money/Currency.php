<?php

namespace App\Money;

/**
 * The two currencies this lab's books are kept in.
 *
 * Every stored money figure is an integer in its currency's MINOR unit: whole
 * lira for SYP (which has no subunit in practice), cents for USD.
 */
enum Currency: string
{
    case SYP = 'SYP';
    case USD = 'USD';
}
