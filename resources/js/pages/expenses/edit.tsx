import { Head, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { DateInput } from '@/components/ui/date-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Expense } from '@/types';
import { useExpenseCategories, useExpenseCategoryLabels } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المصاريف',
        href: '/expenses',
    },
    {
        title: 'تعديل مصروف',
        href: '#',
    },
];

export default function ExpensesEdit({ expense }: { expense: Expense }) {
    const activeCategories = useExpenseCategories();
    const categoryLabels = useExpenseCategoryLabels();
    // The picker offers active categories, plus this expense's own category
    // even if it was deactivated since — otherwise editing an old expense
    // would show a blank field for a value that is already valid.
    const EXPENSE_CATEGORIES =
        expense.category in activeCategories
            ? activeCategories
            : {
                  ...activeCategories,
                  [expense.category]:
                      categoryLabels[expense.category] ?? expense.category,
              };
    const { data, setData, put, processing, errors } = useForm({
        category: expense.category,
        description: expense.description ?? '',
        amount: expense.amount.toString(),
        expense_date: (expense.expense_date || expense.created_at).split(
            'T',
        )[0],
        notes: expense.notes ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/expenses/${expense.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تعديل مصروف" />

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
                            تعديل المصروف
                        </h1>
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

                            <div className="grid gap-2">
                                <Label htmlFor="amount">المبلغ</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    min="1"
                                    value={data.amount}
                                    onChange={(e) =>
                                        setData('amount', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

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
                                حفظ التعديلات
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
