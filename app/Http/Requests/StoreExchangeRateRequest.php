<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The day's lira-per-dollar rate, set by hand from the sidebar.
 *
 * There is no date field on purpose: this control sets *today's* rate, and the
 * day comes from the server clock. A client that could name the date could
 * rewrite the rate a past invoice is read back through.
 */
class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Matches the decimal(15,6) column: six decimals of precision and
            // nine digits of room, which the street rate will never exhaust.
            'rate' => ['required', 'numeric', 'min:0.000001', 'max:999999999'],
        ];
    }
}
