<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employees = Employee::withSum('payments', 'amount')->latest()->get();

        return inertia('employees/index', [
            'employees' => $employees,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('employees/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        Employee::create($request->validated());

        return redirect()->route('employees.index')
            ->with('success', 'تم إضافة الموظف بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        return inertia('employees/edit', [
            'employee' => $employee,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return redirect()->route('employees.index')
            ->with('success', 'تم تحديث الموظف بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'تم حذف الموظف بنجاح');
    }
}
