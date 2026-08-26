<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesMonth;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Money\Rate;
use Illuminate\Http\Request;

class ExpenseController extends Controller
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
        return inertia('expenses/create', [
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request)
    {
        $expense = Expense::create($request->payload());

        if ($expense->isForeign()) {
            Rate::remember($expense->expense_date, $expense->rate);
        }

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
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->payload());

        if ($expense->isForeign()) {
            Rate::remember($expense->expense_date, $expense->rate);
        }

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
}
