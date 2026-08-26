<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeePaymentRequest;
use App\Http\Requests\UpdateEmployeePaymentRequest;
use App\Models\EmployeePayment;
use App\Money\Rate;

class EmployeePaymentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeePaymentRequest $request)
    {
        $employeePayment = EmployeePayment::create($request->payload());

        if ($employeePayment->isForeign()) {
            Rate::remember($employeePayment->payment_date, $employeePayment->rate);
        }

        return redirect()->back()
            ->with('success', 'تم تسجيل الراتب بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeePaymentRequest $request, EmployeePayment $employeePayment)
    {
        $employeePayment->update($request->payload());

        if ($employeePayment->isForeign()) {
            Rate::remember($employeePayment->payment_date, $employeePayment->rate);
        }

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
