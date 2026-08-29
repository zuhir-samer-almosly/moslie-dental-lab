<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDentistPaymentRequest;
use App\Http\Requests\UpdateDentistPaymentRequest;
use App\Models\DentistPayment;
use App\Money\Rate;

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
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDentistPaymentRequest $request)
    {
        $payment = DentistPayment::create($request->payload());

        if ($payment->isForeign() && ! $payment->isNativeUsd()) {
            Rate::remember($payment->payment_date, $payment->rate);
        }

        return redirect()->route('payments.index')
            ->with('success', 'تم إضافة الدفعة بنجاح');
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
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDentistPaymentRequest $request, DentistPayment $dentistPayment)
    {
        $dentistPayment->update($request->payload());

        if ($dentistPayment->isForeign() && ! $dentistPayment->isNativeUsd()) {
            Rate::remember($dentistPayment->payment_date, $dentistPayment->rate);
        }

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
