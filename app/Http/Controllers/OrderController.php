<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\DentistPayment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = Order::with(['dentist', 'items'])->latest()->get();

        // Per order, the dentist's outstanding balance carried in from BEFORE
        // this order's date: their earlier orders minus their earlier payments.
        $payments = DentistPayment::all(['dentist_id', 'amount', 'payment_date', 'created_at']);

        $orders->each(function (Order $order) use ($orders, $payments) {
            $cutoff = $order->due_date;

            $priorOrders = $orders
                ->where('dentist_id', $order->dentist_id)
                ->where('status', '!=', 'cancelled')
                ->filter(fn (Order $o) => $o->due_date < $cutoff)
                ->sum('amount');

            $priorPayments = $payments
                ->where('dentist_id', $order->dentist_id)
                ->filter(fn (DentistPayment $p) => \Illuminate\Support\Carbon::parse($p->payment_date ?? $p->created_at)->lt($cutoff))
                ->sum('amount');

            $order->previous_balance = (int) $priorOrders - (int) $priorPayments;
        });

        return inertia('orders/index', [
            'orders' => $orders,
        ]);
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
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
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
