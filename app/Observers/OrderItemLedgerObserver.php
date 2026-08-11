<?php

namespace App\Observers;

use App\Ledger\Ledger;
use App\Models\OrderItem;

/**
 * Re-posts an order whenever one of its items changes.
 *
 * OrderPosting splits an order across the dates its items carry, so the order
 * alone is no longer enough to know when its money was earned. Nothing else
 * would trigger that re-post: OrderController writes the order first and its
 * items afterwards, so LedgerObserver::saved fires on an order that still has
 * no items — and on update, the items are replaced after the order row is
 * saved. Without this the ledger would hold whatever the order looked like
 * before its items existed.
 *
 * An item is not a money record of its own: it never posts anything, it only
 * makes its parent order post again from current state.
 */
class OrderItemLedgerObserver
{
    public function __construct(private readonly Ledger $ledger) {}

    public function saved(OrderItem $item): void
    {
        $this->resync($item);
    }

    public function deleted(OrderItem $item): void
    {
        $this->resync($item);
    }

    private function resync(OrderItem $item): void
    {
        // Freshly loaded rather than from the relation cache: on an update the
        // order row has already been rewritten (new amount, new due_date) and
        // this item may be one of a batch still being written, so the posting
        // has to read what the database holds right now.
        $order = $item->order()->with('items')->first();

        if ($order) {
            $this->ledger->sync($order);
        }
    }
}
