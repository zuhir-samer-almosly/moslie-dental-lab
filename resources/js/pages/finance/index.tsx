import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المالية',
        href: '/finance',
    },
];

type Category = { key: string; label: string; total: number };
type NamedTotal = { name: string; total: number };
type TrendRow = {
    month: string;
    income: number;
    expenses: number;
    net: number;
};

type FinanceProps = {
    month: string;
    income: number;
    expenses: number;
    net: number;
    categories: Category[];
    incomeByDentist: NamedTotal[];
    expensesByEmployee: NamedTotal[];
    trend: TrendRow[];
};

const nf = (value: number) => value.toLocaleString('en-US');

const MONTH_LABEL = (month: string, opts?: Intl.DateTimeFormatOptions) =>
    new Date(`${month}-01T00:00:00`).toLocaleDateString('ar-SY', {
        month: 'long',
        year: 'numeric',
        ...opts,
    });

function shiftMonth(month: string, delta: number) {
    const [y, m] = month.split('-').map(Number);
    const date = new Date(y, m - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export default function FinanceIndex({
    month,
    income,
    expenses,
    net,
    categories,
    incomeByDentist,
    expensesByEmployee,
    trend,
}: FinanceProps) {
    const goToMonth = (next: string) => {
        router.get(
            '/finance',
            { month: next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const maxTrend = Math.max(
        1,
        ...trend.map((t) => Math.max(t.income, t.expenses)),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="المالية" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header + month picker */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            المالية
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            الدخل والمصروفات وصافي الربح لشهر{' '}
                            {MONTH_LABEL(month)}
                        </p>
                    </div>
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
                </div>

                {/* Headline stats */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        title="الدخل"
                        value={nf(income)}
                        hint="مدفوعات الأطباء هذا الشهر"
                        icon={TrendingUp}
                        tone="emerald"
                    />
                    <StatCard
                        title="المصروفات"
                        value={nf(expenses)}
                        hint="الرواتب وما في حكمها"
                        icon={TrendingDown}
                        tone="rose"
                    />
                    <StatCard
                        title="صافي الربح"
                        value={nf(net)}
                        hint={net < 0 ? 'خسارة هذا الشهر' : 'ربح هذا الشهر'}
                        icon={Wallet}
                        tone={net < 0 ? 'rose' : 'blue'}
                    />
                </div>

                {/* Expense categories */}
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="border-b p-5">
                        <h2 className="font-semibold">المصروفات حسب البند</h2>
                        <p className="text-xs text-muted-foreground">
                            ستضاف المواد كبند مستقل لاحقاً
                        </p>
                    </div>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>البند</TableHead>
                                    <TableHead className="text-end">
                                        المبلغ
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.map((c) => (
                                    <TableRow key={c.key}>
                                        <TableCell className="font-medium">
                                            {c.label}
                                        </TableCell>
                                        <TableCell className="text-end font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                                            {nf(c.total)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow>
                                    <TableCell className="font-bold">
                                        الإجمالي
                                    </TableCell>
                                    <TableCell className="text-end font-bold tabular-nums">
                                        {nf(expenses)}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Breakdown lists */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <BreakdownCard
                        title="الدخل حسب الطبيب"
                        rows={incomeByDentist}
                        emptyText="لا يوجد دخل في هذا الشهر"
                        tone="emerald"
                    />
                    <BreakdownCard
                        title="الرواتب حسب الموظف"
                        rows={expensesByEmployee}
                        emptyText="لا توجد رواتب في هذا الشهر"
                        tone="rose"
                    />
                </div>

                {/* Trend */}
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="border-b p-5">
                        <h2 className="font-semibold">آخر 6 أشهر</h2>
                        <p className="text-xs text-muted-foreground">
                            الدخل مقابل المصروفات وصافي الربح
                        </p>
                    </div>
                    <CardContent className="space-y-4 p-5">
                        {trend.map((row) => (
                            <div key={row.month} className="space-y-1.5">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium">
                                        {MONTH_LABEL(row.month, {
                                            month: 'short',
                                        })}
                                    </span>
                                    <span
                                        className={cn(
                                            'font-semibold tabular-nums',
                                            row.net < 0
                                                ? 'text-rose-600 dark:text-rose-400'
                                                : 'text-emerald-600 dark:text-emerald-400',
                                        )}
                                    >
                                        {nf(row.net)}
                                    </span>
                                </div>
                                <div className="flex h-2 gap-1 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-emerald-500/70"
                                        style={{
                                            width: `${(row.income / maxTrend) * 50}%`,
                                        }}
                                    />
                                    <div
                                        className="h-full rounded-full bg-rose-500/70"
                                        style={{
                                            width: `${(row.expenses / maxTrend) * 50}%`,
                                        }}
                                    />
                                </div>
                                <div className="flex justify-between text-xs text-muted-foreground tabular-nums">
                                    <span>دخل {nf(row.income)}</span>
                                    <span>مصروف {nf(row.expenses)}</span>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

type StatTone = 'blue' | 'emerald' | 'rose';

const toneStyles: Record<StatTone, string> = {
    blue: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    emerald: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    rose: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
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
    tone: StatTone;
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

function BreakdownCard({
    title,
    rows,
    emptyText,
    tone,
}: {
    title: string;
    rows: NamedTotal[];
    emptyText: string;
    tone: 'emerald' | 'rose';
}) {
    const color =
        tone === 'emerald'
            ? 'text-emerald-600 dark:text-emerald-400'
            : 'text-rose-600 dark:text-rose-400';

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="border-b p-5">
                <h2 className="font-semibold">{title}</h2>
            </div>
            <CardContent className="p-0">
                {rows.length === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        {emptyText}
                    </p>
                ) : (
                    <Table>
                        <TableBody>
                            {rows.map((row, i) => (
                                <TableRow key={`${row.name}-${i}`}>
                                    <TableCell className="font-medium">
                                        {row.name}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-end font-semibold tabular-nums',
                                            color,
                                        )}
                                    >
                                        {nf(row.total)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
