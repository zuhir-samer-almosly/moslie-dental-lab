<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource for a given month.
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));

        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$start, $end])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        return inertia('expenses/index', [
            'expenses' => $expenses,
            'month' => $month->format('Y-m'),
            'total' => (int) $expenses->sum('amount'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('expenses/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request)
    {
        Expense::create($request->validated());

        return redirect()->route('expenses.index')
            ->with('success', 'تم تسجيل المصروف بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        return inertia('expenses/edit', [
            'expense' => $expense,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->validated());

        return redirect()->route('expenses.index')
            ->with('success', 'تم تحديث المصروف بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'تم حذف المصروف بنجاح');
    }

    /**
     * Parse a "Y-m" month string, falling back to the current month.
     */
    private function resolveMonth(?string $month): Carbon
    {
        if ($month) {
            try {
                // `!` resets unspecified parts (day, time) instead of filling
                // them from "now" — without it, parsing "2026-06" on July 31
                // produces June 31 → overflows into July.
                return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // fall through to current month
            }
        }

        return Carbon::now()->startOfMonth();
    }
}
