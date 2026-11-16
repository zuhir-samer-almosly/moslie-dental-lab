import { useForm } from '@inertiajs/react';
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
import { today } from '@/lib/utils';
import type { Expense } from '@/types';
import { useExpenseCategories, useExpenseCategoryLabels } from '@/types';

/**
 * The one expense form, shared by the create and edit pages. Given an
 * `expense` it edits that one; without, it records a new one.
 */
export default function ExpenseForm({
    expense,
    todayRate,
}: {
    expense?: Expense;
    todayRate: string | null;
}) {
    const activeCategories = useExpenseCategories();
    const categoryLabels = useExpenseCategoryLabels();
    // The picker offers active categories, plus this expense's own category
    // even if it was deactivated since — otherwise editing an old expense
    // would show a blank field for a value that is already valid.
    const EXPENSE_CATEGORIES =
        !expense || expense.category in activeCategories
            ? activeCategories
            : {
                  ...activeCategories,
                  [expense.category]:
                      categoryLabels[expense.category] ?? expense.category,
              };
    const { data, setData, post, put, processing, errors } = useForm({
        category: expense?.category ?? '',
        description: expense?.description ?? '',
        amount: expense?.amount.toString() ?? '',
        currency: (expense?.currency ?? 'SYP') as CurrencyAmount['currency'],
        original_amount:
            expense?.original_amount === null ||
            expense?.original_amount === undefined
                ? ''
                : (expense.original_amount / 100).toFixed(2),
        rate: expense?.rate ?? '',
        expense_date: expense
            ? (expense.expense_date || expense.created_at).split('T')[0]
            : today(),
        notes: expense?.notes ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (expense) {
            put(`/expenses/${expense.id}`);
        } else {
            post('/expenses');
        }
    };

    return (
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
                            options={Object.entries(EXPENSE_CATEGORIES).map(
                                ([key, label]) => ({
                                    value: key,
                                    label,
                                }),
                            )}
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
                            onChange={(value) => setData('expense_date', value)}
                            required
                        />
                        <InputError message={errors.expense_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">ملاحظات</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {expense ? 'حفظ التعديلات' : 'حفظ'}
                    </Button>
                </CardContent>
            </Card>
        </form>
    );
}
