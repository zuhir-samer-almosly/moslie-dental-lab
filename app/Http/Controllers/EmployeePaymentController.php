<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeePaymentRequest;
use App\Http\Requests\UpdateEmployeePaymentRequest;
use App\Models\EmployeePayment;

class EmployeePaymentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeePaymentRequest $request)
    {
        EmployeePayment::create($request->validated());

        return redirect()->back()
            ->with('success', 'تم تسجيل الراتب بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeePaymentRequest $request, EmployeePayment $employeePayment)
    {
        $employeePayment->update($request->validated());

        return redirect()->back()
            ->with('success', 'تم تحديث الراتب بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeePayment $employeePayment)
    {
        $employeePayment->delete();

        return redirect()->back()
            ->with('success', 'تم حذف الراتب بنجاح');
    }
}
