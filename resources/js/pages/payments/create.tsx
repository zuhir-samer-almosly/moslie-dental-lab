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

export default function PaymentsCreate({
    dentists,
    todayRate,
}: {
    dentists: Dentist[];
    todayRate: string | null;
}) {
    const { data, setData, post, processing, errors } = useForm({
        dentist_id: '',
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        currency: 'SYP' as CurrencyAmount['currency'],
        original_amount: '',
        rate: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/payments');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إضافة دفعة" />

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
                            إضافة دفعة جديدة
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            سجّل دفعة جديدة مستلمة من الطبيب
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
                                        setData('dentist_id', value ?? '')
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
                                حفظ
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
