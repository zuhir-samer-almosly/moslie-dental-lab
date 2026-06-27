<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialPurchaseRequest;
use App\Http\Requests\UpdateMaterialPurchaseRequest;
use App\Models\MaterialPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MaterialPurchaseController extends Controller
{
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
        return inertia('material-purchases/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMaterialPurchaseRequest $request)
    {
        MaterialPurchase::create($request->validated());

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
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMaterialPurchaseRequest $request, MaterialPurchase $materialPurchase)
    {
        $materialPurchase->update($request->validated());

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

    /**
     * Parse a "Y-m" month string, falling back to the current month.
     */
    private function resolveMonth(?string $month): Carbon
    {
        if ($month) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // fall through to current month
            }
        }

        return Carbon::now()->startOfMonth();
    }
}
