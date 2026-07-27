<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDentistRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'unique:dentists,phone'],
            'address' => ['nullable', 'string'],
            'price_list' => ['nullable', 'array'],
            // Integer to match order items' price rule — a decimal here would
            // auto-fill into the order form and then fail its validation.
            'price_list.*' => ['integer', 'min:0'],
        ];
    }
}
