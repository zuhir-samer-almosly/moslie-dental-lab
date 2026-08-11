<?php

namespace App\Support;

use App\Models\Order;

/**
 * Scoping an order to a date period.
 *
 * An order's `due_date` is the EARLIEST of its item dates (see
 * OrderController::store/update), but every screen that shows an order
 * renders and totals it ONE ROW PER ITEM, each row carrying that item's own
 * date from `meta['date']`. Filtering a period against `due_date` alone is
 * therefore wrong in both directions: an order whose earliest item is in
 * range drags in its out-of-range items too, and an order whose earliest
 * item predates the range is dropped even when a later item belongs inside
 * it. Both the invoice report and the orders list need the same per-item
 * answer, so it lives here rather than being written twice.
 */
class OrderPeriod
{
    /**
     * Return the order carrying only the items dated within [$from, $to],
     * or null when nothing in it belongs to the period.
     *
     * Items with no date of their own fall back to the order's `due_date`,
     * mirroring what the frontend prints for them. Orders with no items at
     * all are matched on `due_date`, since that is all they have.
     *
     * Both bounds are inclusive `Y-m-d` strings — that format sorts
     * lexicographically, so plain string comparison is a correct date
     * comparison and avoids parsing every item date into a Carbon.
     */
    public static function scope(Order $order, string $from, string $to): ?Order
    {
        $dueDate = $order->due_date->toDateString();

        if ($order->items->isEmpty()) {
            return $dueDate >= $from && $dueDate <= $to ? $order : null;
        }

        $matching = $order->items
            ->filter(function ($item) use ($from, $to, $dueDate) {
                $date = $item->meta['date'] ?? $dueDate;

                return $date >= $from && $date <= $to;
            })
            // Chronological, not insertion order. Items are stored in the
            // order they were typed into the form, so an item added to an
            // existing order lands last however early it is dated — which
            // reads as a jumbled invoice (a 29/7 line printing under 7/8).
            ->sortBy(fn ($item) => $item->meta['date'] ?? $dueDate)
            ->values();

        if ($matching->isEmpty()) {
            return null;
        }

        $order->setRelation('items', $matching);

        return $order;
    }
}
