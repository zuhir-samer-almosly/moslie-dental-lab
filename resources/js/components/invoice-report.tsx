import {
    Dash,
    formatDate,
    itemAmount,
    itemDate,
    itemPatient,
    itemTeeth,
    TeethOdontogram,
} from '@/components/order-display';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Dentist, DentistPayment, Order, OrderItem } from '@/types';

/**
 * The invoice document itself — everything that ends up on paper, and nothing
 * that doesn't. Two shells render it: the interactive page (`pages/invoices/
 * index.tsx`, wrapped in AppLayout with the filter form above it) and the
 * headless print page (`pages/invoices/print.tsx`) that Browsershot loads to
 * produce the PDF. Keeping it in one component is what stops the on-screen
 * totals and the PDF totals from ever drifting apart.
 */

type DentistGroup = {
    id: number;
    name: string;
    gender: 'male' | 'female';
    opening: number;
    rows: { order: Order; item: OrderItem | null }[];
    ordersTotal: number;
    paymentsTotal: number;
    due: number;
};

export type InvoiceData = {
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

/**
 * Build a per-dentist statement: previous (opening) balance carried from
 * earlier months + this period's orders − this period's payments = amount due.
 * Dentists with only a carried-over balance (no new orders) still appear.
 */
export function groupByDentist(
    orders: Order[],
    payments: DentistPayment[],
    openingByDentist: Record<string, number>,
    dentists: Dentist[],
): DentistGroup[] {
    const map = new Map<number, DentistGroup>();
    const findDentist = (id: number) => dentists.find((d) => d.id === id);
    const nameFor = (id: number) => findDentist(id)?.name ?? '—';
    const genderFor = (id: number) => findDentist(id)?.gender ?? 'male';

    const ensure = (id: number, name?: string, gender?: 'male' | 'female'): DentistGroup => {
        let group = map.get(id);
        if (!group) {
            group = {
                id,
                name: name ?? nameFor(id),
                gender: gender ?? genderFor(id),
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
        const group = ensure(order.dentist_id, order.dentist?.name, order.dentist?.gender);
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
        ensure(payment.dentist_id, payment.dentist?.name, payment.dentist?.gender).paymentsTotal +=
            payment.amount;
    }

    for (const group of map.values()) {
        group.due = group.opening + group.ordersTotal - group.paymentsTotal;
    }

    return [...map.values()];
}

export function InvoiceReport({
    orders,
    payments,
    totals,
    openingByDentist,
    dentists,
    filters,
}: InvoiceData) {
    if (!orders || !payments || !totals) {
        return null;
    }

    const groups = groupByDentist(orders, payments, openingByDentist, dentists);

    return (
        <div className="space-y-6">
            {/* Header - visible on print */}
            <div className="text-center">
                <h2 className="text-xl font-bold">تقرير الفواتير</h2>
                <p className="text-sm text-muted-foreground">
                    من {formatDate(filters.from)} إلى {formatDate(filters.to)}
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
                                    {group.gender === 'female' ? 'الدكتورة' : 'الدكتور'} : {group.name} {group.gender === 'female' ? 'المحترمة' : 'المحترم'}
                                </h4>
                            </div>
                            <div className="rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>اسم المريض</TableHead>
                                            <TableHead>التاريخ</TableHead>
                                            <TableHead>العنصر</TableHead>
                                            <TableHead>الأسنان</TableHead>
                                            <TableHead>السعر</TableHead>
                                            <TableHead>المبلغ</TableHead>
                                            <TableHead>ملاحظات</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {group.rows.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={7}
                                                    className="text-center text-muted-foreground"
                                                >
                                                    لا توجد طلبات جديدة في هذه
                                                    الفترة
                                                </TableCell>
                                            </TableRow>
                                        )}
                                        {group.rows.map(({ order, item }) =>
                                            item ? (
                                                <TableRow key={`i-${item.id}`}>
                                                    <TableCell>
                                                        {itemPatient(item) || (
                                                            <Dash />
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap">
                                                        {formatDate(
                                                            itemDate(item),
                                                        ) ||
                                                            formatDate(
                                                                order.due_date,
                                                            ) || <Dash />}
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap">
                                                        {item.type}{' '}
                                                        <span className="text-muted-foreground">
                                                            × {item.quantity}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <TeethOdontogram
                                                            teeth={itemTeeth(
                                                                item,
                                                            )}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap tabular-nums">
                                                        {(
                                                            item.price ?? 0
                                                        ).toLocaleString(
                                                            'en-US',
                                                        )}{' '}
                                                        <span className="text-muted-foreground">
                                                            ×{' '}
                                                            {item.quantity ?? 0}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="tabular-nums">
                                                        {itemAmount(
                                                            item,
                                                        ).toLocaleString(
                                                            'en-US',
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="whitespace-pre-line">
                                                        {item.notes || <Dash />}
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                <TableRow key={`o-${order.id}`}>
                                                    <TableCell>
                                                        <Dash />
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap">
                                                        {formatDate(
                                                            order.due_date,
                                                        ) || <Dash />}
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
                                                    <TableCell className="whitespace-pre-line">
                                                        {order.notes || (
                                                            <Dash />
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                            <div className="space-y-1 rounded-md bg-muted px-3 py-2 text-sm">
                                {group.opening !== 0 && (
                                    <div className="flex items-center justify-between">
                                        <span>
                                            رصيد مستحق من الفاتورة الماضية
                                        </span>
                                        <span className="tabular-nums">
                                            {group.opening.toLocaleString(
                                                'en-US',
                                            )}
                                        </span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between">
                                    <span>إجمالي طلبات الفترة</span>
                                    <span className="tabular-nums">
                                        {group.ordersTotal.toLocaleString(
                                            'en-US',
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span>مدفوعات الفترة</span>
                                    <span className="tabular-nums text-red-600">
                                        −
                                        {group.paymentsTotal.toLocaleString(
                                            'en-US',
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between border-t border-border pt-1 font-bold">
                                    <span>المستحق على الطبيب</span>
                                    <span className="tabular-nums text-green-600">
                                        {group.due.toLocaleString('en-US')}
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
                                <TableHead>التاريخ</TableHead>
                                <TableHead>المبلغ</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={2}
                                        className="text-center"
                                    >
                                        لا توجد مدفوعات
                                    </TableCell>
                                </TableRow>
                            ) : (
                                payments.map((payment) => (
                                    <TableRow key={payment.id}>
                                        <TableCell>
                                            {formatDate(
                                                payment.payment_date ||
                                                    payment.created_at,
                                            )}
                                        </TableCell>
                                        <TableCell className="font-semibold text-red-600">
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
                        <span className="tabular-nums text-red-600">
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
                            <span>رصيد مستحق من الفاتورة الماضية:</span>
                            <span className="font-semibold tabular-nums">
                                {totals.opening.toLocaleString('en-US')}
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
                        <span className="font-semibold tabular-nums text-red-600">
                            −{totals.payments.toLocaleString('en-US')}
                        </span>
                    </div>
                    <div className="flex justify-between border-t pt-2">
                        <span className="font-bold">الإجمالي المستحق:</span>
                        <span className="text-lg font-bold tabular-nums text-green-600">
                            {totals.balance.toLocaleString('en-US')}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}
