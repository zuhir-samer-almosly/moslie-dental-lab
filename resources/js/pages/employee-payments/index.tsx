import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    HandCoins,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { formatDate } from '@/components/order-display';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, EmployeePayment } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الرواتب',
        href: '/employee-payments',
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

export default function EmployeePaymentsIndex({
    payments,
    month,
    total,
}: {
    payments: EmployeePayment[];
    month: string;
    total: number;
}) {
    const goToMonth = (next: string) => {
        router.get(
            '/employee-payments',
            { month: next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الراتب؟')) {
            router.delete(`/employee-payments/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الرواتب" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            الرواتب
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            رواتب وسلف الموظفين حسب الشهر
                        </p>
                    </div>
                    <Button asChild size="lg" className="gap-2 sm:w-auto">
                        <Link href="/employee-payments/create">
                            <Plus className="size-4" />
                            تسجيل راتب
                        </Link>
                    </Button>
                </div>

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
                        <Input
                            type="month"
                            value={month}
                            className="w-44"
                            onChange={(e) => goToMonth(e.target.value)}
                        />
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
                                إجمالي رواتب {MONTH_LABEL(month)}
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
                        {payments.length === 0 ? (
                            <EmptyState
                                icon={HandCoins}
                                text="لا توجد رواتب مسجّلة في هذا الشهر"
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>الموظف</TableHead>
                                        <TableHead>المبلغ</TableHead>
                                        <TableHead>التاريخ</TableHead>
                                        <TableHead>ملاحظات</TableHead>
                                        <TableHead className="text-end">
                                            الإجراءات
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payments.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="font-medium">
                                                {payment.employee?.name}
                                            </TableCell>
                                            <TableCell className="font-semibold text-rose-600 tabular-nums dark:text-rose-400">
                                                {payment.amount.toLocaleString(
                                                    'en-US',
                                                )}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                {formatDate(
                                                    payment.payment_date ||
                                                        payment.created_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {payment.notes || '-'}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/employee-payments/${payment.id}/edit`}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(
                                                                payment.id,
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
    icon: typeof HandCoins;
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
