<?php

namespace App\Http\Requests;

use App\Concerns\MoneyValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Several payments from one dentist entered in a single sitting — four
 * cheques handed over the same day, say. Each row becomes its own payment,
 * held to exactly the rules a lone payment is.
 */
class StoreDentistPaymentsRequest extends FormRequest
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
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_date' => ['required', 'date'],
            'payments.*.notes' => ['nullable', 'string'],
            ...$this->moneyRules('amount', $this->dentistIsDollar(), 'payments.*.'),
        ];
    }

    /**
     * Row fields named the way the form labels them, so an error reads
     * "المبلغ مطلوب" rather than quoting `payments.1.amount` at the user.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payments' => 'الدفعات',
            'payments.*.amount' => 'المبلغ',
            'payments.*.original_amount' => 'المبلغ بالدولار',
            'payments.*.rate' => 'سعر الصرف',
            'payments.*.currency' => 'العملة',
            'payments.*.payment_date' => 'التاريخ',
            'payments.*.notes' => 'الملاحظات',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // The stock required_unless text spells out the currency rule
            // ("ما لم يكن العملة ضمن USD"); on a lira row it is just required.
            'payments.*.amount.required_unless' => 'حقل المبلغ مطلوب.',
        ];
    }

    /**
     * One model-ready payload per row, each carrying the dentist and with its
     * dollar amount converted to the cents the column holds.
     *
     * @return list<array<string, mixed>>
     */
    public function payloads(): array
    {
        $validated = $this->validated();
        $native = $this->dentistIsDollar();

        return array_map(
            fn (array $row) => [
                'dentist_id' => $validated['dentist_id'],
                ...$this->moneyPayload($row, 'amount', $native),
            ],
            array_values($validated['payments']),
        );
    }

    /**
     * Whether the dentist being paid is billed in dollars. Guarded with
     * `is_numeric` for the same reason as StoreDentistPaymentRequest: a
     * malformed id must reach the `exists` rule, not throw first.
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
