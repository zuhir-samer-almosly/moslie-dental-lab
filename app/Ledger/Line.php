<?php

namespace App\Ledger;

/**
 * One side of an entry. A line carries a debit or a credit, never both.
 */
final class Line
{
    public function __construct(
        public readonly string $accountCode,
        public readonly int $debit,
        public readonly int $credit,
        public readonly ?int $dentistId,
    ) {}

    public static function debit(string $code, int $amount, ?int $dentistId = null): self
    {
        return new self($code, $amount, 0, $dentistId);
    }

    public static function credit(string $code, int $amount, ?int $dentistId = null): self
    {
        return new self($code, 0, $amount, $dentistId);
    }
}
