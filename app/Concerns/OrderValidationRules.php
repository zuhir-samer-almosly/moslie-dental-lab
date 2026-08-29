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

        // A dollar dentist's line is NATIVE dollars: cents, and no rate at
        // all — his money is never converted. A lira dentist's dollar line is
        // a quote and still needs the rate it was quoted at.
        $dollarDentist = $this->dentistIsDollar();

        return [
            'dentist_id' => ['required', 'exists:dentists,id'],
            'status' => ['required', 'in:pending,completed,cancelled,recieved'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.currency' => ['nullable', 'in:SYP,USD'],
            // Absent currency means lira, so an item that never heard of
            // currency keeps submitting a bare price and still validates.
            'items.*.price' => $dollarDentist
                ? ['prohibited']
                : ['required_unless:items.*.currency,USD', 'nullable', 'integer', 'min:0'],
            // Dollar quotes are held in cents, matching the price list.
            //
            // `exclude_unless` rather than `required_if`: a lira line still
            // carries these keys, zeroed, because the form keeps one shape for
            // every row. They describe nothing on such a line, so they are
            // dropped from the data outright instead of being held to rules
            // meant for a dollar line — a zero placeholder must not fail min:1
            // and take the whole order down with it.
            'items.*.original_amount' => $dollarDentist
                ? ['required', 'integer', 'min:1']
                : ['exclude_unless:items.*.currency,USD', 'required', 'integer', 'min:1'],
            'items.*.rate' => $dollarDentist
                ? ['prohibited']
                : ['exclude_unless:items.*.currency,USD', 'required', 'numeric', 'min:0.000001'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.date' => ['required', 'date'],
            'items.*.patient_name' => ['nullable', 'string', 'max:255'],
            'items.*.selected_teeth' => ['nullable', 'array'],
            'items.*.selected_teeth.*' => ['integer', 'in:'.$teeth],
        ];
    }

    /**
     * Whether the dentist this order is for is billed in dollars. Read from
     * the request rather than the route, because both storing and updating
     * submit `dentist_id`.
     */
    protected function dentistIsDollar(): bool
    {
        $dentist = \App\Models\Dentist::find($this->input('dentist_id'));

        return (bool) $dentist?->isDollar();
    }
}
