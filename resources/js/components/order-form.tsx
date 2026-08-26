import { useForm } from '@inertiajs/react';
import { Check, Info, Plus, Trash2, Users } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import DentalChart from '@/components/dental-chart';
import DentistsManagerDialog from '@/components/dentists/dentists-manager-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { DateInput } from '@/components/ui/date-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import WorkTypeCombobox from '@/components/work-type-combobox';
import {
    formatRate,
    formatSyp,
    formatUsd,
    rateValue,
    usdToSyp,
} from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Dentist, Order } from '@/types';
import { ORDER_STATUSES } from '@/types';

export type OrderItemForm = {
    type: string;
    patient_name: string;
    quantity: number;
    /** Always lira. Derived from the two fields below when the quote is in dollars. */
    price: number;
    notes: string;
    date: string;
    selected_teeth: number[];
    currency: 'SYP' | 'USD';
    /** US cents, when the dentist's price list quotes this work type in dollars. */
    original_amount: number;
    rate: string;
};

export type OrderFormValues = {
    dentist_id: string;
    status: Order['status'];
    notes: string;
    items: OrderItemForm[];
};

export const today = () => new Date().toISOString().slice(0, 10);

/**
 * Shared create/edit form for an order and its line items. The parent decides
 * the initial values and where to submit; everything else (item add/remove,
 * per-dentist price auto-fill, teeth-driven quantity, running total) lives here
 * so create and edit stay in lockstep.
 */
export default function OrderForm({
    dentists,
    initialValues,
    method,
    action,
    submitLabel,
    todayRate,
}: {
    dentists: Dentist[];
    initialValues: OrderFormValues;
    method: 'post' | 'put';
    action: string;
    submitLabel: string;
    todayRate: string | null;
}) {
    const form = useForm<OrderFormValues>(initialValues);
    const { data, setData, processing, errors } = form;

    const getSelectedDentist = useCallback(() => {
        return dentists.find((d) => d.id.toString() === data.dentist_id);
    }, [dentists, data.dentist_id]);

    // Available work types come from the selected dentist's own price list.
    const workTypeNames = data.dentist_id
        ? Object.keys(getSelectedDentist()?.price_list ?? {})
        : [];

    /**
     * The price fields a work type implies, from the dentist's own list.
     *
     * A dollar quote is converted here, at the rate recorded for today, and
     * the item carries lira from then on — the quote and its rate ride along
     * so the invoice can show what the price came from. If no rate has been
     * recorded yet the rate is left empty for the user to type; the price is
     * then 0 until they do, rather than silently pricing the work at nothing.
     */
    const quoteFields = useCallback(
        (
            type: string,
        ): Pick<
            OrderItemForm,
            'price' | 'currency' | 'original_amount' | 'rate'
        > | null => {
            const entry = getSelectedDentist()?.price_list?.[type];
            if (!entry) {
                return null;
            }

            if (entry.currency === 'USD') {
                const rate = rateValue(todayRate);
                const parsed = parseFloat(rate);
                return {
                    currency: 'USD',
                    original_amount: entry.price,
                    rate,
                    price: Number.isFinite(parsed)
                        ? usdToSyp(entry.price / 100, parsed)
                        : 0,
                };
            }

            return {
                currency: 'SYP',
                original_amount: 0,
                rate: '',
                // Round: legacy price lists may hold decimals, but the server
                // only accepts integer item prices.
                price: Math.round(entry.price),
            };
        },
        [getSelectedDentist, todayRate],
    );

    const handleDentistChange = (value: string) => {
        setData((prev) => {
            const dentist = dentists.find((d) => d.id.toString() === value);
            const updatedItems = prev.items.map((item) => {
                const entry = dentist?.price_list?.[item.type];
                if (!entry) {
                    return item;
                }
                if (entry.currency === 'USD') {
                    const rate = item.rate || rateValue(todayRate);
                    const parsed = parseFloat(rate);
                    return {
                        ...item,
                        currency: 'USD' as const,
                        original_amount: entry.price,
                        rate,
                        price: Number.isFinite(parsed)
                            ? usdToSyp(entry.price / 100, parsed)
                            : 0,
                    };
                }
                return {
                    ...item,
                    currency: 'SYP' as const,
                    original_amount: 0,
                    rate: '',
                    price: Math.round(entry.price),
                };
            });
            return { ...prev, dentist_id: value, items: updatedItems };
        });
    };

    // Server-side errors for item fields arrive keyed as `items.<i>.<field>`,
    // which the useForm error type doesn't know about.
    const itemError = (index: number, field: string) =>
        (errors as Record<string, string | undefined>)[
            `items.${index}.${field}`
        ];

    // The add button is sticky, so it can be clicked from anywhere in a long
    // list — but the new item is always appended at the end. Scroll to it so
    // the click never looks like it did nothing.
    const itemsContainerRef = useRef<HTMLDivElement>(null);
    const scrollToLastItem = useRef(false);

    useEffect(() => {
        if (!scrollToLastItem.current) {
            return;
        }
        scrollToLastItem.current = false;
        itemsContainerRef.current?.lastElementChild?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }, [data.items.length]);

    // An item's price is frozen once the item is added. Editing the dentist's
    // price list — from the dialog or anywhere else — deliberately does NOT
    // rewrite items already in the draft: the price you see on a line is the
    // price you agreed, and only you change it, by typing in that line's own
    // field. New prices apply to the items you add next (via getDentistPrice
    // in addItem).

    // If the selected dentist is deleted from the dialog, drop the dangling
    // selection rather than submitting an id the server will reject. Driven by
    // the refreshed list, not by the delete callback, so a delete the server
    // refused (dentist has orders or payments) correctly changes nothing.
    useEffect(() => {
        if (
            data.dentist_id &&
            !dentists.some((d) => d.id.toString() === data.dentist_id)
        ) {
            setData('dentist_id', '');
        }
    }, [dentists, data.dentist_id, setData]);

    const addItem = () => {
        const defaultType = workTypeNames[0] || '';
        const quote = quoteFields(defaultType);
        scrollToLastItem.current = true;
        setData('items', [
            ...data.items,
            {
                type: defaultType,
                patient_name: '',
                quantity: 1,
                price: quote?.price ?? 0,
                notes: '',
                date: today(),
                selected_teeth: [],
                currency: quote?.currency ?? 'SYP',
                original_amount: quote?.original_amount ?? 0,
                rate: quote?.rate ?? '',
            },
        ]);
    };

    const removeItem = (index: number) => {
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );
    };

    const updateItem = (
        index: number,
        field: keyof OrderItemForm,
        value: string | number,
    ) => {
        const newItems = [...data.items];
        newItems[index] = { ...newItems[index], [field]: value };

        // Auto-fill price when work type changes
        if (field === 'type') {
            const quote = quoteFields(value as string);
            if (quote !== null) {
                newItems[index] = { ...newItems[index], ...quote };
            }
        }

        // Typing a lira price by hand overrides the dentist's dollar quote, so
        // the item stops claiming a conversion it no longer reflects.
        if (field === 'price') {
            newItems[index] = {
                ...newItems[index],
                currency: 'SYP',
                original_amount: 0,
                rate: '',
            };
        }

        setData('items', newItems);
    };

    /** Re-price a dollar-quoted item when its rate is edited. */
    const updateItemRate = (index: number, rate: string) => {
        const newItems = [...data.items];
        const parsed = parseFloat(rate);
        newItems[index] = {
            ...newItems[index],
            rate,
            price: Number.isFinite(parsed)
                ? usdToSyp(newItems[index].original_amount / 100, parsed)
                : 0,
        };
        setData('items', newItems);
    };

    const [dentistsDialogOpen, setDentistsDialogOpen] = useState(false);

    const total = data.items.reduce(
        (sum, item) => sum + item.quantity * item.price,
        0,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (method === 'put') {
            form.put(action);
        } else {
            form.post(action);
        }
    };

    const selectedDentist = getSelectedDentist();

    const labelClass = 'text-[14px] font-semibold text-[#435955]';
    const fieldClass = 'h-11 rounded-[10px]';

    return (
        <>
            <form onSubmit={handleSubmit} className="max-w-[920px] space-y-6">
                {/* Order info */}
                <section className="flex flex-col gap-[18px] rounded-2xl border bg-card p-6">
                    <h2 className="text-base font-bold text-foreground">
                        معلومات الطلب
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="dentist_id" className={labelClass}>
                                الطبيب
                            </Label>
                            <div className="flex items-center gap-2">
                                <div className="flex-1">
                                    <Combobox
                                        id="dentist_id"
                                        value={data.dentist_id}
                                        onChange={(value) =>
                                            handleDentistChange(value ?? '')
                                        }
                                        options={dentists.map((dentist) => ({
                                            value: dentist.id.toString(),
                                            label: dentist.name,
                                        }))}
                                        placeholder="اختر الطبيب"
                                        className={fieldClass}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    title="إدارة الأطباء والأسعار"
                                    onClick={() => setDentistsDialogOpen(true)}
                                    className="size-11 shrink-0 rounded-[10px] p-0"
                                >
                                    <Users className="size-4" />
                                </Button>
                            </div>
                            <InputError message={errors.dentist_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status" className={labelClass}>
                                الحالة
                            </Label>
                            <Combobox
                                id="status"
                                value={data.status}
                                onChange={(value) => {
                                    if (value !== null) {
                                        setData(
                                            'status',
                                            value as typeof data.status,
                                        );
                                    }
                                }}
                                options={Object.entries(ORDER_STATUSES).map(
                                    ([key, label]) => ({
                                        value: key,
                                        label,
                                    }),
                                )}
                                className={fieldClass}
                            />
                            <InputError message={errors.status} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes" className={labelClass}>
                            ملاحظات
                        </Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={2}
                            placeholder="ملاحظات عامة على الطلب..."
                            className="rounded-[10px]"
                        />
                        <InputError message={errors.notes} />
                    </div>

                    {selectedDentist?.price_list &&
                        Object.keys(selectedDentist.price_list).length > 0 && (
                            <div className="flex items-center gap-2 rounded-[10px] bg-secondary px-3.5 py-2.5 text-[13px] text-primary">
                                <Info className="size-4 shrink-0" />
                                <span>
                                    يتم ملء الأسعار تلقائياً حسب قائمة أسعار
                                    الطبيب{' '}
                                    <span className="font-semibold">
                                        {selectedDentist.name}
                                    </span>
                                </span>
                            </div>
                        )}
                </section>

                {/* Items */}
                <section className="space-y-4">
                    {/* Sticky so the add button stays reachable without scrolling
                    back to the top of a long item list. */}
                    <div className="sticky top-0 z-20 flex items-center justify-between bg-background py-3">
                        <h2 className="text-base font-bold text-foreground">
                            العناصر
                            {data.items.length > 0 && (
                                <span className="mr-1.5 text-sm font-medium text-muted-foreground tabular-nums">
                                    ({data.items.length})
                                </span>
                            )}
                        </h2>
                        <Button
                            type="button"
                            onClick={addItem}
                            variant="outline"
                            className="gap-2 rounded-[10px] border-primary text-primary hover:bg-secondary hover:text-primary"
                        >
                            <Plus className="size-4" />
                            إضافة عنصر
                        </Button>
                    </div>

                    {data.items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            لا توجد عناصر. قم بإضافة عنصر واحد على الأقل.
                        </p>
                    ) : (
                        <div ref={itemsContainerRef} className="space-y-4">
                            {data.items.map((item, index) => (
                                <div
                                    key={index}
                                    // scroll-mt clears the sticky bar when a newly
                                    // added item is scrolled into view.
                                    className="flex scroll-mt-16 flex-col gap-[18px] rounded-2xl border bg-card p-6"
                                >
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-[15px] font-bold text-primary">
                                            عنصر {index + 1}
                                        </h3>
                                        <button
                                            type="button"
                                            title="حذف العنصر"
                                            onClick={() => removeItem(index)}
                                            className="flex size-[34px] items-center justify-center rounded-[9px] border border-[#F5D5DB] bg-card text-[#BE123C] transition-colors hover:bg-[#FDECEE]"
                                        >
                                            <Trash2 className="size-[15px]" />
                                        </button>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label className={labelClass}>
                                                النوع
                                            </Label>
                                            <WorkTypeCombobox
                                                value={item.type}
                                                onChange={(v) =>
                                                    updateItem(index, 'type', v)
                                                }
                                                options={workTypeNames}
                                                placeholder="اختر النوع أو اكتب..."
                                            />
                                            <InputError
                                                message={itemError(
                                                    index,
                                                    'type',
                                                )}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className={labelClass}>
                                                اسم المريض
                                            </Label>
                                            <Input
                                                value={item.patient_name}
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'patient_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="أدخل اسم المريض..."
                                                className={fieldClass}
                                            />
                                            <InputError
                                                message={itemError(
                                                    index,
                                                    'patient_name',
                                                )}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className={labelClass}>
                                                التاريخ
                                            </Label>
                                            <DateInput
                                                value={item.date}
                                                onChange={(value) =>
                                                    updateItem(
                                                        index,
                                                        'date',
                                                        value,
                                                    )
                                                }
                                                required
                                                className={fieldClass}
                                            />
                                            <InputError
                                                message={itemError(
                                                    index,
                                                    'date',
                                                )}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className={labelClass}>
                                                الكمية{' '}
                                                {item.selected_teeth.length >
                                                    0 && (
                                                    <span className="text-xs font-normal text-muted-foreground">
                                                        (حسب الأسنان المختارة)
                                                    </span>
                                                )}
                                            </Label>
                                            <Input
                                                type="number"
                                                min="1"
                                                value={item.quantity || ''}
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'quantity',
                                                        // NaN guard: an emptied
                                                        // field must not poison
                                                        // the totals.
                                                        parseInt(
                                                            e.target.value,
                                                        ) || 0,
                                                    )
                                                }
                                                readOnly={
                                                    item.selected_teeth.length >
                                                    0
                                                }
                                                className={cn(
                                                    fieldClass,
                                                    item.selected_teeth.length >
                                                        0 && 'bg-muted',
                                                )}
                                            />
                                            <InputError
                                                message={itemError(
                                                    index,
                                                    'quantity',
                                                )}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className={labelClass}>
                                                {item.currency === 'USD'
                                                    ? 'سعر الصرف'
                                                    : 'السعر'}
                                            </Label>
                                            {item.currency === 'USD' ? (
                                                <>
                                                    <Input
                                                        type="number"
                                                        min="0.000001"
                                                        step="0.000001"
                                                        value={item.rate}
                                                        onChange={(e) =>
                                                            updateItemRate(
                                                                index,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={fieldClass}
                                                    />
                                                    <p
                                                        dir="ltr"
                                                        className="text-end text-xs text-muted-foreground tabular-nums"
                                                    >
                                                        {item.rate
                                                            ? `$${formatUsd(item.original_amount)} × ${formatRate(item.rate)} = ${formatSyp(item.price)} ل.س`
                                                            : `$${formatUsd(item.original_amount)} — أدخل سعر الصرف`}
                                                    </p>
                                                    <InputError
                                                        message={itemError(
                                                            index,
                                                            'rate',
                                                        )}
                                                    />
                                                </>
                                            ) : (
                                                <>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        value={item.price || ''}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                index,
                                                                'price',
                                                                parseInt(
                                                                    e.target
                                                                        .value,
                                                                ) || 0,
                                                            )
                                                        }
                                                        className={fieldClass}
                                                    />
                                                    <InputError
                                                        message={itemError(
                                                            index,
                                                            'price',
                                                        )}
                                                    />
                                                </>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className={labelClass}>
                                                ملاحظات
                                            </Label>
                                            <Input
                                                value={item.notes}
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'notes',
                                                        e.target.value,
                                                    )
                                                }
                                                className={fieldClass}
                                            />
                                        </div>
                                    </div>

                                    <DentalChart
                                        selectedTeeth={item.selected_teeth}
                                        onSelectionChange={(teeth) => {
                                            const newItems = [...data.items];
                                            newItems[index] = {
                                                ...newItems[index],
                                                selected_teeth: teeth,
                                                quantity:
                                                    teeth.length > 0
                                                        ? teeth.length
                                                        : newItems[index]
                                                              .quantity,
                                            };
                                            setData('items', newItems);
                                        }}
                                    />

                                    <div className="flex justify-end text-sm text-[#435955]">
                                        المجموع الفرعي:{' '}
                                        <span className="mr-1.5 font-bold text-foreground tabular-nums">
                                            {(
                                                item.quantity * item.price
                                            ).toLocaleString('en-US')}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    <InputError message={errors.items} />
                </section>

                {/* Total + submit */}
                <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-card px-6 py-5">
                    <span className="text-[17px] font-bold text-foreground">
                        المجموع الكلي:{' '}
                        <span className="text-primary tabular-nums">
                            {total.toLocaleString('en-US')}
                        </span>{' '}
                        <span className="text-[13px] font-medium text-muted-foreground">
                            ليرة
                        </span>
                    </span>
                    <Button
                        type="submit"
                        size="lg"
                        disabled={processing || data.items.length === 0}
                        className="gap-2 rounded-xl px-7 text-[15px] font-semibold shadow-[0_2px_6px_rgba(15,118,110,0.25)]"
                    >
                        <Check className="size-[17px]" />
                        {submitLabel}
                    </Button>
                </section>
            </form>

            {/* Deliberately a SIBLING of the form, not a child. Radix portals
                the dialog's DOM out to <body>, but React events still bubble
                through the React tree — so a dialog rendered inside <form>
                makes its own submit button also submit the order, silently
                saving a half-typed order and navigating away. */}
            <DentistsManagerDialog
                open={dentistsDialogOpen}
                onOpenChange={setDentistsDialogOpen}
                dentists={dentists}
            />
        </>
    );
}
