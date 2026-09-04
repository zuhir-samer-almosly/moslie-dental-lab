<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExchangeRateRequest;
use App\Money\Rate;
use Illuminate\Http\RedirectResponse;

/**
 * Setting today's lira-per-dollar rate from the sidebar control.
 *
 * Nothing already booked moves: every entry carries the rate it was converted
 * at. What changes is what new forms offer and what the `≈ $` figures beside
 * lira totals are read back through.
 */
class ExchangeRateController extends Controller
{
    public function store(StoreExchangeRateRequest $request): RedirectResponse
    {
        Rate::set(now()->toDateString(), (string) $request->validated('rate'));

        return back()->with('success', 'تم تحديث سعر الدولار');
    }
}
