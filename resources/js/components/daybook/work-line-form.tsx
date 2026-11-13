import { useMemo, useState } from 'react';
import type { WorkLine } from '@/components/daybook/types';
import DentalChart from '@/components/dental-chart';
import InputError from '@/components/input-error';
import CurrencyToggle from '@/components/money/currency-toggle';
import DollarInput from '@/components/money/decimal-input';
import type { WorkTypesProp } from '@/components/order-form';
import TeethInput from '@/components/teeth-input';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkTypeCombobox from '@/components/work-type-combobox';
import type { WorkTypeOption } from '@/components/work-type-combobox';
import { useDaybookSave } from '@/hooks/use-daybook-save';
import { normalizeArabic } from '@/lib/arabic';
import { formatMoney, formatSyp, rateValue, usdToSyp } from '@/lib/money';
import { quoteFor, rankedWorkTypes } from '@/lib/price-list';
import { cn } from '@/lib/utils';
import type { Dentist } from '@/types';

/** How many of the dentist's types get a one-tap button. */
const QUICK_TYPES = 6;

type Draft = {
    type: string;
    patient_name: string;
    selected_teeth: number[];
    quantity: number;
    /** Lira. Derived from the two fields below on a dollar-quoted line. */
    price: number;
    currency: 'SYP' | 'USD';
    /** US cents. */
    original_amount: number;
    rate: string;
    notes: string;
};

const emptyDraft = (dollarDentist: boolean): Draft => ({
    type: '',
    patient_name: '',
    selected_teeth: [],
    quantity: 1,
    price: 0,
    currency: dollarDentist ? 'USD' : 'SYP',
    original_amount: 0,
    rate: '',
    notes: '',
});

const draftFrom = (line: WorkLine): Draft => ({
    type: line.type,
    patient_name: line.patient_name,
    selected_teeth: line.selected_teeth,
    quantity: line.quantity,
    price: line.price,
    currency: line.currency === 'USD' ? 'USD' : line.item_currency,
    original_amount: line.original_amount ?? 0,
    rate: rateValue(line.rate),
    notes: line.notes ?? '',
});

export const fieldClass = 'h-12 rounded-[10px] text-base';
export const labelClass = 'text-[15px] font-semibold';

/**
 * One line of work: doctor, patient, type, teeth, price. Saved as its own
 * one-item order through the ordinary order action, so the rules and the
 * ledger posting are the order form's own.
 *
 * The parent remounts this (by `key`) to start a new line or to edit one, so
 * the draft is plain initial state rather than something synced from props.
 */
export default function WorkLineForm({
    dentists,
    workTypes,
    todayRate,
    date,
    editing,
    dentistId,
    onDentistChange,
    onSaved,
    onCancel,
}: {
    dentists: Dentist[];
    workTypes: WorkTypesProp;
    todayRate: string | null;
    date: string;
    editing: WorkLine | null;
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
    const [chartOpen, setChartOpen] = useState(false);
    const { save, processing, failed, errors } = useDaybookSave();

    const patch = (fields: Partial<Draft>) =>
        setDraft((prev) => ({ ...prev, ...fields }));

    const workTypeNames = useMemo(
        () => rankedWorkTypes(dentist, workTypes.usage[dentistId] ?? []),
        [dentist, workTypes.usage, dentistId],
    );

    // His priced types (with the price shown), then every other name in use —
    // so a type he has no price for is still picked rather than respelled.
    const typeOptions = useMemo<WorkTypeOption[]>(() => {
        const list = dentist?.price_list ?? {};
        const priced = new Set(workTypeNames.map(normalizeArabic));

        return [
            ...workTypeNames.map((name) => ({
                name,
                price: formatMoney(
                    list[name].price,
                    dollarDentist ? 'USD' : list[name].currency,
                ),
            })),
            ...workTypes.catalog
                .filter((name) => !priced.has(normalizeArabic(name)))
                .map((name) => ({ name })),
        ];
    }, [dentist, workTypeNames, workTypes.catalog, dollarDentist]);

    /**
     * A different doctor means a different price list and maybe a different
     * currency: re-price the typed work type from his list, or — when he has
     * no price for it and the currency changed — clear a figure that would no
     * longer mean what was typed.
     */
    const changeDentist = (id: string) => {
        const next = dentists.find((d) => d.id.toString() === id);
        const nextDollar = next?.currency === 'USD';
        const quote = quoteFor(next, draft.type, todayRate);

        if (quote) {
            patch(quote);
        } else if (nextDollar !== dollarDentist) {
            patch({
                currency: nextDollar ? 'USD' : 'SYP',
                price: 0,
                original_amount: 0,
                rate: '',
            });
        }

        onDentistChange(id);
    };

    const changeType = (type: string) =>
        patch({ type, ...(quoteFor(dentist, type, todayRate) ?? {}) });

    const changeTeeth = (teeth: number[]) =>
        patch({
            selected_teeth: teeth,
            quantity: teeth.length > 0 ? teeth.length : draft.quantity,
        });

    /** A lira dentist's line switched between lira and a dollar quote. */
    const changeCurrency = (currency: 'SYP' | 'USD') =>
        patch(
            currency === 'USD'
                ? {
                      currency,
                      price: 0,
                      original_amount: 0,
                      rate: draft.rate || rateValue(todayRate),
                  }
                : { currency, original_amount: 0, rate: '', price: 0 },
        );

    const changeDollars = (cents: number) => {
        const rate = parseFloat(draft.rate);
        patch({
            original_amount: cents,
            price:
                !dollarDentist && Number.isFinite(rate)
                    ? usdToSyp(cents / 100, rate)
                    : 0,
        });
    };

    const changeRate = (text: string) => {
        const rate = parseFloat(text);
        patch({
            rate: text,
            price: Number.isFinite(rate)
                ? usdToSyp(draft.original_amount / 100, rate)
                : 0,
        });
    };

    const inDollars = dollarDentist || draft.currency === 'USD';
    const lineTotal =
        draft.quantity * (dollarDentist ? draft.original_amount : draft.price);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const common = {
            type: draft.type,
            patient_name: draft.patient_name,
            quantity: draft.quantity,
            notes: draft.notes,
            date,
            selected_teeth: draft.selected_teeth,
        };

        // A dollar dentist's line is native dollars: `price` and `rate` are
        // prohibited server-side, so they are left out rather than zeroed.
        const item = dollarDentist
            ? {
                  ...common,
                  currency: 'USD',
                  original_amount: draft.original_amount,
              }
            : {
                  ...common,
                  currency: draft.currency,
                  price: draft.price,
                  original_amount: draft.original_amount,
                  rate: draft.rate,
              };

        const payload = {
            dentist_id: dentistId,
            status: editing?.status ?? 'pending',
            notes: '',
            items: [item],
        };

        if (editing) {
            save(
                'put',
                `/daybook/orders/${editing.order_id}`,
                payload,
                onSaved,
            );
        } else {
            save('post', '/daybook/orders', payload, () => {
                // The doctor stays: a sheet is usually several lines for him.
                setDraft(emptyDraft(dollarDentist));
                setChartOpen(false);
                onSaved();
            });
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="grid gap-2">
                <Label htmlFor="work-dentist" className={labelClass}>
                    الطبيب
                </Label>
                <Combobox
                    id="work-dentist"
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

            <div className="grid gap-2">
                <Label htmlFor="work-patient" className={labelClass}>
                    المريض{' '}
                    <span className="text-xs font-normal text-muted-foreground">
                        (اختياري)
                    </span>
                </Label>
                <Input
                    id="work-patient"
                    value={draft.patient_name}
                    onChange={(e) => patch({ patient_name: e.target.value })}
                    className={fieldClass}
                    autoComplete="off"
                />
                <InputError message={errors['items.0.patient_name']} />
            </div>

            <div className="grid gap-2">
                <Label className={labelClass}>نوع العمل</Label>
                {workTypeNames.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {workTypeNames.slice(0, QUICK_TYPES).map((name) => (
                            <button
                                key={name}
                                type="button"
                                onClick={() => changeType(name)}
                                aria-pressed={draft.type === name}
                                className={cn(
                                    'min-h-12 rounded-[10px] border px-4 text-[15px] font-medium transition-colors',
                                    draft.type === name
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-card hover:border-primary',
                                )}
                            >
                                {name}
                            </button>
                        ))}
                    </div>
                )}
                <div className="[&_input]:h-12 [&_input]:rounded-[10px] [&_input]:text-base">
                    <WorkTypeCombobox
                        value={draft.type}
                        onChange={changeType}
                        options={typeOptions}
                        placeholder="اختر النوع أو اكتب..."
                    />
                </div>
                <InputError message={errors['items.0.type']} />
            </div>

            <div className="grid gap-2">
                <div className="flex items-center justify-between gap-2">
                    <Label className={labelClass}>أرقام الأسنان</Label>
                    <button
                        type="button"
                        onClick={() => setChartOpen((open) => !open)}
                        className="min-h-10 rounded-md px-2 text-sm font-medium text-primary"
                    >
                        {chartOpen ? 'إخفاء الرسم' : 'اختر من الرسم'}
                    </button>
                </div>
                <TeethInput
                    teeth={draft.selected_teeth}
                    onChange={changeTeeth}
                    className={fieldClass}
                />
                {chartOpen && (
                    <div className="overflow-x-auto rounded-[10px] border bg-card p-2">
                        <DentalChart
                            selectedTeeth={draft.selected_teeth}
                            onSelectionChange={changeTeeth}
                        />
                    </div>
                )}
                <InputError message={errors['items.0.selected_teeth']} />
            </div>

            <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-3">
                <div className="grid gap-2">
                    <Label htmlFor="work-quantity" className={labelClass}>
                        الكمية
                    </Label>
                    <Input
                        id="work-quantity"
                        type="number"
                        inputMode="numeric"
                        min="1"
                        value={draft.quantity || ''}
                        onChange={(e) =>
                            patch({ quantity: parseInt(e.target.value) || 0 })
                        }
                        readOnly={draft.selected_teeth.length > 0}
                        className={cn(
                            fieldClass,
                            draft.selected_teeth.length > 0 && 'bg-muted',
                        )}
                    />
                    <InputError message={errors['items.0.quantity']} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-2">
                        <Label htmlFor="work-price" className={labelClass}>
                            سعر الواحدة {inDollars ? '($)' : '(ل.س)'}
                        </Label>
                        {!dollarDentist && (
                            <CurrencyToggle
                                value={draft.currency}
                                onChange={changeCurrency}
                            />
                        )}
                    </div>
                    {inDollars ? (
                        <DollarInput
                            id="work-price"
                            cents={draft.original_amount}
                            onChange={changeDollars}
                            dir="ltr"
                            className={fieldClass}
                        />
                    ) : (
                        <Input
                            id="work-price"
                            type="number"
                            inputMode="numeric"
                            min="0"
                            value={draft.price || ''}
                            onChange={(e) =>
                                patch({ price: parseInt(e.target.value) || 0 })
                            }
                            className={fieldClass}
                        />
                    )}
                    <InputError
                        message={
                            errors['items.0.price'] ??
                            errors['items.0.original_amount']
                        }
                    />
                </div>
            </div>

            {/* A lira dentist quoted in dollars: the rate decides the lira. */}
            {!dollarDentist && draft.currency === 'USD' && (
                <div className="grid gap-2">
                    <Label htmlFor="work-rate" className={labelClass}>
                        سعر الصرف
                    </Label>
                    <Input
                        id="work-rate"
                        type="number"
                        inputMode="decimal"
                        step="0.000001"
                        min="0.000001"
                        dir="ltr"
                        value={draft.rate}
                        onChange={(e) => changeRate(e.target.value)}
                        className={fieldClass}
                    />
                    <p className="text-sm text-muted-foreground">
                        يُسجَّل بالليرة:{' '}
                        <span className="font-semibold text-foreground tabular-nums">
                            {formatSyp(draft.price)}
                        </span>{' '}
                        للواحدة
                    </p>
                    <InputError message={errors['items.0.rate']} />
                </div>
            )}

            <div className="flex items-center justify-between rounded-[10px] bg-muted px-4 py-3">
                <span className="text-[15px] font-semibold">المجموع</span>
                <span
                    className="text-lg font-bold tabular-nums"
                    dir={dollarDentist ? 'ltr' : undefined}
                >
                    {formatMoney(lineTotal, dollarDentist ? 'USD' : 'SYP')}
                </span>
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
                    disabled={processing || !dentistId || !draft.type.trim()}
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
