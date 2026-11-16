<?php

namespace App\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Saves and deletes a money record together with its ledger entry, or not at
 * all.
 *
 * LedgerObserver posts after the row is written. Without a transaction around
 * both, a posting that threw would leave a record the books know nothing
 * about — or, on a delete, an entry for a record that is gone. Lives on the
 * model rather than in each controller so it also covers the assistant
 * tools, the seeders and any import path.
 */
trait WritesWithLedger
{
    public function save(array $options = [])
    {
        return DB::transaction(fn () => parent::save($options));
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }
}
