import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-react';
import {
    Dash,
    formatDate,
    itemAmount,
    itemDate,
    itemPatient,
    itemTeeth,
    TeethOdontogram,
} from '@/components/order-display';
import { Badge } from '@/components/ui/badge';
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
import type { BreadcrumbItem, Order } from '@/types';
import { ORDER_STATUSES } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الطلبات',
        href: '/orders',
    },
];

export default function OrdersIndex({ orders }: { orders: Order[] }) {
    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الطلب؟')) {
            router.delete(`/orders/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الطلبات" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            الطلبات
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {orders.length} طلب في المختبر
                        </p>
                    </div>
                    <Button asChild size="lg" className="gap-2 sm:w-auto">
                        <Link href="/orders/create">
                            <Plus className="size-4" />
                            إضافة طلب
                        </Link>
                    </Button>
                </div>

                {/* Table */}
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex items-center justify-between border-b p-5">
                        <div className="space-y-0.5">
                            <h2 className="font-semibold">قائمة الطلبات</h2>
                            <p className="text-xs text-muted-foreground">
                                جميع الطلبات وعناصرها
                            </p>
                        </div>
                    </div>
                    <CardContent className="overflow-x-auto p-0">
                        {orders.length === 0 ? (
                            <EmptyState
                                icon={ClipboardList}
                                text="لا توجد طلبات بعد"
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>اسم الطبيب</TableHead>
                                        <TableHead>اسم المريض</TableHead>
                                        <TableHead>العنصر</TableHead>
                                        <TableHead>الأسنان</TableHead>
                                        <TableHead>التاريخ</TableHead>
                                        <TableHead>الحالة</TableHead>
                                        <TableHead>المبلغ</TableHead>
                                        <TableHead>ملاحظات</TableHead>
                                        <TableHead className="text-end">
                                            الإجراءات
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orders.map((order) => {
                                        const items = order.items ?? [];
                                        const span = Math.max(items.length, 1);

                                        // Order-level cells are rendered once and span all the
                                        // order's item rows (dentist / status / amount / actions).
                                        const dentistCell = (
                                            <TableCell
                                                rowSpan={span}
                                                className="border-s align-middle font-medium"
                                            >
                                                {!!order.previous_balance && (
                                                    <span className="mb-1 block text-[11px] font-normal text-muted-foreground">
                                                        رصيد سابق مستحق من قبل:{' '}
                                                        <span className="font-semibold text-foreground tabular-nums">
                                                            {order.previous_balance.toLocaleString(
                                                                'en-US',
                                                            )}
                                                        </span>
                                                    </span>
                                                )}
                                                {order.dentist?.name}
                                            </TableCell>
                                        );
                                        const statusCell = (
                                            <TableCell
                                                rowSpan={span}
                                                className="border-s align-middle"
                                            >
                                                <Badge>
                                                    {
                                                        ORDER_STATUSES[
                                                            order.status
                                                        ]
                                                    }
                                                </Badge>
                                            </TableCell>
                                        );
                                        const actionsCell = (
                                            <TableCell
                                                rowSpan={span}
                                                className="text-end align-middle"
                                            >
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/orders/${order.id}/edit`}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(
                                                                order.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        );

                                        if (items.length === 0) {
                                            return (
                                                <TableRow key={order.id}>
                                                    {dentistCell}
                                                    <TableCell>
                                                        <Dash />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Dash />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Dash />
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap">
                                                        {formatDate(
                                                            order.due_date,
                                                        ) || <Dash />}
                                                    </TableCell>
                                                    {statusCell}
                                                    <TableCell className="tabular-nums">
                                                        {order.amount.toLocaleString(
                                                            'en-US',
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="whitespace-pre-line">
                                                        {order.notes || (
                                                            <Dash />
                                                        )}
                                                    </TableCell>
                                                    {actionsCell}
                                                </TableRow>
                                            );
                                        }

                                        return items.map((item, index) => (
                                            <TableRow key={item.id}>
                                                {index === 0 && dentistCell}
                                                <TableCell>
                                                    {itemPatient(item) || (
                                                        <Dash />
                                                    )}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    {item.type}{' '}
                                                    <span className="text-muted-foreground">
                                                        × {item.quantity}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <TeethOdontogram
                                                        teeth={itemTeeth(item)}
                                                    />
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    {formatDate(
                                                        itemDate(item),
                                                    ) ||
                                                        formatDate(
                                                            order.due_date,
                                                        ) || <Dash />}
                                                </TableCell>
                                                {index === 0 && statusCell}
                                                <TableCell className="tabular-nums">
                                                    {itemAmount(
                                                        item,
                                                    ).toLocaleString('en-US')}
                                                </TableCell>
                                                <TableCell className="whitespace-pre-line">
                                                    {item.notes || <Dash />}
                                                </TableCell>
                                                {index === 0 && actionsCell}
                                            </TableRow>
                                        ));
                                    })}
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
    icon: typeof ClipboardList;
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
