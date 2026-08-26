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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { useExpenseCategories } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المصاريف',
        href: '/expenses',
    },
    {
        title: 'تسجيل مصروف',
        href: '/expenses/create',
    },
];

export default function ExpensesCreate({
    todayRate,
}: {
    todayRate: string | null;
}) {
    const EXPENSE_CATEGORIES = useExpenseCategories();
    const { data, setData, post, processing, errors } = useForm({
        category: '',
        description: '',
        amount: '',
        currency: 'SYP' as CurrencyAmount['currency'],
        original_amount: '',
        rate: '',
        expense_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/expenses');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تسجيل مصروف" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
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
                            تسجيل مصروف جديد
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            سجّل مصروفاً عاماً (مواصلات، ضرائب، إيجار...)
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="space-y-6 p-5 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="category">البند</Label>
                                <Combobox
                                    id="category"
                                    value={data.category}
                                    onChange={(value) =>
                                        setData('category', value ?? '')
                                    }
                                    options={Object.entries(
                                        EXPENSE_CATEGORIES,
                                    ).map(([key, label]) => ({
                                        value: key,
                                        label,
                                    }))}
                                    placeholder="اختر البند"
                                    aria-invalid={!!errors.category}
                                />
                                <InputError message={errors.category} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">الوصف</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    placeholder="تفاصيل المصروف (اختياري)"
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                                <InputError message={errors.description} />
                            </div>

                            <CurrencyAmountField
                                label="المبلغ"
                                value={data}
                                onChange={(patch) =>
                                    setData((prev) => ({ ...prev, ...patch }))
                                }
                                errors={errors}
                                todayRate={todayRate}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="expense_date">التاريخ</Label>
                                <DateInput
                                    id="expense_date"
                                    value={data.expense_date}
                                    onChange={(value) =>
                                        setData('expense_date', value)
                                    }
                                    required
                                />
                                <InputError message={errors.expense_date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">ملاحظات</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                />
                                <InputError message={errors.notes} />
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
