<?php

namespace App\Http\Controllers;

use App\Concerns\ParsesDate;
use App\Http\Requests\StoreDentistPaymentRequest;
use App\Http\Requests\StoreDentistPaymentsRequest;
use App\Http\Requests\UpdateDentistPaymentRequest;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Money\Rate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DentistPaymentController extends Controller
{
    use ParsesDate;

    /**
     * Display a listing of the resource — paginated and filterable by dentist
     * and date range, so the page never loads every payment ever recorded.
     */
    public function index(Request $request)
    {
        $dentistId = $request->integer('dentist') ?: null;
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        $payments = DentistPayment::query()
            ->with('dentist:id,name')
            ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId))
            ->when($from, fn ($q) => $q->where('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('payment_date', '<=', $to))
            // Sorted by the date the list shows and filters on, not by when
            // the row was typed in, so a back-dated payment sits among its
            // own day's payments instead of at the top.
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return inertia('payments/index', [
            'payments' => $payments,
            'dentists' => Dentist::orderBy('name')->get(['id', 'name']),
            'filters' => ['dentist' => $dentistId, 'from' => $from, 'to' => $to],
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
     * Store several payments from one dentist at once — the create page.
     * All or nothing: a row that fails to save takes the others with it, so
     * a batch is never left half-recorded.
     */
    public function storeMany(StoreDentistPaymentsRequest $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->payloads() as $payload) {
                $payment = DentistPayment::create($payload);

                if ($payment->isForeign() && ! $payment->isNativeUsd()) {
                    Rate::remember($payment->payment_date, $payment->rate);
                }
            }
        });

        return redirect()->route('payments.index')
            ->with('success', 'تم إضافة الدفعات بنجاح');
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
