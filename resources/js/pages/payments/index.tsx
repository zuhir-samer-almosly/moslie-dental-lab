import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Pencil, Plus, Trash2 } from 'lucide-react';
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
import type { BreadcrumbItem, DentistPayment } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المدفوعات',
        href: '/payments',
    },
];

export default function PaymentsIndex({
    payments,
}: {
    payments: DentistPayment[];
}) {
    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذه الدفعة؟')) {
            router.delete(`/payments/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="المدفوعات" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            المدفوعات
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {payments.length} دفعة مسجّلة
                        </p>
                    </div>
                    <Button asChild size="lg" className="gap-2 sm:w-auto">
                        <Link href="/payments/create">
                            <Plus className="size-4" />
                            إضافة دفعة
                        </Link>
                    </Button>
                </div>

                {/* Table */}
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex items-center justify-between border-b p-5">
                        <div className="space-y-0.5">
                            <h2 className="font-semibold">قائمة المدفوعات</h2>
                            <p className="text-xs text-muted-foreground">
                                جميع المدفوعات المستلمة من الأطباء
                            </p>
                        </div>
                    </div>
                    <CardContent className="p-0">
                        {payments.length === 0 ? (
                            <EmptyState
                                icon={CreditCard}
                                text="لا توجد مدفوعات بعد"
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>اسم الطبيب</TableHead>
                                        <TableHead>المبلغ</TableHead>
                                        <TableHead>التاريخ</TableHead>
                                        <TableHead className="text-end">
                                            الإجراءات
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payments.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="font-medium">
                                                {payment.dentist?.name}
                                            </TableCell>
                                            <TableCell className="font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                                +
                                                {payment.amount.toLocaleString(
                                                    'en-US',
                                                )}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                {new Date(
                                                    payment.payment_date ||
                                                        payment.created_at,
                                                ).toLocaleDateString('en-US')}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/payments/${payment.id}/edit`}
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
    icon: typeof CreditCard;
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
