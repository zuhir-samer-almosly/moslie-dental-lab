<?php

namespace App\Money;

/**
 * Foreign money with no rate to convert it by is a bug, not a validation
 * failure. Booking it anyway would silently record nothing — the one outcome
 * worse than refusing the write.
 */
class MissingRateException extends \RuntimeException {}
