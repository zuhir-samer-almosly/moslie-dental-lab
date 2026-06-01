<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = Order::with(['dentist', 'items'])->latest()->get();

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
        $amount = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        $validated['amount'] = $amount;

        $order = Order::create($validated);

        // Create order items
        foreach ($items as $item) {
            $selectedTeeth = $item['selected_teeth'] ?? [];
            $patientName = $item['patient_name'] ?? '';
            unset($item['selected_teeth'], $item['patient_name']);
            $item['meta'] = ['selected_teeth' => $selectedTeeth, 'patient_name' => $patientName];
            $order->items()->create($item);
        }

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
        $amount = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        $validated['amount'] = $amount;

        $order->update($validated);

        // Delete old items and create new ones
        $order->items()->delete();
        foreach ($items as $item) {
            $selectedTeeth = $item['selected_teeth'] ?? [];
            $patientName = $item['patient_name'] ?? '';
            unset($item['selected_teeth'], $item['patient_name']);
            $item['meta'] = ['selected_teeth' => $selectedTeeth, 'patient_name' => $patientName];
            $order->items()->create($item);
        }

        return redirect()->route('orders.index')
            ->with('success', 'تم تحديث الطلب بنجاح');
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
