import { Head, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import CurrencyAmountField, {
    type CurrencyAmount,
} from '@/components/money/currency-amount-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { DateInput } from '@/components/ui/date-input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Dentist, DentistPayment } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المدفوعات',
        href: '/payments',
    },
    {
        title: 'تعديل دفعة',
        href: '#',
    },
];

export default function PaymentsEdit({
    payment,
    dentists,
    todayRate,
}: {
    payment: DentistPayment;
    dentists: Dentist[];
    todayRate: string | null;
}) {
    const form = useForm({
        dentist_id: payment.dentist_id.toString(),
        amount: payment.amount.toString(),
        payment_date:
            payment.payment_date ||
            new Date(payment.created_at).toISOString().split('T')[0],
        currency: (payment.currency ?? 'SYP') as CurrencyAmount['currency'],
        // Stored in cents; the field edits whole dollars the way they were handed over.
        original_amount:
            payment.original_amount === null ||
            payment.original_amount === undefined
                ? ''
                : (payment.original_amount / 100).toFixed(2),
        rate: payment.rate ?? '',
    });
    const { data, setData, processing, errors } = form;

    const selectedDentist = dentists.find(
        (d) => d.id.toString() === data.dentist_id,
    );
    /**
     * Whether the selected dentist bills in dollars. Decided from the
     * dentist, exactly as the order form does — never from the form's own
     * `currency` field, which a lira dentist may still carry from the
     * payment's original save or a prior selection.
     */
    const dollarDentist = selectedDentist?.currency === 'USD';

    /**
     * Switching dentists mid-edit must not leave a stale amount typed for
     * the previous dentist's currency sitting in state — a lira figure
     * surviving under a dollar dentist, or vice versa. Reset only when the
     * currency actually crosses that line; switching between two lira (or
     * two dollar) dentists keeps whatever the user already typed.
     */
    const handleDentistChange = (value: string) => {
        const dentist = dentists.find((d) => d.id.toString() === value);
        const isDollar = dentist?.currency === 'USD';

        // Reset the money fields only when dollar-ness actually changes —
        // switching between two lira dentists (or two dollar dentists) must
        // not throw away a figure the user already typed.
        if (isDollar === dollarDentist) {
            setData('dentist_id', value);
            return;
        }

        setData((prev) => ({
            ...prev,
            dentist_id: value,
            amount: '',
            original_amount: '',
            rate: '',
            currency: isDollar ? 'USD' : 'SYP',
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // A dollar dentist's payment is native dollars: `amount` and `rate`
        // are `prohibited` server-side, not merely optional, so they must be
        // omitted from the payload entirely rather than sent zeroed.
        form.transform((formData) =>
            dollarDentist
                ? {
                      dentist_id: formData.dentist_id,
                      payment_date: formData.payment_date,
                      currency: 'USD' as const,
                      original_amount: formData.original_amount,
                  }
                : formData,
        );
        form.put(`/payments/${payment.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تعديل دفعة" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-fit gap-2 text-muted-foreground"
                        onClick={() => window.history.back()}
                    >
                        <ArrowRight className="size-4" />
                        رجوع
                    </Button>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            تعديل دفعة
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            تحديث بيانات الدفعة المستلمة
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="space-y-6 p-5 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="dentist_id">الطبيب</Label>
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
                                />
                                <InputError message={errors.dentist_id} />
                            </div>

                            <CurrencyAmountField
                                value={data}
                                onChange={(patch) =>
                                    setData((prev) => ({ ...prev, ...patch }))
                                }
                                errors={errors}
                                todayRate={todayRate}
                                native={dollarDentist}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="payment_date">التاريخ</Label>
                                <DateInput
                                    id="payment_date"
                                    value={data.payment_date}
                                    onChange={(value) =>
                                        setData('payment_date', value)
                                    }
                                    required
                                />
                                <InputError message={errors.payment_date} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                تحديث
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
