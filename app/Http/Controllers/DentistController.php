<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDentistRequest;
use App\Http\Requests\UpdateDentistRequest;
use App\Models\Dentist;

class DentistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('dentists/index', [
            'dentists' => Dentist::markLedgerHistory(Dentist::latest()->get()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDentistRequest $request)
    {
        Dentist::create($request->validated());

        // Dentists are edited from a dialog that can float over any page —
        // most importantly a half-typed order — so a write lands the user
        // back where they were.
        return back()->with('success', 'تم إضافة الطبيب بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDentistRequest $request, Dentist $dentist)
    {
        $dentist->update($request->validated());

        return back()->with('success', 'تم تحديث الطبيب بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dentist $dentist)
    {
        // The FKs cascade: deleting a dentist would wipe all their orders and
        // payments. Never allow that once financial history exists.
        if ($dentist->orders()->exists() || $dentist->payments()->exists()) {
            return back()
                ->with('error', 'لا يمكن حذف الطبيب لوجود طلبات أو دفعات مسجلة باسمه.');
        }

        $dentist->delete();

        return back()
            ->with('success', 'تم حذف الطبيب بنجاح');
    }
}
