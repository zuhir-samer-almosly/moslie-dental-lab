<?php

namespace App\Concerns;

trait OrderValidationRules
{
    /**
     * Validation rules shared by storing and updating an order.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function orderRules(): array
    {
        // FDI tooth-numbering: quadrants 1-4, teeth 1-8.
        $teeth = implode(',', [
            11, 12, 13, 14, 15, 16, 17, 18,
            21, 22, 23, 24, 25, 26, 27, 28,
            31, 32, 33, 34, 35, 36, 37, 38,
            41, 42, 43, 44, 45, 46, 47, 48,
        ]);

        return [
            'dentist_id' => ['required', 'exists:dentists,id'],
            'status' => ['required', 'in:pending,completed,cancelled,recieved'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.date' => ['required', 'date'],
            'items.*.patient_name' => ['nullable', 'string', 'max:255'],
            'items.*.selected_teeth' => ['nullable', 'array'],
            'items.*.selected_teeth.*' => ['integer', 'in:'.$teeth],
        ];
    }
}
