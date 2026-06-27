<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeePaymentRequest;
use App\Http\Requests\UpdateEmployeePaymentRequest;
use App\Models\Employee;
use App\Models\EmployeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeePaymentController extends Controller
{
    /**
     * Display a listing of the resource for a given month.
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));

        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $payments = EmployeePayment::with('employee')
            ->whereBetween('payment_date', [$start, $end])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        return inertia('employee-payments/index', [
            'payments' => $payments,
            'month' => $month->format('Y-m'),
            'total' => (int) $payments->sum('amount'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('employee-payments/create', [
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeePaymentRequest $request)
    {
        EmployeePayment::create($request->validated());

        return redirect()->route('employee-payments.index')
            ->with('success', 'تم تسجيل الراتب بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmployeePayment $employeePayment)
    {
        return inertia('employee-payments/edit', [
            'payment' => $employeePayment,
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeePaymentRequest $request, EmployeePayment $employeePayment)
    {
        $employeePayment->update($request->validated());

        return redirect()->route('employee-payments.index')
            ->with('success', 'تم تحديث الراتب بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeePayment $employeePayment)
    {
        $employeePayment->delete();

        return redirect()->route('employee-payments.index')
            ->with('success', 'تم حذف الراتب بنجاح');
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
