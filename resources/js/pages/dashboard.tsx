import { Head, Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Coins,
    CreditCard,
    HandCoins,
    Package,
    Plus,
    TrendingDown,
    TrendingUp,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatDate, StatusPill } from '@/components/order-display';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, DentistPayment, Order } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'لوحة التحكم',
        href: dashboard().url,
    },
];

type DashboardStats = {
    month: string;
    income: number;
    expenses: number;
    net: number;
    salaries: number;
    materials: number;
    general_expenses: number;
    earned: number;
    outstanding: number;
    cash_balance: number;
    pending_orders: number;
    dentists: number;
    employees: number;
};

type DashboardProps = {
    stats: DashboardStats;
    recentOrders: Order[];
    recentPayments: DentistPayment[];
};

const nf = (value: number) => value.toLocaleString('en-US');
const monthLabel = (month: string) =>
    new Date(`${month}-01T00:00:00`).toLocaleDateString('ar-SY', {
        month: 'long',
        year: 'numeric',
    });
const initial = (name?: string | null) => name?.trim().charAt(0) || '؟';

// Icon-chip tints. Teal is the brand default; the money colors get their own
// semantic tone (green in, red out, amber pending).
const CHIP = {
    teal: 'bg-secondary text-primary',
    green: 'bg-[#E5F5EE] text-[#047857]',
    red: 'bg-[#FDECEE] text-[#BE123C]',
    amber: 'bg-[#FEF3E2] text-[#B45309]',
} as const;

function MoneyCard({
    title,
    value,
    hint,
    icon: Icon,
    variant,
}: {
    title: string;
    value: number;
    hint: string;
    icon: LucideIcon;
    variant: 'income' | 'expense' | 'net-positive' | 'net-negative';
}) {
    return (
        <div className="flex flex-col gap-2.5 rounded-2xl border border-border bg-card p-6">
            <div className="flex items-center justify-between">
                <span className="text-[15px] font-medium text-muted-foreground">
                    {title}
                </span>
                <span
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                        variant === 'income' && CHIP.green,
                        variant === 'expense' && CHIP.red,
                        variant === 'net-negative' && CHIP.red,
                        variant === 'net-positive' && CHIP.green,
                    )}
                >
                    <Icon className="size-5" />
                </span>
            </div>
            <span
                className={cn(
                    'text-[32px] font-bold tabular-nums',
                    variant === 'income' && 'text-[#047857]',
                    variant === 'expense' && 'text-[#BE123C]',
                    variant === 'net-negative' && 'text-[#BE123C]',
                    variant === 'net-positive' && 'text-[#047857]',
                )}
                style={{ letterSpacing: '-0.5px' }}
            >
                {nf(value)}
            </span>
            <span className="text-[13px] text-muted-foreground">
                {hint}
            </span>
        </div>
    );
}

function MiniStat({
    title,
    value,
    icon: Icon,
    tone,
    href,
    valueClassName,
}: {
    title: string;
    value: number;
    icon: LucideIcon;
    tone: keyof typeof CHIP;
    href: string;
    valueClassName?: string;
}) {
    return (
        <Link
            href={href}
            className="group flex items-center gap-3.5 rounded-[14px] border bg-card p-4 transition-colors hover:border-primary"
        >
            <span
                className={cn(
                    'flex size-[42px] shrink-0 items-center justify-center rounded-xl',
                    CHIP[tone],
                )}
            >
                <Icon className="size-5" />
            </span>
            <div className="min-w-0 space-y-0.5">
                <p
                    className={cn(
                        'text-xl leading-none font-bold tabular-nums',
                        valueClassName,
                    )}
                >
                    {nf(value)}
                </p>
                <p className="truncate text-[13px] text-muted-foreground">
                    {title}
                </p>
            </div>
        </Link>
    );
}

const quickActions: {
    href: string;
    label: string;
    icon: LucideIcon;
    tone: keyof typeof CHIP;
}[] = [
    {
        href: '/orders/create',
        label: 'إضافة طلب',
        icon: ClipboardList,
        tone: 'teal',
    },
    {
        href: '/payments/create',
        label: 'إضافة دفعة',
        icon: CreditCard,
        tone: 'green',
    },
    {
        href: '/employees',
        label: 'تسجيل راتب',
        icon: HandCoins,
        tone: 'red',
    },
    {
        href: '/material-purchases/create',
        label: 'تسجيل مادة',
        icon: Package,
        tone: 'amber',
    },
];

export default function Dashboard({
    stats,
    recentOrders,
    recentPayments,
}: DashboardProps) {
    const { auth } = usePage().props;
    const netNegative = stats.net < 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="لوحة التحكم" />
            <div className="flex h-full flex-1 flex-col gap-7 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-[26px] font-bold text-foreground">
                            مرحباً، {auth.user.name} 👋
                        </h1>
                        <p className="text-[15px] text-muted-foreground">
                            هذا ملخص أداء المختبر لشهر {monthLabel(stats.month)}
                        </p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        className="gap-2 rounded-xl px-[22px] text-[15px] font-semibold shadow-[0_2px_6px_rgba(15,118,110,0.25)]"
                    >
                        <Link href="/orders/create">
                            <Plus className="size-[18px]" />
                            إضافة طلب جديد
                        </Link>
                    </Button>
                </div>

                {/* This month's money */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MoneyCard
                        title="الدخل هذا الشهر"
                        value={stats.income}
                        hint="مدفوعات الأطباء هذا الشهر"
                        icon={TrendingUp}
                        variant="income"
                    />
                    <MoneyCard
                        title="الأعمال المنجزة"
                        value={stats.earned}
                        hint="قيمة الطلبات المستحقة هذا الشهر"
                        icon={ClipboardCheck}
                        variant="income"
                    />
                    <MoneyCard
                        title="المصروفات هذا الشهر"
                        value={stats.expenses}
                        hint={`رواتب ${nf(stats.salaries)} + مواد ${nf(stats.materials)} + مصاريف ${nf(stats.general_expenses)}`}
                        icon={TrendingDown}
                        variant="expense"
                    />
                    <MoneyCard
                        title="صافي الربح"
                        value={stats.net}
                        hint={netNegative ? 'خسارة هذا الشهر' : 'ربح هذا الشهر'}
                        icon={Wallet}
                        variant={netNegative ? 'net-negative' : 'net-positive'}
                    />
                </div>

                {/* Operational numbers */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <MiniStat
                        title="رصيد الصندوق"
                        value={stats.cash_balance}
                        icon={Banknote}
                        tone="green"
                        // The cash page, not /finance: this card is an
                        // all-time balance and so is that page's, while the
                        // finance page's رصيد الصندوق is as of a month end.
                        href="/ledger/cash"
                    />
                    <MiniStat
                        title="الرصيد المتبقي على الأطباء"
                        value={stats.outstanding}
                        icon={Coins}
                        tone="amber"
                        href="/outstanding"
                        valueClassName={
                            stats.outstanding < 0
                                ? 'text-[#BE123C]'
                                : 'text-[#B45309]'
                        }
                    />
                    <MiniStat
                        title="طلبات قيد الانتظار"
                        value={stats.pending_orders}
                        icon={Clock}
                        tone="teal"
                        href="/orders"
                    />
                    <MiniStat
                        title="إجمالي الأطباء"
                        value={stats.dentists}
                        icon={Users}
                        tone="teal"
                        href="/dentists"
                    />
                    <MiniStat
                        title="إجمالي الموظفين"
                        value={stats.employees}
                        icon={UserCog}
                        tone="teal"
                        href="/employees"
                    />
                </div>

                {/* Recent activity */}
                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Recent orders */}
                    <div className="overflow-hidden rounded-2xl border bg-card">
                        <div className="flex items-center justify-between border-b border-[#EDF2F1] px-[22px] py-[18px]">
                            <div className="space-y-0.5">
                                <h2 className="text-base font-bold">
                                    أحدث الطلبات
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    آخر 5 طلبات
                                </p>
                            </div>
                            <Link
                                href="/orders"
                                className="text-[13px] font-semibold text-primary"
                            >
                                عرض الكل ←
                            </Link>
                        </div>
                        <div className="p-2">
                            {recentOrders.length === 0 ? (
                                <EmptyState
                                    icon={ClipboardList}
                                    text="لا توجد طلبات بعد"
                                />
                            ) : (
                                <ul>
                                    {recentOrders.map((order) => (
                                        <li key={order.id}>
                                            <Link
                                                href={`/orders/${order.id}/edit`}
                                                className="flex items-center justify-between gap-3 rounded-[10px] px-3.5 py-3 transition-colors hover:bg-muted"
                                            >
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="flex size-[38px] shrink-0 items-center justify-center rounded-full bg-secondary text-[15px] font-bold text-primary">
                                                        {initial(
                                                            order.dentist?.name,
                                                        )}
                                                    </span>
                                                    <div className="min-w-0 space-y-1.5">
                                                        <p className="truncate text-[15px] font-semibold">
                                                            {order.dentist
                                                                ?.name ?? '—'}
                                                        </p>
                                                        <div className="flex items-center gap-2">
                                                            <StatusPill
                                                                status={
                                                                    order.status
                                                                }
                                                                className="px-2 py-0.5 text-[11px]"
                                                            />
                                                            <span className="text-xs text-muted-foreground">
                                                                {formatDate(
                                                                    order.created_at,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <span className="shrink-0 text-[15px] font-bold tabular-nums">
                                                    {nf(order.amount)}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>

                    {/* Recent payments */}
                    <div className="overflow-hidden rounded-2xl border bg-card">
                        <div className="flex items-center justify-between border-b border-[#EDF2F1] px-[22px] py-[18px]">
                            <div className="space-y-0.5">
                                <h2 className="text-base font-bold">
                                    أحدث المدفوعات
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    آخر 5 مدفوعات
                                </p>
                            </div>
                            <Link
                                href="/payments"
                                className="text-[13px] font-semibold text-primary"
                            >
                                عرض الكل ←
                            </Link>
                        </div>
                        <div className="p-2">
                            {recentPayments.length === 0 ? (
                                <EmptyState
                                    icon={CreditCard}
                                    text="لا توجد مدفوعات بعد"
                                />
                            ) : (
                                <ul>
                                    {recentPayments.map((payment) => (
                                        <li
                                            key={payment.id}
                                            className="flex items-center justify-between gap-3 rounded-[10px] px-3.5 py-3"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <span className="flex size-[38px] shrink-0 items-center justify-center rounded-full bg-[#E5F5EE] text-[#047857]">
                                                    <CreditCard className="size-4" />
                                                </span>
                                                <div className="min-w-0 space-y-1.5">
                                                    <p className="truncate text-[15px] font-semibold">
                                                        {payment.dentist
                                                            ?.name ?? '—'}
                                                    </p>
                                                    <span className="text-xs text-muted-foreground">
                                                        {formatDate(
                                                            payment.payment_date ||
                                                                payment.created_at,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                            <span className="shrink-0 text-[15px] font-bold text-[#047857] tabular-nums">
                                                +{nf(payment.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>

                {/* Quick actions */}
                <div className="space-y-3">
                    <div className="space-y-0.5">
                        <h2 className="text-base font-bold">إجراءات سريعة</h2>
                        <p className="text-xs text-muted-foreground">
                            الإجراءات الأكثر استخداماً
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {quickActions.map((action) => (
                            <Link
                                key={action.href}
                                href={action.href}
                                className="group flex items-center gap-3 rounded-[14px] border bg-card p-4 text-[15px] font-semibold transition-colors hover:border-primary hover:bg-muted"
                            >
                                <span
                                    className={cn(
                                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                        CHIP[action.tone],
                                    )}
                                >
                                    <action.icon className="size-[19px]" />
                                </span>
                                {action.label}
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function EmptyState({ icon: Icon, text }: { icon: LucideIcon; text: string }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
            <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <p className="text-sm text-muted-foreground">{text}</p>
        </div>
    );
}
