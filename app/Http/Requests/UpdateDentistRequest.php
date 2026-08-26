<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDentistRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'phone' => ['nullable', 'string', \Illuminate\Validation\Rule::unique('dentists')->ignore($this->dentist)],
            'address' => ['nullable', 'string'],
            'price_list' => ['nullable', 'array'],
            'price_list.*' => ['array:price,currency'],
            // Integer to match order items' price rule — a decimal here would
            // auto-fill into the order form and then fail its validation. A
            // dollar price is held in cents, so it is a whole number too.
            'price_list.*.price' => ['required', 'integer', 'min:0'],
            'price_list.*.currency' => ['required', 'in:SYP,USD'],
        ];
    }
}
