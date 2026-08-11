<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesMonth;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\DentistPayment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ResolvesMonth;

    /**
     * Display a listing of the resource for a given month.
     */
    public function index(Request $request)
    {
        $orders = Order::with(['dentist', 'items'])->latest()->get();
        $payments = DentistPayment::all(['dentist_id', 'amount', 'payment_date', 'created_at']);

        // Compute carried balances against the FULL history first, so filtering
        // the visible list to one month below doesn't distort them.
        $this->assignPreviousBalances($orders, $payments);

        // Show one month at a time (consistent with the other ledgers) so the
        // list stays bounded regardless of how many orders exist overall.
        $month = $this->resolveMonth($request->query('month'));
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $visible = $orders
            ->filter(fn (Order $o) => $this->orderTouchesMonth($o, $start, $end))
            ->values();

        return inertia('orders/index', [
            'orders' => $visible,
            'month' => $month->format('Y-m'),
        ]);
    }

    /**
     * Whether an order belongs on this month's list: any item's own date
     * (falling back to the order's due_date when an item has none) falls
     * inside [$start, $end], or — for an itemless order — its due_date does.
     *
     * due_date alone isn't enough: it's always the EARLIEST of the order's
     * item dates (see store()/update() below), so an order whose items span
     * more than one month would otherwise only ever show up in the earliest
     * month, hiding its later items from their own month's list. This can
     * make one order appear on two months' lists — deliberately: this page
     * shows the whole order (every item, one edit/delete action) as a unit,
     * so it belongs wherever any of its items do.
     */
    private function orderTouchesMonth(Order $order, Carbon $start, Carbon $end): bool
    {
        if ($order->items->isEmpty()) {
            return $order->due_date->between($start, $end);
        }

        return $order->items->contains(function ($item) use ($start, $end, $order) {
            $date = $item->meta['date'] ?? $order->due_date->toDateString();

            return Carbon::parse($date)->between($start, $end);
        });
    }

    /**
     * Attach each order's `previous_balance`: the dentist's outstanding balance
     * carried in from BEFORE that order's date — their earlier billable orders
     * minus their earlier payments.
     *
     * Done per dentist with a sorted two-pointer sweep (O(n log n)) rather than
     * re-scanning the whole order/payment set for every order (O(n²)).
     *
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, DentistPayment>  $payments
     */
    private function assignPreviousBalances(Collection $orders, Collection $payments): void
    {
        $paymentsByDentist = $payments->groupBy('dentist_id');

        foreach ($orders->groupBy('dentist_id') as $dentistId => $dentistOrders) {
            // Billable order amounts and payment amounts as [ts, amount] events,
            // each sorted ascending by date so a single forward pass suffices.
            $orderEvents = $dentistOrders
                ->reject(fn (Order $o) => $o->status === 'cancelled')
                ->map(fn (Order $o) => ['ts' => $o->due_date->timestamp, 'amount' => (int) $o->amount])
                ->sortBy('ts')
                ->values()
                ->all();

            $paymentEvents = ($paymentsByDentist[$dentistId] ?? collect())
                ->map(fn (DentistPayment $p) => [
                    'ts' => Carbon::parse($p->payment_date ?? $p->created_at)->timestamp,
                    'amount' => (int) $p->amount,
                ])
                ->sortBy('ts')
                ->values()
                ->all();

            // Visit every order (including cancelled — they still show a carried
            // balance) in ascending date order, accumulating everything that
            // falls strictly before the current order's date.
            $sortedOrders = $dentistOrders
                ->sortBy(fn (Order $o) => $o->due_date->timestamp)
                ->values();

            $oi = 0;
            $pi = 0;
            $orderSum = 0;
            $paymentSum = 0;

            foreach ($sortedOrders as $order) {
                $cutoff = $order->due_date->timestamp;

                while ($oi < count($orderEvents) && $orderEvents[$oi]['ts'] < $cutoff) {
                    $orderSum += $orderEvents[$oi]['amount'];
                    $oi++;
                }

                while ($pi < count($paymentEvents) && $paymentEvents[$pi]['ts'] < $cutoff) {
                    $paymentSum += $paymentEvents[$pi]['amount'];
                    $pi++;
                }

                $order->previous_balance = $orderSum - $paymentSum;
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

        // Calculate total from items
        $validated['amount'] = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        // The order's due date is derived from the earliest item date.
        $validated['due_date'] = collect($items)->pluck('date')->filter()->min() ?? now()->toDateString();

        DB::transaction(function () use ($validated, $items) {
            $order = Order::create($validated);

            foreach ($items as $item) {
                $order->items()->create($this->itemAttributes($item));
            }
        });

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

        // Calculate total from items
        $validated['amount'] = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        // The order's due date is derived from the earliest item date.
        $validated['due_date'] = collect($items)->pluck('date')->filter()->min() ?? now()->toDateString();

        DB::transaction(function () use ($order, $validated, $items) {
            $order->update($validated);

            // Delete old items and create new ones
            $order->items()->delete();
            foreach ($items as $item) {
                $order->items()->create($this->itemAttributes($item));
            }
        });

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
