<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDentistPaymentRequest;
use App\Http\Requests\UpdateDentistPaymentRequest;
use App\Models\DentistPayment;

class DentistPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = DentistPayment::with('dentist')->latest()->get();

        return inertia('payments/index', [
            'payments' => $payments,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $dentists = \App\Models\Dentist::all();

        return inertia('payments/create', [
            'dentists' => $dentists,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDentistPaymentRequest $request)
    {
        DentistPayment::create($request->validated());

        return redirect()->route('payments.index')
            ->with('success', 'تم إضافة الدفعة بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(DentistPayment $dentistPayment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DentistPayment $dentistPayment)
    {
        $dentists = \App\Models\Dentist::all();

        return inertia('payments/edit', [
            'payment' => $dentistPayment,
            'dentists' => $dentists,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDentistPaymentRequest $request, DentistPayment $dentistPayment)
    {
        $dentistPayment->update($request->validated());

        return redirect()->route('payments.index')
            ->with('success', 'تم تحديث الدفعة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DentistPayment $dentistPayment)
    {
        $dentistPayment->delete();

        return redirect()->route('payments.index')
            ->with('success', 'تم حذف الدفعة بنجاح');
    }
}
