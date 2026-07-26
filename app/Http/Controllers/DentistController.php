<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDentistRequest;
use App\Http\Requests\UpdateDentistRequest;
use App\Models\Dentist;
use Illuminate\Http\Request;

class DentistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $dentists = Dentist::latest()->get();

        return inertia('dentists/index', [
            'dentists' => $dentists,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('dentists/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDentistRequest $request)
    {
        Dentist::create($request->validated());

        return $this->redirectAfterWrite($request, 'تم إضافة الطبيب بنجاح');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dentist $dentist)
    {
        return inertia('dentists/edit', [
            'dentist' => $dentist,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDentistRequest $request, Dentist $dentist)
    {
        $dentist->update($request->validated());

        return $this->redirectAfterWrite($request, 'تم تحديث الطبيب بنجاح');
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

    /**
     * Dentists are edited from a dialog that can float over any page — most
     * importantly a half-typed order — so a write must land the user back
     * where they were. Only the standalone create/edit pages ask to be sent
     * to the list, via a `to_index` flag on the payload. It is a boolean form
     * field rather than a URL, so there is no open-redirect surface, and it is
     * absent from the request rules so `validated()` never hands it to the
     * model.
     */
    private function redirectAfterWrite(Request $request, string $message)
    {
        $redirect = $request->boolean('to_index')
            ? redirect()->route('dentists.index')
            : back();

        return $redirect->with('success', $message);
    }
}
