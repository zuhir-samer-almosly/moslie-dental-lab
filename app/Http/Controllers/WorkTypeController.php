<?php

namespace App\Http\Controllers;

use App\Models\Dentist;
use App\Support\WorkTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Housekeeping for the work-type names typed on order lines: see which names
 * exist, and fold a misspelt one into the right one.
 */
class WorkTypeController extends Controller
{
    public function index()
    {
        $types = WorkTypes::summary();

        return inertia('work-types/index', [
            'types' => $types,
            'suggestions' => WorkTypes::suggestions($types),
        ]);
    }

    /** Fold one name into another (or into a new name — a rename). */
    public function merge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string', 'max:255'],
        ]);

        $from = $validated['from'];
        $to = WorkTypes::tidy($validated['to']);

        if ($to === '' || $to === $from) {
            throw ValidationException::withMessages(['to' => 'اختر اسماً مختلفاً للدمج فيه.']);
        }

        if (! WorkTypes::summary()->contains('name', $from)) {
            throw ValidationException::withMessages(['from' => 'هذا النوع لم يعد موجوداً.']);
        }

        $lines = WorkTypes::merge($from, $to);

        return back()->with('success', "تم تحويل «{$from}» إلى «{$to}» ({$lines} سطر)");
    }

    /**
     * Save one price into a dentist's price list — the order form's "keep
     * this price" button for a type typed for the first time.
     */
    public function storePrice(Request $request, Dentist $dentist): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Whole lira, or cents for a dollar price — as in the price list.
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'in:SYP,USD'],
        ]);

        $name = WorkTypes::canonicaliser($dentist)($validated['name']);

        // The model's setter forces USD for a dollar dentist.
        $dentist->price_list = [
            ...($dentist->price_list ?? []),
            $name => ['price' => $validated['price'], 'currency' => $validated['currency']],
        ];
        $dentist->save();

        return back()->with('success', "تم حفظ سعر «{$name}» في قائمة أسعار {$dentist->name}");
    }
}
