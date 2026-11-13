import { useState } from 'react';
import type { PaymentLine } from '@/components/daybook/types';
import { fieldClass, labelClass } from '@/components/daybook/work-line-form';
import InputError from '@/components/input-error';
import CurrencyAmountField, {
    type CurrencyAmount,
} from '@/components/money/currency-amount-field';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDaybookSave } from '@/hooks/use-daybook-save';
import { rateValue } from '@/lib/money';
import type { Dentist } from '@/types';

type Draft = CurrencyAmount & { notes: string };

const emptyDraft = (dollarDentist: boolean): Draft => ({
    currency: dollarDentist ? 'USD' : 'SYP',
    amount: '',
    original_amount: '',
    rate: '',
    notes: '',
});

const draftFrom = (line: PaymentLine): Draft => ({
    currency: line.payment_currency,
    amount: line.amount.toString(),
    // Stored in cents; typed in dollars, the way it was handed over.
    original_amount:
        line.original_amount === null
            ? ''
            : (line.original_amount / 100).toFixed(2),
    rate: rateValue(line.rate),
    notes: line.notes ?? '',
});

/**
 * One payment received: doctor, amount, note. Saved through the ordinary
 * payment action. Remounted by `key` to start a new line or edit one.
 */
export default function PaymentLineForm({
    dentists,
    todayRate,
    date,
    editing,
    dentistId,
    onDentistChange,
    onSaved,
    onCancel,
}: {
    dentists: Dentist[];
    todayRate: string | null;
    date: string;
    editing: PaymentLine | null;
    dentistId: string;
    onDentistChange: (id: string) => void;
    onSaved: () => void;
    onCancel: () => void;
}) {
    const dentist = dentists.find((d) => d.id.toString() === dentistId);
    const dollarDentist = dentist?.currency === 'USD';

    const [draft, setDraft] = useState<Draft>(() =>
        editing ? draftFrom(editing) : emptyDraft(dollarDentist),
    );
    const { save, processing, failed, errors } = useDaybookSave();

    /**
     * A figure typed for one currency must not survive under a doctor billed
     * in the other; between two doctors of the same currency it stays.
     */
    const changeDentist = (id: string) => {
        const next = dentists.find((d) => d.id.toString() === id);
        const nextDollar = next?.currency === 'USD';

        if (nextDollar !== dollarDentist) {
            setDraft((prev) => ({
                ...emptyDraft(nextDollar),
                notes: prev.notes,
            }));
        }

        onDentistChange(id);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // A dollar dentist's payment is native dollars: `amount` and `rate`
        // are prohibited server-side, so they are left out, not zeroed.
        const payload = dollarDentist
            ? {
                  dentist_id: dentistId,
                  payment_date: date,
                  currency: 'USD',
                  original_amount: draft.original_amount,
                  notes: draft.notes,
              }
            : {
                  dentist_id: dentistId,
                  payment_date: date,
                  currency: draft.currency,
                  amount: draft.amount,
                  original_amount: draft.original_amount,
                  rate: draft.rate,
                  notes: draft.notes,
              };

        if (editing) {
            save(
                'put',
                `/daybook/payments/${editing.payment_id}`,
                payload,
                onSaved,
            );
        } else {
            save('post', '/daybook/payments', payload, () => {
                setDraft(emptyDraft(dollarDentist));
                onSaved();
            });
        }
    };

    const hasAmount =
        dollarDentist || draft.currency === 'USD'
            ? draft.original_amount !== ''
            : draft.amount !== '';

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-4 [&_input:not([type=radio])]:h-12 [&_input:not([type=radio])]:text-base"
        >
            <div className="grid gap-2">
                <Label htmlFor="payment-dentist" className={labelClass}>
                    الطبيب
                </Label>
                <Combobox
                    id="payment-dentist"
                    value={dentistId}
                    onChange={(value) => changeDentist(value ?? '')}
                    options={dentists.map((d) => ({
                        value: d.id.toString(),
                        label: d.name,
                    }))}
                    placeholder="اختر الطبيب"
                    className={fieldClass}
                    aria-invalid={!!errors.dentist_id}
                />
                <InputError message={errors.dentist_id} />
            </div>

            <CurrencyAmountField
                value={draft}
                onChange={(fields) =>
                    setDraft((prev) => ({ ...prev, ...fields }))
                }
                errors={errors}
                todayRate={todayRate}
                native={dollarDentist}
                idPrefix="daybook-payment-"
            />

            <div className="grid gap-2">
                <Label htmlFor="payment-notes" className={labelClass}>
                    ملاحظات{' '}
                    <span className="text-xs font-normal text-muted-foreground">
                        (اختياري)
                    </span>
                </Label>
                <Input
                    id="payment-notes"
                    value={draft.notes}
                    onChange={(e) =>
                        setDraft((prev) => ({ ...prev, notes: e.target.value }))
                    }
                    placeholder="نقداً، حوالة، من سلّمها..."
                    autoComplete="off"
                />
                <InputError message={errors.notes} />
            </div>

            {failed && (
                <p
                    role="alert"
                    className="rounded-[10px] border border-destructive/40 bg-destructive/10 px-4 py-3 text-[15px] font-medium text-destructive"
                >
                    لم يُحفظ — الاتصال ضعيف. اضغط «حفظ» مرة أخرى.
                </p>
            )}

            <div className="flex gap-3">
                <Button
                    type="submit"
                    disabled={processing || !dentistId || !hasAmount}
                    className="h-14 flex-1 rounded-[10px] text-lg font-bold"
                >
                    {processing
                        ? 'جارٍ الحفظ...'
                        : editing
                          ? 'حفظ التعديل'
                          : 'حفظ'}
                </Button>
                {editing && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={processing}
                        className="h-14 rounded-[10px] px-6 text-base"
                    >
                        إلغاء
                    </Button>
                )}
            </div>
        </form>
    );
}
