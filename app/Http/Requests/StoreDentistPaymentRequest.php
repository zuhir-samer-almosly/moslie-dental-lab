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

    /** Whether the dentist being paid is billed in dollars. */
    private function dentistIsDollar(): bool
    {
        return (bool) \App\Models\Dentist::find($this->input('dentist_id'))?->isDollar();
    }
}
