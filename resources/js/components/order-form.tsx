import { useForm } from '@inertiajs/react';
import { Check, Info, Plus, Trash2 } from 'lucide-react';
import { useCallback } from 'react';
import DentalChart from '@/components/dental-chart';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DateInput } from '@/components/ui/date-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import WorkTypeCombobox from '@/components/work-type-combobox';
import { cn } from '@/lib/utils';
import type { Dentist, Order } from '@/types';
import { ORDER_STATUSES } from '@/types';

export type OrderItemForm = {
    type: string;
    patient_name: string;
    quantity: number;
    price: number;
    notes: string;
    date: string;
    selected_teeth: number[];
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
}: {
    dentists: Dentist[];
    initialValues: OrderFormValues;
    method: 'post' | 'put';
    action: string;
    submitLabel: string;
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

    const getDentistPrice = useCallback(
        (type: string): number | null => {
            const dentist = getSelectedDentist();
            if (dentist?.price_list && type in dentist.price_list) {
                // Round: legacy price lists may hold decimals, but the server
                // only accepts integer item prices.
                return Math.round(dentist.price_list[type]);
            }
            return null;
        },
        [getSelectedDentist],
    );

    const handleDentistChange = (value: string) => {
        setData((prev) => {
            const dentist = dentists.find((d) => d.id.toString() === value);
            const updatedItems = prev.items.map((item) => {
                if (dentist?.price_list && item.type in dentist.price_list) {
                    return {
                        ...item,
                        price: Math.round(dentist.price_list[item.type]),
                    };
                }
                return item;
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

    const addItem = () => {
        const defaultType = workTypeNames[0] || '';
        const price = getDentistPrice(defaultType) ?? 0;
        setData('items', [
            ...data.items,
            {
                type: defaultType,
                patient_name: '',
                quantity: 1,
                price,
                notes: '',
                date: today(),
                selected_teeth: [],
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
            const dentistPrice = getDentistPrice(value as string);
            if (dentistPrice !== null) {
                newItems[index].price = dentistPrice;
            }
        }

        setData('items', newItems);
    };

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
                        <Select
                            value={data.dentist_id}
                            onValueChange={handleDentistChange}
                        >
                            <SelectTrigger className={fieldClass}>
                                <SelectValue placeholder="اختر الطبيب" />
                            </SelectTrigger>
                            <SelectContent>
                                {dentists.map((dentist) => (
                                    <SelectItem
                                        key={dentist.id}
                                        value={dentist.id.toString()}
                                    >
                                        {dentist.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.dentist_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status" className={labelClass}>
                            الحالة
                        </Label>
                        <Select
                            value={data.status}
                            onValueChange={(value: string) =>
                                setData('status', value as typeof data.status)
                            }
                        >
                            <SelectTrigger className={fieldClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(ORDER_STATUSES).map(
                                    ([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
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
                                يتم ملء الأسعار تلقائياً حسب قائمة أسعار الطبيب{' '}
                                <span className="font-semibold">
                                    {selectedDentist.name}
                                </span>
                            </span>
                        </div>
                    )}
            </section>

            {/* Items */}
            <section className="space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-bold text-foreground">
                        العناصر
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
                    <div className="space-y-4">
                        {data.items.map((item, index) => (
                            <div
                                key={index}
                                className="flex flex-col gap-[18px] rounded-2xl border bg-card p-6"
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
                                            message={itemError(index, 'type')}
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
                                                updateItem(index, 'date', value)
                                            }
                                            required
                                            className={fieldClass}
                                        />
                                        <InputError
                                            message={itemError(index, 'date')}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label className={labelClass}>
                                            الكمية{' '}
                                            {item.selected_teeth.length > 0 && (
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
                                                    parseInt(e.target.value) ||
                                                    0,
                                                )
                                            }
                                            readOnly={
                                                item.selected_teeth.length > 0
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
                                            السعر
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            value={item.price || ''}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'price',
                                                    parseInt(e.target.value) ||
                                                    0,
                                                )
                                            }
                                            className={fieldClass}
                                        />
                                        <InputError
                                            message={itemError(index, 'price')}
                                        />
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
                                                    : newItems[index].quantity,
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
    );
}
