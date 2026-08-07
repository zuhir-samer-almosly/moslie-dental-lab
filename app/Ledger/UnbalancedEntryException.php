<?php

namespace App\Ledger;

/**
 * An entry whose debits do not equal its credits is a bug, not a validation
 * failure. It must never reach the database.
 */
class UnbalancedEntryException extends \RuntimeException {}
