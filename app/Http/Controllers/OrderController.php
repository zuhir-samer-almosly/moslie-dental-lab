<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesMonth;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Ledger\Ledger;
use App\Ledger\LedgerReports;
use App\Models\Order;
use App\Money\Rate;
use App\Support\OrderPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ResolvesMonth;

    public function __construct(
        private readonly LedgerReports $reports,
        private readonly Ledger $ledger,
    ) {}

    /**
     * Display a listing of the resource for a given month.
     */
    public function index(Request $request)
    {
        $orders = Order::with(['dentist', 'items'])->latest()->get();

        // Compute carried balances against the FULL history first, so filtering
        // the visible list to one month below doesn't distort them.
        $this->assignPreviousBalances($orders);

        // Show one month at a time (consistent with the other ledgers) so the
        // list stays bounded regardless of how many orders exist overall.
        $month = $this->resolveMonth($request->query('month'));
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Scope to the items dated in this month, not to whole orders: an
        // order's due_date is only its EARLIEST item date, so matching on it
        // alone both drags an order's later-dated items into this month and
        // hides an order whose earliest item fell in an earlier one.
        $visible = $orders
            ->map(fn (Order $o) => OrderPeriod::scope($o, $start->toDateString(), $end->toDateString()))
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
     * @param  Collection<int, Order>  $orders
     */
    private function assignPreviousBalances(Collection $orders): void
    {
        $movements = $this->reports->receivableMovementsByDentist();

        foreach ($orders->groupBy('dentist_id') as $dentistId => $dentistOrders) {
            $byDate = ($movements[$dentistId] ?? collect())->all();
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
        $dentists = \App\Models\Dentist::all();

        return inertia('orders/create', [
            'dentists' => $dentists,
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        $validated = $request->validated();
        $items = $validated['items'];
        unset($validated['items']);

        $items = $this->withLiraPrices($items);
        // Calculate total from items
        $validated['amount'] = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        // The order's due date is derived from the earliest item date.
        $validated['due_date'] = collect($items)->pluck('date')->filter()->min() ?? now()->toDateString();

        DB::transaction(fn () => $this->ledger->defer(function () use ($validated, $items) {
            $order = Order::create($validated);

            foreach ($items as $item) {
                $order->items()->create($this->itemAttributes($item));
            }
        }));

        return redirect()->route('orders.index')
            ->with('success', 'تم إضافة الطلب بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        $order->load('items');
        $dentists = \App\Models\Dentist::all();

        return inertia('orders/edit', [
            'order' => $order,
            'dentists' => $dentists,
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        $validated = $request->validated();
        $items = $validated['items'];
        unset($validated['items']);

        $items = $this->withLiraPrices($items);
        // Calculate total from items
        $validated['amount'] = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        // The order's due date is derived from the earliest item date.
        $validated['due_date'] = collect($items)->pluck('date')->filter()->min() ?? now()->toDateString();

        DB::transaction(fn () => $this->ledger->defer(function () use ($order, $validated, $items) {
            $order->update($validated);

            // Delete old items and create new ones
            $order->items()->delete();
            foreach ($items as $item) {
                $order->items()->create($this->itemAttributes($item));
            }
        }));

        return redirect()->route('orders.index')
            ->with('success', 'تم تحديث الطلب بنجاح');
    }

    /**
     * Map a validated item payload to the stored OrderItem attributes,
     * folding the per-item date, patient name and selected teeth into meta.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    /**
     * Resolve every item's lira price before anything sums them.
     *
     * An item quoted in dollars is a *quote*: it converts at the rate given
     * with it, and the order holds lira from then on. Doing it here means the
     * order's amount and the item rows are derived from one conversion rather
     * than two that could drift — `App\Money\Rate` is the only thing that
     * converts, here and in the model.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withLiraPrices(array $items): array
    {
        return array_map(function (array $item) {
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

        return redirect()->route('orders.index')
            ->with('success', 'تم حذف الطلب بنجاح');
    }
}
