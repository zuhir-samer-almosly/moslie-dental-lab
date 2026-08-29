<?php

namespace App\Ledger\Postings;

use App\Ledger\AccountCode;
use App\Ledger\Entry;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Money\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * An order is a receivable the moment it exists. Cancelled orders post
 * nothing, matching Order::billable() — the scope every money report uses.
 *
 * Posted ONE ENTRY PER ITEM DATE, not one per order. An order's `due_date` is
 * only the EARLIEST of its item dates (OrderController::store/update), and
 * items are routinely appended to an existing order weeks later — so dating
 * the whole order by `due_date` books next month's work into this month. Every
 * screen already bills an order item by item, on each item's own
 * `meta['date']` (see App\Support\OrderPeriod); the ledger has to agree, or a
 * period's opening balance carries work that the same period then lists again
 * as its own, and the invoice bills it twice.
 *
 * Valued in whole lira or cents depending on the order's DENTIST — never the
 * order row's own `currency` column, which OrderController keeps in sync on
 * every real write but which a directly constructed row (a factory, a test)
 * can leave stale. Reading the dentist is the same authority
 * `OrderItem::nativeCurrency()` already uses, so an item's unit and the
 * order's account can never disagree. Valued from `orders.amount`/
 * `orders.original_amount` rather than the `total` items accessor, because
 * that is what the existing reports use; the items only decide *when* each
 * part of that amount was earned.
 */
final class OrderPosting implements Posting
{
    public function __construct(private readonly Order $order) {}

    public function shouldPost(): bool
    {
        return $this->order->status !== 'cancelled' && $this->valueInDentistCurrency() !== 0;
    }

    /** @return list<Entry> */
    public function entries(): array
    {
        $currency = $this->dentistCurrency();

        return $this->amountsByDate()
            ->map(fn (int $amount, string $date) => new Entry(
                $date,
                "طلب #{$this->order->id}",
                [
                    Line::debit(AccountCode::receivableFor($currency), $amount, $this->order->dentist_id),
                    Line::credit(AccountCode::revenueFor($currency), $amount),
                ],
            ))
            ->values()
            ->all();
    }

    /**
     * What the order earned on each date, oldest first.
     *
     * Items with no date of their own fall back to the order's `due_date`,
     * and an order with no items at all is entirely on `due_date` — both
     * mirroring OrderPeriod, so the ledger and the invoice bucket the same
     * money into the same period.
     *
     * @return Collection<string, int> `Y-m-d` => amount earned that day
     */
    private function amountsByDate(): Collection
    {
        $dueDate = Carbon::parse($this->order->due_date)->toDateString();
        $amount = $this->valueInDentistCurrency();
        $items = $this->order->items;

        if ($items->isEmpty()) {
            return collect([$dueDate => $amount]);
        }

        $byDate = $items
            ->groupBy(fn (OrderItem $item) => $item->meta['date'] ?? $dueDate)
            ->map(fn (Collection $group) => (int) $group->sum(
                fn (OrderItem $item) => $item->quantity * $item->valueInOwnCurrency()
            ));

        // This order's own value in its dentist's currency stays the authority
        // for what it is WORTH, so splitting it by date can never change a
        // total. The two agree by construction — OrderController recomputes
        // `amount` (and `original_amount`, for a dollar dentist) from the
        // items on every write, and `money:redenominate` recomputes it too —
        // so this is normally zero. Should a row ever drift, the difference
        // belongs on the order's own date rather than silently vanishing
        // from the books.
        $residual = $amount - $byDate->sum();

        if ($residual !== 0) {
            $byDate[$dueDate] = ($byDate[$dueDate] ?? 0) + $residual;
        }

        return $byDate
            ->reject(fn (int $value) => $value === 0)
            ->sortKeys();
    }

    /**
     * The single authority for what currency this order's money is in: its
     * DENTIST's, resolved fresh every time rather than trusted from the order
     * row's own `currency` column. `entries()` picks the account pair from
     * this; `amountsByDate()`/`valueInDentistCurrency()` pick the unit from
     * it too — the same call, so the two can never come from different
     * places. A row whose `currency` column disagrees with its dentist (a
     * factory default, a stale edit) is resolved as the dentist says, not as
     * the row claims.
     */
    private function dentistCurrency(): Currency
    {
        return $this->order->dentist->billingCurrency();
    }

    /**
     * This order's value in `dentistCurrency()`'s minor unit: cents for a
     * dollar dentist, whole lira otherwise. Deliberately not
     * `Order::valueInOwnCurrency()`, which trusts the row's own `currency`
     * column instead of the dentist.
     */
    private function valueInDentistCurrency(): int
    {
        return $this->dentistCurrency() === Currency::USD
            ? (int) $this->order->original_amount
            : (int) $this->order->amount;
    }
}
