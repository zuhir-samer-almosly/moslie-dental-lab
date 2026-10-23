import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Check, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import CurrencyAmountField, {
    type CurrencyAmount,
} from '@/components/money/currency-amount-field';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { DateInput } from '@/components/ui/date-input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatMoney, usdToSyp } from '@/lib/money';
import type { BreadcrumbItem, Dentist } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المدفوعات',
        href: '/payments',
    },
    {
        title: 'إضافة دفعة',
        href: '/payments/create',
    },
];

type PaymentRow = CurrencyAmount & {
    payment_date: string;
    notes: string;
};

const today = () => new Date().toISOString().split('T')[0];

const emptyRow = (currency: CurrencyAmount['currency']): PaymentRow => ({
    amount: '',
    payment_date: today(),
    currency,
    original_amount: '',
    rate: '',
    notes: '',
});

/**
 * Several payments from one dentist in one sitting — four cheques handed over
 * the same day are four rows here, not four trips through the form. The
 * dentist is picked once; each row becomes its own payment on save, and the
 * batch saves all or nothing.
 */
export default function PaymentsCreate({
    dentists,
    todayRate,
}: {
    dentists: Dentist[];
    todayRate: string | null;
}) {
    const form = useForm({
        dentist_id: '',
        payments: [emptyRow('SYP')],
    });
    const { data, setData, processing, errors } = form;

    const selectedDentist = dentists.find(
        (d) => d.id.toString() === data.dentist_id,
    );
    /**
     * Whether the selected dentist bills in dollars. Decided from the
     * dentist, exactly as the order form does — never from a row's own
     * `currency` field, which a lira dentist may still carry from a prior
     * selection.
     */
    const dollarDentist = selectedDentist?.currency === 'USD';

    /**
     * Switching dentists mid-form must not leave stale amounts typed for the
     * previous dentist's currency sitting in the rows. Reset the money fields
     * only when dollar-ness actually changes — switching between two lira
     * (or two dollar) dentists keeps whatever the user already typed.
     */
    const handleDentistChange = (value: string) => {
        const dentist = dentists.find((d) => d.id.toString() === value);
        const isDollar = dentist?.currency === 'USD';

        if (isDollar === dollarDentist) {
            setData('dentist_id', value);
            return;
        }

        setData((prev) => ({
            dentist_id: value,
            payments: prev.payments.map((row) => ({
                ...row,
                amount: '',
                original_amount: '',
                rate: '',
                currency: isDollar ? 'USD' : 'SYP',
            })),
        }));
    };

    const updateRow = (index: number, patch: Partial<PaymentRow>) =>
        setData((prev) => ({
            ...prev,
            payments: prev.payments.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        }));

    // The add button is sticky, so it can be clicked from anywhere in a long
    // list — but the new row is always appended at the end. Scroll to it so
    // the click never looks like it did nothing.
    const rowsContainerRef = useRef<HTMLDivElement>(null);
    const scrollToLastRow = useRef(false);

    useEffect(() => {
        if (!scrollToLastRow.current) {
            return;
        }
        scrollToLastRow.current = false;
        rowsContainerRef.current?.lastElementChild?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }, [data.payments.length]);

    const addRow = () => {
        scrollToLastRow.current = true;
        setData((prev) => ({
            ...prev,
            payments: [
                ...prev.payments,
                emptyRow(dollarDentist ? 'USD' : 'SYP'),
            ],
        }));
    };

    const removeRow = (index: number) =>
        setData((prev) => ({
            ...prev,
            payments: prev.payments.filter((_, i) => i !== index),
        }));

    // Server-side errors for row fields arrive keyed as
    // `payments.<i>.<field>`, which the useForm error type doesn't know about.
    const rowErrors = (index: number) => {
        const all = errors as Record<string, string | undefined>;
        const prefix = `payments.${index}.`;

        return Object.fromEntries(
            Object.entries(all)
                .filter(([key]) => key.startsWith(prefix))
                .map(([key, message]) => [key.slice(prefix.length), message]),
        ) as Partial<Record<keyof PaymentRow, string>>;
    };

    /**
     * What the books will carry for the whole batch: dollars for a dollar
     * dentist, otherwise lira — a lira dentist's dollar cheque counted at the
     * lira it converts to, the same preview the row itself shows.
     */
    const total = data.payments.reduce((sum, row) => {
        if (dollarDentist) {
            return (
                sum + Math.round((parseFloat(row.original_amount) || 0) * 100)
            );
        }
        if (row.currency === 'USD') {
            const dollars = parseFloat(row.original_amount);
            const rate = parseFloat(row.rate);
            return Number.isFinite(dollars) && Number.isFinite(rate)
                ? sum + usdToSyp(dollars, rate)
                : sum;
        }
        return sum + (parseInt(row.amount, 10) || 0);
    }, 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // A dollar dentist's payment is native dollars: `amount` and `rate`
        // are `prohibited` server-side, not merely optional, so they must be
        // omitted from each row entirely rather than sent zeroed.
        //
        // This is a whitelist, so every non-money field has to be listed here
        // too — anything left out is silently dropped for dollar dentists
        // only, which no server-side test can see.
        form.transform((formData) => ({
            dentist_id: formData.dentist_id,
            payments: formData.payments.map((row) =>
                dollarDentist
                    ? {
                          payment_date: row.payment_date,
                          currency: 'USD' as const,
                          original_amount: row.original_amount,
                          notes: row.notes,
                      }
                    : row,
            ),
        }));
        form.post('/payments/batch');
    };

    const labelClass = 'text-[14px] font-semibold text-[#435955]';
    const fieldClass = 'h-11 rounded-[10px]';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إضافة دفعة" />

            {/* No overflow-* here: it would make this a scroll container and
                break the sticky payments bar. */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex max-w-[920px] items-center gap-3.5">
                    <Link
                        href="/payments"
                        title="رجوع إلى المدفوعات"
                        className="flex size-10 items-center justify-center rounded-[10px] border bg-card text-[#435955] transition-colors hover:border-primary hover:text-primary"
                    >
                        <ArrowRight className="size-[18px]" />
                    </Link>
                    <div className="space-y-1">
                        <h1 className="text-[26px] font-bold text-foreground">
                            إضافة دفعات جديدة
                        </h1>
                        <p className="text-[15px] text-muted-foreground">
                            سجّل دفعة أو أكثر مستلمة من الطبيب
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="max-w-[920px] space-y-6"
                >
                    {/* Dentist */}
                    <section className="flex flex-col gap-[18px] rounded-2xl border bg-card p-6">
                        <h2 className="text-base font-bold text-foreground">
                            معلومات الدفعة
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="dentist_id" className={labelClass}>
                                الطبيب
                            </Label>
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
                                aria-invalid={!!errors.dentist_id}
                                className={fieldClass}
                            />
                            <InputError message={errors.dentist_id} />
                        </div>
                    </section>

                    {/* Payments */}
                    <section className="space-y-4">
                        {/* Sticky so the add button stays reachable without
                            scrolling back to the top of a long list. */}
                        <div className="sticky top-0 z-20 flex items-center justify-between bg-background py-3">
                            <h2 className="text-base font-bold text-foreground">
                                الدفعات
                                <span className="mr-1.5 text-sm font-medium text-muted-foreground tabular-nums">
                                    ({data.payments.length})
                                </span>
                            </h2>
                            <Button
                                type="button"
                                onClick={addRow}
                                variant="outline"
                                className="gap-2 rounded-[10px] border-primary text-primary hover:bg-secondary hover:text-primary"
                            >
                                <Plus className="size-4" />
                                إضافة دفعة
                            </Button>
                        </div>

                        <div ref={rowsContainerRef} className="space-y-4">
                            {data.payments.map((row, index) => {
                                const rowError = rowErrors(index);
                                const idPrefix = `payments-${index}-`;

                                return (
                                    <div
                                        key={index}
                                        // scroll-mt clears the sticky bar when a
                                        // newly added row is scrolled into view.
                                        className="flex scroll-mt-16 flex-col gap-[18px] rounded-2xl border bg-card p-6"
                                    >
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-[15px] font-bold text-primary">
                                                دفعة {index + 1}
                                            </h3>
                                            {data.payments.length > 1 && (
                                                <button
                                                    type="button"
                                                    title="حذف الدفعة"
                                                    onClick={() =>
                                                        removeRow(index)
                                                    }
                                                    className="flex size-[34px] items-center justify-center rounded-[9px] border border-[#F5D5DB] bg-card text-[#BE123C] transition-colors hover:bg-[#FDECEE]"
                                                >
                                                    <Trash2 className="size-[15px]" />
                                                </button>
                                            )}
                                        </div>

                                        <CurrencyAmountField
                                            value={row}
                                            onChange={(patch) =>
                                                updateRow(index, patch)
                                            }
                                            errors={rowError}
                                            todayRate={todayRate}
                                            native={dollarDentist}
                                            idPrefix={idPrefix}
                                        />

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`${idPrefix}payment_date`}
                                                    className={labelClass}
                                                >
                                                    التاريخ
                                                </Label>
                                                <DateInput
                                                    id={`${idPrefix}payment_date`}
                                                    value={row.payment_date}
                                                    onChange={(value) =>
                                                        updateRow(index, {
                                                            payment_date: value,
                                                        })
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        rowError.payment_date
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`${idPrefix}notes`}
                                                    className={labelClass}
                                                >
                                                    ملاحظات
                                                </Label>
                                                <Textarea
                                                    id={`${idPrefix}notes`}
                                                    value={row.notes}
                                                    onChange={(e) =>
                                                        updateRow(index, {
                                                            notes: e.target
                                                                .value,
                                                        })
                                                    }
                                                    rows={1}
                                                    placeholder="رقم الشيك، من سلّمها، نقداً أم حوالة"
                                                    className="rounded-[10px]"
                                                />
                                                <InputError
                                                    message={rowError.notes}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <InputError message={errors.payments} />
                    </section>

                    {/* Total + submit */}
                    <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-card px-6 py-5">
                        <span className="text-[17px] font-bold text-foreground">
                            المجموع الكلي:{' '}
                            <span
                                dir={dollarDentist ? 'ltr' : undefined}
                                className="text-primary tabular-nums"
                            >
                                {formatMoney(
                                    total,
                                    dollarDentist ? 'USD' : 'SYP',
                                )}
                            </span>
                            {!dollarDentist && (
                                <>
                                    {' '}
                                    <span className="text-[13px] font-medium text-muted-foreground">
                                        ليرة
                                    </span>
                                </>
                            )}
                        </span>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing}
                            className="gap-2 rounded-xl px-7 text-[15px] font-semibold shadow-[0_2px_6px_rgba(15,118,110,0.25)]"
                        >
                            <Check className="size-[17px]" />
                            حفظ
                        </Button>
                    </section>
                </form>
            </div>
        </AppLayout>
    );
}
