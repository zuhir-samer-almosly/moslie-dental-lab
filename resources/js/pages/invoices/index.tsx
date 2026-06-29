import { Head, router, useForm } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import Heading from '@/components/heading';
import {
    Dash,
    formatDate,
    itemAmount,
    itemDate,
    itemPatient,
    itemTeeth,
    TeethBadges,
} from '@/components/order-display';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    Dentist,
    DentistPayment,
    Order,
    OrderItem,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الفواتير',
        href: '/invoices',
    },
];

type DentistGroup = {
    id: number;
    name: string;
    opening: number;
    rows: { order: Order; item: OrderItem | null }[];
    ordersTotal: number;
    paymentsTotal: number;
    due: number;
};

/**
 * Build a per-dentist statement: previous (opening) balance carried from
 * earlier months + this period's orders − this period's payments = amount due.
 * Dentists with only a carried-over balance (no new orders) still appear.
 */
function groupByDentist(
    orders: Order[],
    payments: DentistPayment[],
    openingByDentist: Record<string, number>,
    dentists: Dentist[],
): DentistGroup[] {
    const map = new Map<number, DentistGroup>();
    const nameFor = (id: number) =>
        dentists.find((d) => d.id === id)?.name ?? '—';

    const ensure = (id: number, name?: string): DentistGroup => {
        let group = map.get(id);
        if (!group) {
            group = {
                id,
                name: name ?? nameFor(id),
                opening: 0,
                rows: [],
                ordersTotal: 0,
                paymentsTotal: 0,
                due: 0,
            };
            map.set(id, group);
        }
        return group;
    };

    // Seed groups with any carried-over balance first, so dentists who owe
    // from last month show up even with no orders this period.
    for (const [id, opening] of Object.entries(openingByDentist)) {
        ensure(Number(id)).opening = opening;
    }

    for (const order of orders) {
        const group = ensure(order.dentist_id, order.dentist?.name);
        const items = order.items ?? [];
        if (items.length === 0) {
            group.rows.push({ order, item: null });
            group.ordersTotal += order.amount;
        } else {
            for (const item of items) {
                group.rows.push({ order, item });
                group.ordersTotal += itemAmount(item);
            }
        }
    }

    for (const payment of payments) {
        ensure(payment.dentist_id, payment.dentist?.name).paymentsTotal +=
            payment.amount;
    }

    for (const group of map.values()) {
        group.due = group.opening + group.ordersTotal - group.paymentsTotal;
    }

    return [...map.values()];
}

type InvoiceData = {
    orders: Order[] | null;
    payments: DentistPayment[] | null;
    totals: {
        opening: number;
        orders: number;
        payments: number;
        balance: number;
    } | null;
    openingByDentist: Record<string, number>;
    dentists: Dentist[];
    filters: {
        from: string | null;
        to: string | null;
        dentist_id: string | null;
    };
};

export default function InvoicesIndex({
    orders,
    payments,
    totals,
    openingByDentist,
    dentists,
    filters,
}: InvoiceData) {
    const { data, setData } = useForm({
        from: filters.from || '',
        to: filters.to || '',
        dentist_id: filters.dentist_id || '',
    });

    const groups =
        orders && payments
            ? groupByDentist(orders, payments, openingByDentist, dentists)
            : [];

    const handleView = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/invoices', data as Record<string, string>);
    };

    const handlePrint = () => {
        window.print();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الفواتير" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6 print:overflow-visible">
                {/* Header */}
                <div className="space-y-1 print:hidden">
                    <h1 className="text-2xl font-bold tracking-tight">
                        الفواتير
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        إنشاء تقارير الطلبات والمدفوعات حسب الفترة والطبيب
                    </p>
                </div>

                {/* Filter Form - Hidden on print */}
                <form
                    onSubmit={handleView}
                    className="space-y-4 rounded-xl border bg-card p-5 text-card-foreground shadow-sm print:hidden"
                >
                    <Heading variant="small" title="تصفية البيانات" />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="from">من تاريخ</Label>
                            <Input
                                id="from"
                                type="date"
                                value={data.from}
                                onChange={(e) =>
                                    setData('from', e.target.value)
                                }
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="to">إلى تاريخ</Label>
                            <Input
                                id="to"
                                type="date"
                                value={data.to}
                                onChange={(e) => setData('to', e.target.value)}
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="dentist_id">الطبيب (اختياري)</Label>
                            <div className="flex gap-2">
                                <Select
                                    value={data.dentist_id || undefined}
                                    onValueChange={(value) =>
                                        setData('dentist_id', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="الكل" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {dentists.map((dentist) => (
                                            <SelectItem
                                                key={dentist.id}
                                                value={dentist.id.toString()}
                                            >
                                                {dentist.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {data.dentist_id && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setData('dentist_id', '')
                                        }
                                    >
                                        مسح
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit">عرض</Button>
                        {orders && (
                            <Button
                                type="button"
                                onClick={handlePrint}
                                variant="outline"
                            >
                                <Printer className="h-4 w-4" />
                                طباعة
                            </Button>
                        )}
                    </div>
                </form>

                {/* Printable Report */}
                {orders && payments && totals && (
                    <div className="space-y-6">
                        {/* Header - visible on print */}
                        <div className="text-center">
                            <h2 className="text-xl font-bold">
                                تقرير الفواتير
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                من{' '}
                                {new Date(filters.from!).toLocaleDateString(
                                    'en-US',
                                )}{' '}
                                إلى{' '}
                                {new Date(filters.to!).toLocaleDateString(
                                    'en-US',
                                )}
                            </p>
                        </div>

                        {/* Orders grouped by dentist */}
                        <div className="space-y-4">
                            <h3 className="text-lg font-semibold">الطلبات</h3>
                            {groups.length === 0 ? (
                                <div className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                                    لا توجد طلبات
                                </div>
                            ) : (
                                groups.map((group) => (
                                    <div key={group.id} className="space-y-2">
                                        <div className="text-center">
                                            <h4 className="text-2xl font-bold">
                                                الطبيب المحترم : {group.name}
                                            </h4>
                                        </div>
                                        <div className="rounded-lg border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            اسم المريض
                                                        </TableHead>
                                                        <TableHead>
                                                            التاريخ
                                                        </TableHead>
                                                        <TableHead>
                                                            العنصر
                                                        </TableHead>
                                                        <TableHead>
                                                            الأسنان
                                                        </TableHead>
                                                        <TableHead>
                                                            السعر
                                                        </TableHead>
                                                        <TableHead>
                                                            المبلغ
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {group.rows.length ===
                                                        0 && (
                                                        <TableRow>
                                                            <TableCell
                                                                colSpan={6}
                                                                className="text-center text-muted-foreground"
                                                            >
                                                                لا توجد طلبات
                                                                جديدة في هذه
                                                                الفترة
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                    {group.rows.map(
                                                        ({ order, item }) =>
                                                            item ? (
                                                                <TableRow
                                                                    key={`i-${item.id}`}
                                                                >
                                                                    <TableCell>
                                                                        {itemPatient(
                                                                            item,
                                                                        ) || (
                                                                            <Dash />
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell className="whitespace-nowrap">
                                                                        {formatDate(
                                                                            itemDate(
                                                                                item,
                                                                            ),
                                                                        ) ||
                                                                            formatDate(
                                                                                order.due_date,
                                                                            ) || (
                                                                                <Dash />
                                                                            )}
                                                                    </TableCell>
                                                                    <TableCell className="whitespace-nowrap">
                                                                        {
                                                                            item.type
                                                                        }{' '}
                                                                        <span className="text-muted-foreground">
                                                                            ×{' '}
                                                                            {
                                                                                item.quantity
                                                                            }
                                                                        </span>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <TeethBadges
                                                                            teeth={itemTeeth(
                                                                                item,
                                                                            )}
                                                                        />
                                                                    </TableCell>
                                                                    <TableCell className="tabular-nums whitespace-nowrap">
                                                                        {(
                                                                            item.price ??
                                                                            0
                                                                        ).toLocaleString(
                                                                            'en-US',
                                                                        )}{' '}
                                                                        <span className="text-muted-foreground">
                                                                            ×{' '}
                                                                            {item.quantity ??
                                                                                0}
                                                                        </span>
                                                                    </TableCell>
                                                                    <TableCell className="tabular-nums">
                                                                        {itemAmount(
                                                                            item,
                                                                        ).toLocaleString(
                                                                            'en-US',
                                                                        )}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ) : (
                                                                <TableRow
                                                                    key={`o-${order.id}`}
                                                                >
                                                                    <TableCell>
                                                                        <Dash />
                                                                    </TableCell>
                                                                    <TableCell className="whitespace-nowrap">
                                                                        {formatDate(
                                                                            order.due_date,
                                                                        ) || (
                                                                            <Dash />
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Dash />
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Dash />
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Dash />
                                                                    </TableCell>
                                                                    <TableCell className="tabular-nums">
                                                                        {order.amount.toLocaleString(
                                                                            'en-US',
                                                                        )}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </div>
                                        <div className="space-y-1 rounded-md bg-muted px-3 py-2 text-sm">
                                            <div className="flex items-center justify-between font-semibold">
                                                <span>إجمالي طلبات الفترة</span>
                                                <span className="tabular-nums">
                                                    {group.ordersTotal.toLocaleString(
                                                        'en-US',
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>

                        {/* Payments Table */}
                        <div className="space-y-2">
                            <h3 className="text-lg font-semibold">المدفوعات</h3>
                            <div className="rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>الطبيب</TableHead>
                                            <TableHead>التاريخ</TableHead>
                                            <TableHead>المبلغ</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={3}
                                                    className="text-center"
                                                >
                                                    لا توجد مدفوعات
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            payments.map((payment) => (
                                                <TableRow key={payment.id}>
                                                    <TableCell>
                                                        {payment.dentist?.name}
                                                    </TableCell>
                                                    <TableCell>
                                                        {new Date(
                                                            payment.payment_date ||
                                                                payment.created_at,
                                                        ).toLocaleDateString(
                                                            'en-US',
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {payment.amount.toLocaleString(
                                                            'en-US',
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                            <div className="space-y-1 rounded-md bg-muted px-3 py-2 text-sm">
                                <div className="flex items-center justify-between font-semibold">
                                    <span>إجمالي مدفوعات الفترة</span>
                                    <span className="tabular-nums">
                                        {totals.payments.toLocaleString('en-US')}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Summary */}
                        <div className="space-y-2 rounded-lg border bg-muted/50 p-4 print:break-inside-avoid">
                            <h3 className="text-lg font-semibold">الملخص</h3>
                            <div className="grid gap-2">
                                {totals.opening !== 0 && (
                                    <div className="flex justify-between">
                                        <span>
                                            رصيد مستحق من الفاتورة الماضية:
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {totals.opening.toLocaleString(
                                                'en-US',
                                            )}
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span>إجمالي الطلبات:</span>
                                    <span className="font-semibold tabular-nums">
                                        {totals.orders.toLocaleString('en-US')}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>إجمالي المدفوعات:</span>
                                    <span className="font-semibold tabular-nums">
                                        −{totals.payments.toLocaleString('en-US')}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t pt-2">
                                    <span className="font-bold">
                                        الإجمالي المستحق:
                                    </span>
                                    <span className="text-lg font-bold tabular-nums">
                                        {totals.balance.toLocaleString('en-US')}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
