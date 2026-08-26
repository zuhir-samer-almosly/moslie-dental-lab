<?php

namespace App\Concerns;

/**
 * Validation and payload shaping for a money field that may arrive in a
 * currency other than the lira.
 *
 * The form collects dollars the way people write them ("100.50"); the column
 * holds cents, so the stored value stays an exact integer and never drifts.
 * The lira figure itself is not submitted for a foreign payment — the model
 * derives it from the amount and the rate, so there is one place that converts.
 */
trait MoneyValidationRules
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function moneyRules(string $field = 'amount'): array
    {
        return [
            'currency' => ['nullable', 'in:SYP,USD'],
            // Absent currency means lira, so a form that never heard of
            // currency keeps submitting a bare amount and still validates.
            $field => ['required_unless:currency,USD', 'nullable', 'integer', 'min:1'],
            'original_amount' => ['required_if:currency,USD', 'nullable', 'numeric', 'min:0.01'],
            'rate' => ['required_if:currency,USD', 'nullable', 'numeric', 'min:0.000001'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function moneyPayload(array $data, string $field = 'amount'): array
    {
        if (($data['currency'] ?? 'SYP') !== 'USD') {
            $data['currency'] = 'SYP';
            $data['original_amount'] = null;
            $data['rate'] = null;

            return $data;
        }

        $data['original_amount'] = (int) round(((float) $data['original_amount']) * 100);

        // Drop any lira figure the form sent: for foreign money the model is
        // the only thing allowed to decide what the lira column holds.
        unset($data[$field]);

        return $data;
    }
}
