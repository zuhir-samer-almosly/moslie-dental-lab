<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesMonth;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Money\Rate;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use ResolvesMonth;

    /**
     * Display the merged employees + monthly salaries page.
     */
    public function index(Request $request)
    {
        // Cast the withSum aggregate to int: MySQL returns SUM as a string, which
        // makes the frontend's .toLocaleString() a no-op (unformatted digits in prod).
        $employees = Employee::withSum('payments', 'amount')->latest()->get()
            ->each(fn (Employee $employee) => $employee->payments_sum_amount = (int) $employee->payments_sum_amount);

        $month = $this->resolveMonth($request->query('month'));
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $payments = EmployeePayment::with('employee')
            ->whereBetween('payment_date', [$start, $end])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        return inertia('employees/index', [
            'employees' => $employees,
            'payments' => $payments,
            'month' => $month->format('Y-m'),
            'total' => (int) $payments->sum('amount'),
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        Employee::create($request->validated());

        return redirect()->back()
            ->with('success', 'تم إضافة الموظف بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return redirect()->back()
            ->with('success', 'تم تحديث الموظف بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        // The FK cascades: deleting an employee would wipe their salary
        // history and retroactively change past finance reports. Block it —
        // deactivating (is_active) is the supported way to retire someone.
        if ($employee->payments()->exists()) {
            return redirect()->back()
                ->with('error', 'لا يمكن حذف الموظف لوجود رواتب مسجلة له. يمكنك إلغاء تفعيله بدلاً من ذلك.');
        }

        $employee->delete();

        return redirect()->back()
            ->with('success', 'تم حذف الموظف بنجاح');
    }
}
