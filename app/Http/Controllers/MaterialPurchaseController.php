<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesMonth;
use App\Http\Requests\StoreMaterialPurchaseRequest;
use App\Http\Requests\UpdateMaterialPurchaseRequest;
use App\Models\MaterialPurchase;
use App\Money\Rate;
use Illuminate\Http\Request;

class MaterialPurchaseController extends Controller
{
    use ResolvesMonth;

    /**
     * Display a listing of the resource for a given month.
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));

        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $purchases = MaterialPurchase::query()
            ->whereBetween('purchase_date', [$start, $end])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        return inertia('material-purchases/index', [
            'purchases' => $purchases,
            'month' => $month->format('Y-m'),
            'total' => (int) $purchases->sum('amount'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('material-purchases/create', [
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMaterialPurchaseRequest $request)
    {
        $materialPurchase = MaterialPurchase::create($request->payload());

        if ($materialPurchase->isForeign()) {
            Rate::remember($materialPurchase->purchase_date, $materialPurchase->rate);
        }

        return redirect()->route('material-purchases.index')
            ->with('success', 'تم تسجيل المادة بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaterialPurchase $materialPurchase)
    {
        return inertia('material-purchases/edit', [
            'purchase' => $materialPurchase,
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMaterialPurchaseRequest $request, MaterialPurchase $materialPurchase)
    {
        $materialPurchase->update($request->payload());

        if ($materialPurchase->isForeign()) {
            Rate::remember($materialPurchase->purchase_date, $materialPurchase->rate);
        }

        return redirect()->route('material-purchases.index')
            ->with('success', 'تم تحديث المادة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaterialPurchase $materialPurchase)
    {
        $materialPurchase->delete();

        return redirect()->route('material-purchases.index')
            ->with('success', 'تم حذف المادة بنجاح');
    }
}
