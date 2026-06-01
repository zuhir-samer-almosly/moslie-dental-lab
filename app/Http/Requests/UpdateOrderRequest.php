<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'dentist_id' => ['required', 'exists:dentists,id'],
            'due_date' => ['required', 'date'],
            'status' => ['required', 'in:pending,completed,cancelled,recieved'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.patient_name' => ['nullable', 'string', 'max:255'],
            'items.*.selected_teeth' => ['nullable', 'array'],
            'items.*.selected_teeth.*' => ['integer', 'min:1', 'max:32'],
        ];
    }
}
