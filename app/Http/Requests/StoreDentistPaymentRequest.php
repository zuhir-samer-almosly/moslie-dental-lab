<?php

namespace App\Http\Requests;

use App\Concerns\MoneyValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreDentistPaymentRequest extends FormRequest
{
    use MoneyValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dentist_id' => ['required', 'exists:dentists,id'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            ...$this->moneyRules('amount', $this->dentistIsDollar()),
        ];
    }

    /**
     * Validated data with the dollar amount converted to the cents the column
     * holds, ready to hand straight to the model.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->moneyPayload($this->validated(), 'amount', $this->dentistIsDollar());
    }

    /**
     * Whether the dentist being paid is billed in dollars.
     *
     * `dentist_id` has not been validated yet when this runs — rules() calls
     * it to build the rules themselves. A malformed request (`dentist_id[]=1`)
     * makes `input()` return an array; `find()` on an array id returns a
     * Collection, and `?->isDollar()` on that throws BadMethodCallException
     * before validation gets a chance to reject it with a 422. Guarding with
     * `is_numeric` lets a bad id fall through to the `exists:dentists,id`
     * rule instead, the same way it was meant to fail.
     */
    private function dentistIsDollar(): bool
    {
        $dentistId = $this->input('dentist_id');

        if (! is_numeric($dentistId)) {
            return false;
        }

        return (bool) \App\Models\Dentist::find($dentistId)?->isDollar();
    }
}
