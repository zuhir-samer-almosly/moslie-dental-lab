import { Head, router } from '@inertiajs/react';
import {
    ClipboardList,
    CreditCard,
    Package,
    Receipt,
    TrendingDown,
    TrendingUp,
    UserCog,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatDate, itemAmount } from '@/components/order-display';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DateInput } from '@/components/ui/date-input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type {
    BreadcrumbItem,
    DentistPayment,
    EmployeePayment,
    Expense,
    MaterialPurchase,
    Order,
} from '@/types';
import { EXPENSE_CATEGORIES, ORDER_STATUSES } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'التقرير',
        href: '/report',
    },
];

type Totals = {
    income: number;
    expenses: number;
    net: number;
    orders_value: number;
    orders_count: number;
    salaries: number;
    materials: number;
    general_expenses: number;
};

type ReportProps = {
    orders: Order[];
    payments: DentistPayment[];
    salaries: EmployeePayment[];
    materials: MaterialPurchase[];
    expenses: Expense[];
    totals: Totals;
    filters: { from: string; to: string };
};

const nf = (value: number) => value.toLocaleString('en-US');

const isoDate = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
        d.getDate(),
    ).padStart(2, '0')}`;

const RANGE_LABEL = (from: string, to: string) => {
    const fmt = (v: string) =>
        new Date(`${v}T00:00:00`).toLocaleDateString('ar-SY', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    return from === to ? fmt(from) : `${fmt(from)} — ${fmt(to)}`;
};

export default function ReportIndex({
    orders,
    payments,
    salaries,
    materials,
    expenses,
    totals,
    filters,
}: ReportProps) {
    const go = (from: string, to: string) => {
        router.get(
            '/report',
            { from, to },
            { preserveState: true, preserveScroll: true },
        );
    };

    const presetToday = () => {
        const t = isoDate(new Date());
        go(t, t);
    };
    const presetThisMonth = () => {
        const d = new Date();
        go(
            isoDate(new Date(d.getFullYear(), d.getMonth(), 1)),
            isoDate(new Date(d.getFullYear(), d.getMonth() + 1, 0)),
        );
    };
    const presetLastMonth = () => {
        const d = new Date();
        go(
            isoDate(new Date(d.getFullYear(), d.getMonth() - 1, 1)),
            isoDate(new Date(d.getFullYear(), d.getMonth(), 0)),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="التقرير" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        التقرير
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        كل ما جرى خلال {RANGE_LABEL(filters.from, filters.to)}
                    </p>
                </div>

                {/* Range picker */}
                <Card className="py-0">
                    <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-1.5">
                                <span className="text-xs text-muted-foreground">
                                    من
                                </span>
                                <DateInput
                                    value={filters.from}
                                    onChange={(v) => go(v, filters.to)}
                                    className="w-40"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <span className="text-xs text-muted-foreground">
                                    إلى
                                </span>
                                <DateInput
                                    value={filters.to}
                                    onChange={(v) => go(filters.from, v)}
                                    className="w-40"
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={presetToday}
                            >
                                اليوم
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={presetThisMonth}
                            >
                                هذا الشهر
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={presetLastMonth}
                            >
                                الشهر الماضي
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Headline stats */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="الدخل"
                        value={nf(totals.income)}
                        hint="مدفوعات الأطباء"
                        icon={TrendingUp}
                        tone="emerald"
                    />
                    <StatCard
                        title="المصروفات"
                        value={nf(totals.expenses)}
                        hint="رواتب ومواد ومصاريف"
                        icon={TrendingDown}
                        tone="rose"
                    />
                    <StatCard
                        title="صافي الربح"
                        value={nf(totals.net)}
                        hint={totals.net < 0 ? 'خسارة' : 'ربح'}
                        icon={Wallet}
                        tone={totals.net < 0 ? 'rose' : 'blue'}
                    />
                    <StatCard
                        title="الطلبات"
                        value={nf(totals.orders_count)}
                        hint={`بقيمة ${nf(totals.orders_value)}`}
                        icon={ClipboardList}
                        tone="blue"
                    />
                </div>

                {/* Orders */}
                <ListCard
                    title="الطلبات"
                    icon={ClipboardList}
                    count={orders.length}
                    emptyText="لا توجد طلبات في هذه الفترة"
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>التاريخ</TableHead>
                                <TableHead>الطبيب</TableHead>
                                <TableHead>البنود</TableHead>
                                <TableHead>الحالة</TableHead>
                                <TableHead className="text-end">
                                    المبلغ
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.map((order) => (
                                <TableRow key={order.id}>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {formatDate(order.due_date)}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {order.dentist?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {order.items && order.items.length > 0
                                            ? order.items
                                                  .map((i) => i.type)
                                                  .join('، ')
                                            : '—'}
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-xs text-muted-foreground">
                                            {ORDER_STATUSES[order.status]}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-end font-semibold tabular-nums">
                                        {nf(
                                            order.items &&
                                                order.items.length > 0
                                                ? order.items.reduce(
                                                      (s, i) =>
                                                          s + itemAmount(i),
                                                      0,
                                                  )
                                                : order.amount,
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </ListCard>

                {/* Payments (income) */}
                <ListCard
                    title="المدفوعات (فواتير)"
                    icon={CreditCard}
                    count={payments.length}
                    emptyText="لا توجد مدفوعات في هذه الفترة"
                    tone="emerald"
                >
                    <SimpleRows
                        rows={payments.map((p) => ({
                            key: p.id,
                            date: p.payment_date ?? p.created_at,
                            label: p.dentist?.name ?? '—',
                            amount: p.amount,
                        }))}
                        tone="emerald"
                    />
                </ListCard>

                {/* Salaries */}
                <ListCard
                    title="الرواتب"
                    icon={UserCog}
                    count={salaries.length}
                    emptyText="لا توجد رواتب مدفوعة في هذه الفترة"
                    tone="rose"
                >
                    <SimpleRows
                        rows={salaries.map((s) => ({
                            key: s.id,
                            date: s.payment_date ?? s.created_at,
                            label: s.employee?.name ?? '—',
                            note: s.notes,
                            amount: s.amount,
                        }))}
                        tone="rose"
                    />
                </ListCard>

                {/* Materials */}
                <ListCard
                    title="المواد"
                    icon={Package}
                    count={materials.length}
                    emptyText="لا توجد مشتريات مواد في هذه الفترة"
                    tone="rose"
                >
                    <SimpleRows
                        rows={materials.map((m) => ({
                            key: m.id,
                            date: m.purchase_date ?? m.created_at,
                            label: m.name,
                            note: m.supplier,
                            amount: m.amount,
                        }))}
                        tone="rose"
                    />
                </ListCard>

                {/* General expenses */}
                <ListCard
                    title="المصاريف"
                    icon={Receipt}
                    count={expenses.length}
                    emptyText="لا توجد مصاريف في هذه الفترة"
                    tone="rose"
                >
                    <SimpleRows
                        rows={expenses.map((e) => ({
                            key: e.id,
                            date: e.expense_date ?? e.created_at,
                            label: EXPENSE_CATEGORIES[e.category] ?? e.category,
                            note: e.description,
                            amount: e.amount,
                        }))}
                        tone="rose"
                    />
                </ListCard>
            </div>
        </AppLayout>
    );
}

type Tone = 'blue' | 'emerald' | 'rose';

const toneStyles: Record<Tone, string> = {
    blue: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    emerald: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    rose: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
};

const amountColor: Record<'emerald' | 'rose', string> = {
    emerald: 'text-emerald-600 dark:text-emerald-400',
    rose: 'text-rose-600 dark:text-rose-400',
};

function StatCard({
    title,
    value,
    hint,
    icon: Icon,
    tone,
}: {
    title: string;
    value: string | number;
    hint: string;
    icon: LucideIcon;
    tone: Tone;
}) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="flex items-start justify-between gap-4 p-5">
                <div className="space-y-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        {title}
                    </p>
                    <p className="text-3xl font-bold tracking-tight tabular-nums">
                        {value}
                    </p>
                    <p className="text-xs text-muted-foreground">{hint}</p>
                </div>
                <div
                    className={cn(
                        'flex size-11 shrink-0 items-center justify-center rounded-xl',
                        toneStyles[tone],
                    )}
                >
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}

function ListCard({
    title,
    icon: Icon,
    count,
    emptyText,
    tone = 'blue',
    children,
}: {
    title: string;
    icon: LucideIcon;
    count: number;
    emptyText: string;
    tone?: Tone;
    children: React.ReactNode;
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="flex items-center gap-3 border-b p-5">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        toneStyles[tone],
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <h2 className="font-semibold">{title}</h2>
                <span className="text-xs text-muted-foreground tabular-nums">
                    ({count})
                </span>
            </div>
            <CardContent className="p-0">
                {count === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        {emptyText}
                    </p>
                ) : (
                    children
                )}
            </CardContent>
        </Card>
    );
}

type SimpleRow = {
    key: number;
    date: string;
    label: string;
    note?: string | null;
    amount: number;
};

function SimpleRows({
    rows,
    tone,
}: {
    rows: SimpleRow[];
    tone: 'emerald' | 'rose';
}) {
    return (
        <Table>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.key}>
                        <TableCell className="w-28 whitespace-nowrap text-muted-foreground">
                            {formatDate(row.date)}
                        </TableCell>
                        <TableCell className="font-medium">
                            {row.label}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {row.note || ''}
                        </TableCell>
                        <TableCell
                            className={cn(
                                'text-end font-semibold tabular-nums',
                                amountColor[tone],
                            )}
                        >
                            {nf(row.amount)}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
