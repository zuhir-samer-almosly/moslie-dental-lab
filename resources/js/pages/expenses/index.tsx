import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Receipt,
    Trash2,
} from 'lucide-react';
import { LedgerTabs } from '@/components/ledger-tabs';
import ForeignOrigin from '@/components/money/foreign-origin';
import { MonthPicker } from '@/components/month-picker';
import { formatDate } from '@/components/order-display';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatSyp } from '@/lib/money';
import type { BreadcrumbItem, Expense } from '@/types';
import { useExpenseCategoryLabels } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المصاريف',
        href: '/expenses',
    },
];

const MONTH_LABEL = (month: string) =>
    new Date(`${month}-01T00:00:00`).toLocaleDateString('ar-SY', {
        month: 'long',
        year: 'numeric',
    });

function shiftMonth(month: string, delta: number) {
    const [y, m] = month.split('-').map(Number);
    const date = new Date(y, m - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export default function ExpensesIndex({
    expenses,
    month,
    total,
}: {
    expenses: Expense[];
    month: string;
    total: number;
}) {
    const EXPENSE_CATEGORY_LABELS = useExpenseCategoryLabels();
    const goToMonth = (next: string) => {
        router.get(
            '/expenses',
            { month: next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا المصروف؟')) {
            router.delete(`/expenses/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="المصاريف" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            المصاريف
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            المصاريف العامة (مواصلات، ضرائب، إيجار...) حسب الشهر
                        </p>
                    </div>
                    <Button asChild size="lg" className="gap-2 sm:w-auto">
                        <Link href="/expenses/create">
                            <Plus className="size-4" />
                            تسجيل مصروف
                        </Link>
                    </Button>
                </div>

                <LedgerTabs active="expenses" month={month} />

                {/* Month picker + total */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => goToMonth(shiftMonth(month, -1))}
                            title="الشهر السابق"
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                        <MonthPicker value={month} onChange={goToMonth} />
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => goToMonth(shiftMonth(month, 1))}
                            title="الشهر التالي"
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                    </div>
                    <Card className="py-0">
                        <CardContent className="flex items-center gap-3 px-5 py-3">
                            <span className="text-sm text-muted-foreground">
                                إجمالي مصاريف {MONTH_LABEL(month)}
                            </span>
                            <span className="text-lg font-bold text-rose-600 tabular-nums dark:text-rose-400">
                                {total.toLocaleString('en-US')}
                            </span>
                        </CardContent>
                    </Card>
                </div>

                {/* Table */}
                <Card className="gap-0 overflow-hidden py-0">
                    <CardContent className="p-0">
                        {expenses.length === 0 ? (
                            <EmptyState
                                icon={Receipt}
                                text="لا توجد مصاريف مسجّلة في هذا الشهر"
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>البند</TableHead>
                                        <TableHead>الوصف</TableHead>
                                        <TableHead>المبلغ</TableHead>
                                        <TableHead>التاريخ</TableHead>
                                        <TableHead>ملاحظات</TableHead>
                                        <TableHead className="text-end">
                                            الإجراءات
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {expenses.map((expense) => (
                                        <TableRow key={expense.id}>
                                            <TableCell className="font-medium">
                                                {EXPENSE_CATEGORY_LABELS[
                                                    expense.category
                                                ] ?? expense.category}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {expense.description || '-'}
                                            </TableCell>
                                            <TableCell className="font-semibold text-rose-600 tabular-nums dark:text-rose-400">
                                                {formatSyp(expense.amount)}
                                                <ForeignOrigin
                                                    money={expense}
                                                    className="text-rose-700/70 dark:text-rose-400/70"
                                                />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                {formatDate(
                                                    expense.expense_date ||
                                                        expense.created_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {expense.notes || '-'}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/expenses/${expense.id}/edit`}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(
                                                                expense.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function EmptyState({
    icon: Icon,
    text,
}: {
    icon: typeof Receipt;
    text: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <p className="text-sm text-muted-foreground">{text}</p>
        </div>
    );
}
