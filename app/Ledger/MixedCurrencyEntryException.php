<?php

namespace App\Ledger;

use RuntimeException;

/**
 * An entry whose lines span more than one currency.
 *
 * Sibling of UnbalancedEntryException, and thrown for the same reason: debits
 * equalling credits is meaningless when the two sides count different units.
 * 500 cents and 500 lira balance as integers and are not the same money.
 */
class MixedCurrencyEntryException extends RuntimeException {}
