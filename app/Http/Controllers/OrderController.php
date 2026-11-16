<?php

namespace App\Http\Controllers;

use App\Concerns\RedirectsToDaybook;
use App\Concerns\ResolvesMonth;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Ledger\Ledger;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\Order;
use App\Models\OrderItem;
use App\Money\Currency;
use App\Money\Rate;
use App\Support\OrderPeriod;
use App\Support\WorkTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use RedirectsToDaybook, ResolvesMonth;

    public function __construct(
        private readonly LedgerReports $reports,
        private readonly Ledger $ledger,
    ) {}

    /**
     * Display a listing of the resource for a given month.
     */
    public function index(Request $request)
    {
        // Show one month at a time (consistent with the other ledgers) so the
        // list stays bounded regardless of how many orders exist overall.
        $month = $this->resolveMonth($request->query('month'));
        [$start, $end] = $this->monthRange($month);

        // Only the orders that can have a line in this month are loaded — the
        // same two ways OrderPeriod::scope() below can match one: an item
        // dated inside the month, or (for an undated item, or an order with
        // no items) a due date inside it. Loading every order ever written
        // to filter here made each visit slower as the books grew.
        $orders = Order::with(['dentist', 'items'])
            ->where(fn ($query) => $query
                ->where(fn ($due) => $due
                    ->whereDate('due_date', '>=', $start)
                    ->whereDate('due_date', '<=', $end))
                ->orWhereHas('items', fn ($items) => $items
                    ->where('meta->date', '>=', $start)
                    ->where('meta->date', '<=', $end)))
            ->latest()
            ->get();

        // Each order's carried balance is read from the ledger by its own
        // date, so it does not depend on which other orders were loaded.
        $this->assignPreviousBalances($orders);

        // Scope to the items dated in this month, not to whole orders: an
        // order's due_date is only its EARLIEST item date, so matching on it
        // alone both drags an order's later-dated items into this month and
        // hides an order whose earliest item fell in an earlier one.
        $visible = $orders
            ->map(fn (Order $o) => OrderPeriod::scope($o, $start, $end))
            ->filter()
            ->values();

        return inertia('orders/index', [
            'orders' => $visible,
            'month' => $month->format('Y-m'),
        ]);
    }

    /**
     * Attach each order's `previous_balance`: what the dentist already owed
     * going into that order's date, read from the ledger's receivable account.
     *
     * Read rather than recomputed. Summing `orders.amount` by `due_date` here
     * — as this used to — bills an earlier order whole on its earliest item
     * date, so an order carrying a later item overstated every balance
     * between the two: the 300 worked on 2/8 was counted against an order
     * dated 28/7. The ledger posts each item on its own date (OrderPosting),
     * so accumulating its daily movements gives the balance that actually
     * stood on the day.
     *
     * Still one pass per dentist rather than a query per order: the movements
     * come back once, already ordered, and a two-pointer sweep walks them
     * alongside the orders (O(n log n)).
     *
     * Read from the receivable account of the dentist's own currency, so a
     * dollar dentist's balance is in cents, like the rest of his money.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function assignPreviousBalances(Collection $orders): void
    {
        $movements = [
            Currency::SYP->value => $this->reports->receivableMovementsByDentist(Currency::SYP),
            Currency::USD->value => $this->reports->receivableMovementsByDentist(Currency::USD),
        ];

        foreach ($orders->groupBy('dentist_id') as $dentistId => $dentistOrders) {
            $currency = $dentistOrders->first()->dentist?->billingCurrency() ?? Currency::SYP;
            $byDate = ($movements[$currency->value][$dentistId] ?? collect())->all();
            $dates = array_keys($byDate);
            $amounts = array_values($byDate);

            // Every order in date order, cancelled ones included: they post
            // nothing themselves but still show what was owed before them.
            // Both sides are bare `Y-m-d`, which sorts and compares as a date.
            $sorted = $dentistOrders
                ->sortBy(fn (Order $order) => $order->due_date->toDateString())
                ->values();

            $i = 0;
            $balance = 0;

            foreach ($sorted as $order) {
                // Strictly before: an order never counts itself, and orders
                // sharing a date all carry the same balance in.
                $cutoff = $order->due_date->toDateString();

                while ($i < count($dates) && $dates[$i] < $cutoff) {
                    $balance += $amounts[$i];
                    $i++;
                }

                $order->previous_balance = $balance;
            }
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Marked, because the form's dentist manager can edit a currency.
        $dentists = Dentist::markLedgerHistory(Dentist::all());

        return inertia('orders/create', [
            'dentists' => $dentists,
            'todayRate' => Rate::on(now()->toDateString()),
            'workTypes' => WorkTypes::forOrderForm(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        [$validated, $items, $dentist] = $this->prepare($request->validated());

        DB::transaction(fn () => $this->ledger->defer(function () use ($validated, $items, $dentist) {
            $order = Order::create($validated);
            $order->setRelation('dentist', $dentist);

            foreach ($items as $item) {
                $order->items()->save($this->itemFor($order, $item));
            }
        }));

        return $this->redirectAfterSave('orders.index')
            ->with('success', 'تم إضافة الطلب بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        $order->load('items');
        // Marked, because the form's dentist manager can edit a currency.
        $dentists = Dentist::markLedgerHistory(Dentist::all());

        return inertia('orders/edit', [
            'order' => $order,
            'dentists' => $dentists,
            'todayRate' => Rate::on(now()->toDateString()),
            'workTypes' => WorkTypes::forOrderForm(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        [$validated, $items, $dentist] = $this->prepare($request->validated());

        DB::transaction(fn () => $this->ledger->defer(function () use ($order, $validated, $items, $dentist) {
            $order->update($validated);
            $order->setRelation('dentist', $dentist);

            // Delete old items and create new ones
            $order->items()->delete();
            foreach ($items as $item) {
                $order->items()->save($this->itemFor($order, $item));
            }
        }));

        return $this->redirectAfterSave('orders.index')
            ->with('success', 'تم تحديث الطلب بنجاح');
    }

    /**
     * Turn a validated order payload into what gets written: the order's own
     * attributes, its items with their stored values resolved, and the
     * dentist it is for. Shared by storing and updating, which differ only
     * in what they do with the result.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: Dentist}
     */
    private function prepare(array $validated): array
    {
        $items = $validated['items'];
        unset($validated['items']);

        $dentist = Dentist::findOrFail($validated['dentist_id']);
        $items = $this->withCanonicalTypes($this->withResolvedPrices($items, $dentist), $dentist);

        // Both totals are derived from the items, so neither can drift from
        // them. For a dollar dentist the lira total is legitimately zero and
        // the cents total is what he owes; for a lira dentist, the reverse.
        $validated['currency'] = $dentist->billingCurrency()->value;
        $validated['amount'] = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        $validated['original_amount'] = $dentist->isDollar()
            ? collect($items)->sum(fn ($item) => $item['quantity'] * (int) $item['original_amount'])
            : null;
        // The order's due date is derived from the earliest item date.
        $validated['due_date'] = collect($items)->pluck('date')->filter()->min() ?? now()->toDateString();

        return [$validated, $items, $dentist];
    }

    /**
     * File each line under the spelling already in use, so a type typed with
     * a different hamza or a stray space does not become a second product.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withCanonicalTypes(array $items, Dentist $dentist): array
    {
        $canonical = WorkTypes::canonicaliser($dentist);

        return array_map(fn (array $item) => [...$item, 'type' => $canonical((string) $item['type'])], $items);
    }

    /**
     * Resolve every item's stored value before anything sums them.
     *
     * A dollar dentist's line is native dollars: the cents stand, no rate is
     * involved, and its lira price is zero. A lira dentist's dollar line is a
     * quote that converts at the rate given with it — `App\Money\Rate` is the
     * only thing that converts, here and in the model.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withResolvedPrices(array $items, Dentist $dentist): array
    {
        return array_map(function (array $item) use ($dentist) {
            if ($dentist->isDollar()) {
                $item['currency'] = 'USD';
                $item['price'] = 0;
                $item['rate'] = null;

                return $item;
            }

            if (($item['currency'] ?? 'SYP') !== 'USD') {
                $item['currency'] = 'SYP';
                $item['original_amount'] = null;
                $item['rate'] = null;

                return $item;
            }

            $item['price'] = Rate::toSyp((int) $item['original_amount'], (string) $item['rate']);

            return $item;
        }, $items);
    }

    /**
     * Build an item already knowing its order — and through it, its dentist.
     *
     * OrderItem::nativeCurrency() asks the dentist whether this line is
     * native dollars, and it is asked on every save. Setting the relation
     * here answers it from memory instead of a query per item.
     *
     * @param  array<string, mixed>  $item
     */
    private function itemFor(Order $order, array $item): OrderItem
    {
        $model = new OrderItem($this->itemAttributes($item));
        $model->setRelation('order', $order);

        return $model;
    }

    /**
     * Map a validated item payload to the stored OrderItem attributes,
     * folding the per-item date, patient name and selected teeth into meta.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function itemAttributes(array $item): array
    {
        $meta = [
            'selected_teeth' => $item['selected_teeth'] ?? [],
            'patient_name' => $item['patient_name'] ?? '',
            'date' => $item['date'] ?? null,
        ];

        unset($item['selected_teeth'], $item['patient_name'], $item['date']);
        $item['meta'] = $meta;

        return $item;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return $this->redirectAfterSave('orders.index')
            ->with('success', 'تم حذف الطلب بنجاح');
    }
}
