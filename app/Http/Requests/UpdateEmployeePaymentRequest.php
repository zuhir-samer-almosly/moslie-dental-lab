<?php

namespace App\Http\Requests;

use App\Concerns\MoneyValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeePaymentRequest extends FormRequest
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
            'employee_id' => ['required', 'exists:employees,id'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            ...$this->moneyRules(),
        ];
    }

    /**
     * Validated data with the dollar amount converted to the cents the
     * column holds, ready to hand straight to the model.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->moneyPayload($this->validated());
    }
}
