import ForeignOrigin from '@/components/money/foreign-origin';
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
import { formatRate, formatSyp, formatUsd } from '@/lib/money';
import { cn } from '@/lib/utils';
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
 * Money here is coloured by what it means, not by the sign it is printed with.
 *
 * The payment lines carry a `−` because they are subtracted from the total,
 * and colouring by that sign is what used to paint payments red and the
 * balance green — the exact inverse of every other page. `payments/index.tsx`
 * shows payments in emerald and `outstanding/index.tsx` shows balances owed in
 * red; this report now agrees with both.
 */
const TONE = {
    owed: 'text-red-600 dark:text-red-400',
    credit: 'text-emerald-600 dark:text-emerald-400',
    settled: '',
} as const;

/**
 * Money the lab received. Always a credit, whatever sign it is printed with.
 */
const PAYMENT_TONE = TONE.credit;

/**
 * A balance owed, coloured by which way it points. Neutral at zero: a bold red
 * `0` on the one dentist who has settled in full reads as an alarm. Green when
 * negative, because an overpayment is a credit in the dentist's favour, not a
 * debt — `InvoiceTest` covers that case ("a credit balance is not clamped to
 * zero"), so it does reach the screen.
 */
function dueTone(due: number): string {
    if (due > 0) return TONE.owed;
    if (due < 0) return TONE.credit;

    return TONE.settled;
}

/**
 * A balance owed, with its unit. Since the redenomination divided every stored
 * amount by 100, a bare figure is ambiguous against any invoice printed before
 * it — the unit is what disambiguates, so it rides along with the two figures
 * a reader actually takes off the page.
 *
 * `inline-flex` keeps the digits and the unit in one bidi run: RTL reordering
 * would otherwise be free to move the Arabic label away from the Latin digit
 * group it belongs to. The unit resets `font-normal text-sm` so it stays quiet
 * beside the bold, enlarged grand total, and `whitespace-nowrap` stops it
 * wrapping mid-phrase in a narrow column.
 */
function DueAmount({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-baseline gap-1',
                dueTone(value),
                className,
            )}
        >
            <span className="tabular-nums">
                {value.toLocaleString('en-US')}
            </span>
            <span className="text-sm font-normal whitespace-nowrap text-muted-foreground">
                ليرة جديدة
            </span>
        </span>
    );
}

/**
 * The gendered Arabic honorific that wraps a dentist's name. Exported because
 * the PDF names its file with the same words the heading prints, and the two
 * drifting apart is exactly the kind of thing nobody notices until a doctor
 * gets a file addressed to the wrong gender.
 */
export function dentistHonorific(gender: 'male' | 'female'): {
    title: string;
    respect: string;
} {
    return gender === 'female'
        ? { title: 'الدكتورة', respect: 'المحترمة' }
        : { title: 'الدكتور', respect: 'المحترم' };
}

/**
 * The date a printed line sorts and displays under: the item's own date,
 * falling back to its order's date when the item carries none — the same
 * fallback the row itself renders.
 *
 * Returns an ISO `yyyy-mm-dd` string, which sorts lexicographically, so
 * plain string comparison orders these correctly.
 */
/**
 * What a payment was handed over as, for the invoice the dentist reads.
 *
 * Two $100 payments a fortnight apart land on different lira figures, and
 * without this the invoice just shows two unequal numbers with no reason
 * given. Naming the rate beside the dollars is what makes the total arguable
 * — the dentist can check it against the day he paid.
 *
 * The number spans are forced `dir="ltr"`: the invoice is RTL, and the bidi
 * algorithm otherwise drags the dollar sign away from its digits.
 */
function PaymentOrigin({ payment }: { payment: DentistPayment }) {
    if (
        payment.currency !== 'USD' ||
        payment.original_amount === null ||
        payment.original_amount === undefined ||
        payment.rate === null ||
        payment.rate === undefined
    ) {
        return <Dash />;
    }

    return (
        <span className="whitespace-nowrap">
            <span dir="ltr" className="font-medium tabular-nums">
                ${formatUsd(payment.original_amount)}
            </span>
            {' بسعر '}
            <span dir="ltr" className="tabular-nums">
                {formatRate(payment.rate)}
            </span>
        </span>
    );
}

function rowDate({ order, item }: { order: Order; item: OrderItem | null }) {
    const value = (item ? itemDate(item) : '') || order.due_date || '';

    // `due_date` serialises as a full ISO timestamp while an item's own date
    // is a bare yyyy-mm-dd; truncating puts both on the same footing.
    return value.slice(0, 10);
}

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

    const ensure = (
        id: number,
        name?: string,
        gender?: 'male' | 'female',
    ): DentistGroup => {
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
        const group = ensure(
            order.dentist_id,
            order.dentist?.name,
            order.dentist?.gender,
        );
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
        ensure(
            payment.dentist_id,
            payment.dentist?.name,
            payment.dentist?.gender,
        ).paymentsTotal += payment.amount;
    }

    for (const group of map.values()) {
        group.due = group.opening + group.ordersTotal - group.paymentsTotal;
        // Sort the whole dentist's lines by date, ACROSS orders. Sorting
        // inside each order isn't enough here: a dentist's rows are several
        // orders concatenated in due_date order, so a short later order
        // still printed its lines below a long earlier one that ran past it.
        group.rows.sort((a, b) => rowDate(a).localeCompare(rowDate(b)));
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
    // Only widen the table when there is something to put in the column, so an
    // all-lira invoice looks exactly as it always did.
    const hasForeignPayment = payments.some(
        (payment) => payment.currency === 'USD',
    );

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
                    groups.map((group) => {
                        const { title, respect } = dentistHonorific(
                            group.gender,
                        );

                        return (
                            <div key={group.id} className="space-y-2">
                                <div className="text-center">
                                    <h4 className="text-2xl font-bold">
                                        {title} : {group.name} {respect}
                                    </h4>
                                </div>
                                <div className="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    اسم المريض
                                                </TableHead>
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
                                                        لا توجد طلبات جديدة في
                                                        هذه الفترة
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
                                                                ) || <Dash />}
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
                                                                {item.type}{' '}
                                                                <span className="text-muted-foreground">
                                                                    ×{' '}
                                                                    {
                                                                        item.quantity
                                                                    }
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
                                                                {formatSyp(
                                                                    item.price ??
                                                                        0,
                                                                )}{' '}
                                                                <span className="text-muted-foreground">
                                                                    ×{' '}
                                                                    {item.quantity ??
                                                                        0}
                                                                </span>
                                                                <ForeignOrigin
                                                                    money={item}
                                                                />
                                                            </TableCell>
                                                            <TableCell className="tabular-nums">
                                                                {itemAmount(
                                                                    item,
                                                                ).toLocaleString(
                                                                    'en-US',
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="whitespace-pre-line">
                                                                {item.notes || (
                                                                    <Dash />
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
                                        <span
                                            className={cn(
                                                PAYMENT_TONE,
                                                'tabular-nums',
                                            )}
                                        >
                                            −
                                            {group.paymentsTotal.toLocaleString(
                                                'en-US',
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between border-t border-border pt-1 font-bold">
                                        <span>المستحق على الطبيب</span>
                                        <DueAmount value={group.due} />
                                    </div>
                                </div>
                            </div>
                        );
                    })
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
                                {hasForeignPayment && (
                                    <TableHead>التفاصيل</TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={hasForeignPayment ? 3 : 2}
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
                                        <TableCell
                                            className={cn(
                                                'font-semibold',
                                                PAYMENT_TONE,
                                            )}
                                        >
                                            {formatSyp(payment.amount)}
                                        </TableCell>
                                        {hasForeignPayment && (
                                            <TableCell className="text-muted-foreground">
                                                <PaymentOrigin
                                                    payment={payment}
                                                />
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
                <div className="space-y-1 rounded-md bg-muted px-3 py-2 text-sm">
                    <div className="flex items-center justify-between font-semibold">
                        <span>إجمالي مدفوعات الفترة</span>
                        <span className={cn(PAYMENT_TONE, 'tabular-nums')}>
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
                        <span
                            className={cn(
                                'font-semibold',
                                PAYMENT_TONE,
                                'tabular-nums',
                            )}
                        >
                            −{totals.payments.toLocaleString('en-US')}
                        </span>
                    </div>
                    <div className="flex justify-between border-t pt-2">
                        <span className="font-bold">الإجمالي المستحق:</span>
                        <DueAmount
                            value={totals.balance}
                            className="text-lg font-bold"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
