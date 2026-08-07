<?php

namespace App\Observers;

use App\Ledger\Ledger;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the ledger in step with the money models. Attached via
 * #[ObservedBy] on each source model.
 *
 * Posting is automatic rather than an explicit call from each controller
 * because it must also cover the backfill command, the seeders, and any
 * future import path — none of which go through a controller.
 */
class LedgerObserver
{
    public function __construct(private readonly Ledger $ledger) {}

    public function saved(Model $model): void
    {
        $this->ledger->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->ledger->forget($model);
    }
}
