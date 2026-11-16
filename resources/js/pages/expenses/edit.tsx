import { Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import ExpenseForm from '@/components/expenses/expense-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Expense } from '@/types';

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

export default function ExpensesEdit({
    expense,
    todayRate,
}: {
    expense: Expense;
    todayRate: string | null;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تعديل مصروف" />

            <div className="mx-auto flex h-full w-full max-w-[720px] flex-1 flex-col gap-6 p-4 md:p-6">
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

                <ExpenseForm expense={expense} todayRate={todayRate} />
            </div>
        </AppLayout>
    );
}
