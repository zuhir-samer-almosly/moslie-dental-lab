import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { type ReactNode, useMemo, useState } from 'react';
import {
    Dash,
    formatDate,
    itemAmount,
    itemDate,
    itemPatient,
    itemTeeth,
    StatusPill,
    TeethChips,
} from '@/components/order-display';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Order } from '@/types';
import { ORDER_STATUSES } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الطلبات',
        href: '/orders',
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

const nf = (value: number) => value.toLocaleString('en-US');

type StatusFilter = 'all' | Order['status'];

export default function OrdersIndex({
    orders,
    month,
}: {
    orders: Order[];
    month: string;
}) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');

    const goToMonth = (next: string) => {
        router.get(
            '/orders',
            { month: next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الطلب؟')) {
            router.delete(`/orders/${id}`);
        }
    };

    // Client-side filtering over dentist + patient names and status.
    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return orders.filter((order) => {
            if (statusFilter !== 'all' && order.status !== statusFilter) {
                return false;
            }
            if (!q) return true;
            const inDentist = order.dentist?.name?.toLowerCase().includes(q);
            const inPatient = (order.items ?? []).some((item) =>
                itemPatient(item).toLowerCase().includes(q),
            );
            return Boolean(inDentist || inPatient);
        });
    }, [orders, search, statusFilter]);

    const totalItems = filtered.reduce(
        (sum, order) => sum + (order.items?.length ?? 0),
        0,
    );
    const totalAmount = filtered.reduce((sum, order) => sum + order.amount, 0);

    const segments: { key: StatusFilter; label: string }[] = [
        { key: 'all', label: 'الكل' },
        ...(Object.entries(ORDER_STATUSES) as [Order['status'], string][]).map(
            ([key, label]) => ({ key, label }),
        ),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الطلبات" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-[26px] font-bold text-foreground">
                            الطلبات
                        </h1>
                        <p className="text-[15px] text-muted-foreground">
                            {filtered.length} طلبات في {MONTH_LABEL(month)}
                        </p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        className="gap-2 rounded-xl px-[22px] text-[15px] font-semibold shadow-[0_2px_6px_rgba(15,118,110,0.25)]"
                    >
                        <Link href="/orders/create">
                            <Plus className="size-[18px]" />
                            إضافة طلب
                        </Link>
                    </Button>
                </div>

                {/* Toolbar: month stepper + search + status filter */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-2 rounded-xl border bg-card p-1.5">
                        <button
                            type="button"
                            onClick={() => goToMonth(shiftMonth(month, -1))}
                            title="الشهر السابق"
                            className="flex size-9 items-center justify-center rounded-lg text-[#435955] transition-colors hover:bg-secondary hover:text-primary"
                        >
                            <ChevronRight className="size-[18px]" />
                        </button>
                        <span className="min-w-[110px] text-center text-[15px] font-semibold text-foreground">
                            {MONTH_LABEL(month)}
                        </span>
                        <button
                            type="button"
                            onClick={() => goToMonth(shiftMonth(month, 1))}
                            title="الشهر التالي"
                            className="flex size-9 items-center justify-center rounded-lg text-[#435955] transition-colors hover:bg-secondary hover:text-primary"
                        >
                            <ChevronLeft className="size-[18px]" />
                        </button>
                    </div>

                    <div className="flex max-w-[340px] flex-1 items-center gap-2.5 rounded-xl border bg-card px-3.5 py-3 text-muted-foreground">
                        <Search className="size-[17px] shrink-0" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="ابحث باسم الطبيب أو المريض..."
                            className="w-full bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5 rounded-xl border bg-card p-1.5">
                        {segments.map((segment) => (
                            <button
                                key={segment.key}
                                type="button"
                                onClick={() => setStatusFilter(segment.key)}
                                className={cn(
                                    'rounded-lg px-4 py-2 text-[13px] transition-colors',
                                    statusFilter === segment.key
                                        ? 'bg-primary font-semibold text-primary-foreground'
                                        : 'font-medium text-[#435955] hover:bg-secondary',
                                )}
                            >
                                {segment.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-2xl border bg-card">
                    {filtered.length === 0 ? (
                        <EmptyState
                            icon={ClipboardList}
                            text="لا توجد طلبات مطابقة"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b bg-[#F8FAFA]">
                                        <Th className="px-[18px]">
                                            اسم الطبيب
                                        </Th>
                                        <Th>اسم المريض</Th>
                                        <Th>العنصر</Th>
                                        <Th>الأسنان</Th>
                                        <Th>التاريخ</Th>
                                        <Th>الحالة</Th>
                                        <Th>المبلغ</Th>
                                        <Th className="px-[18px] text-end">
                                            الإجراءات
                                        </Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((order, orderIndex) => {
                                        const items = order.items ?? [];
                                        const span = Math.max(items.length, 1);
                                        const isLastOrder =
                                            orderIndex === filtered.length - 1;

                                        // Group cells (dentist / status / actions) span
                                        // all of the order's item rows and carry the
                                        // vertical divider between order groups.
                                        const groupBorder = isLastOrder
                                            ? ''
                                            : 'border-b border-[#EDF2F1]';

                                        const dentistCell = (
                                            <td
                                                rowSpan={span}
                                                className={cn(
                                                    'border-s border-[#EDF2F1] px-[18px] py-3.5 align-middle font-semibold text-foreground',
                                                    groupBorder,
                                                )}
                                            >
                                                {!!order.previous_balance && (
                                                    <span className="mb-1 block text-[11px] font-normal text-muted-foreground">
                                                        رصيد سابق مستحق:{' '}
                                                        <span className="font-bold text-[#B45309] tabular-nums">
                                                            {nf(
                                                                order.previous_balance,
                                                            )}
                                                        </span>
                                                    </span>
                                                )}
                                                {order.dentist?.name}
                                            </td>
                                        );
                                        const statusCell = (
                                            <td
                                                rowSpan={span}
                                                className={cn(
                                                    'border-s border-[#EDF2F1] px-3 py-3.5 align-middle',
                                                    groupBorder,
                                                )}
                                            >
                                                <StatusPill
                                                    status={order.status}
                                                />
                                            </td>
                                        );
                                        const actionsCell = (
                                            <td
                                                rowSpan={span}
                                                className={cn(
                                                    'px-[18px] py-3.5 align-middle',
                                                    groupBorder,
                                                )}
                                            >
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={`/orders/${order.id}/edit`}
                                                        title="تعديل"
                                                        className="flex size-[34px] items-center justify-center rounded-[9px] border bg-card text-[#435955] transition-colors hover:border-primary hover:text-primary"
                                                    >
                                                        <Pencil className="size-[15px]" />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        title="حذف"
                                                        onClick={() =>
                                                            handleDelete(
                                                                order.id,
                                                            )
                                                        }
                                                        className="flex size-[34px] items-center justify-center rounded-[9px] border border-[#F5D5DB] bg-card text-[#BE123C] transition-colors hover:border-[#BE123C] hover:bg-[#FDECEE]"
                                                    >
                                                        <Trash2 className="size-[15px]" />
                                                    </button>
                                                </div>
                                            </td>
                                        );

                                        if (items.length === 0) {
                                            return (
                                                <tr key={order.id}>
                                                    {dentistCell}
                                                    <Td className={groupBorder}>
                                                        <Dash />
                                                    </Td>
                                                    <Td className={groupBorder}>
                                                        <Dash />
                                                    </Td>
                                                    <Td className={groupBorder}>
                                                        <Dash />
                                                    </Td>
                                                    <Td
                                                        className={cn(
                                                            'whitespace-nowrap text-[#435955]',
                                                            groupBorder,
                                                        )}
                                                    >
                                                        {formatDate(
                                                            order.due_date,
                                                        ) || <Dash />}
                                                    </Td>
                                                    {statusCell}
                                                    <Td
                                                        className={cn(
                                                            'font-semibold tabular-nums',
                                                            groupBorder,
                                                        )}
                                                    >
                                                        {nf(order.amount)}
                                                    </Td>
                                                    {actionsCell}
                                                </tr>
                                            );
                                        }

                                        return items.map((item, index) => {
                                            const isLastItem =
                                                index === items.length - 1;
                                            const rowBorder =
                                                isLastOrder && isLastItem
                                                    ? ''
                                                    : isLastItem
                                                      ? 'border-b border-[#EDF2F1]'
                                                      : 'border-b border-[#F3F7F6]';
                                            return (
                                                <tr key={item.id}>
                                                    {index === 0 && dentistCell}
                                                    <Td className={rowBorder}>
                                                        {itemPatient(item) || (
                                                            <Dash />
                                                        )}
                                                    </Td>
                                                    <Td
                                                        className={cn(
                                                            'whitespace-nowrap',
                                                            rowBorder,
                                                        )}
                                                    >
                                                        {item.type}{' '}
                                                        <span className="text-muted-foreground">
                                                            × {item.quantity}
                                                        </span>
                                                    </Td>
                                                    <Td className={rowBorder}>
                                                        <TeethChips
                                                            teeth={itemTeeth(
                                                                item,
                                                            )}
                                                        />
                                                    </Td>
                                                    <Td
                                                        className={cn(
                                                            'whitespace-nowrap text-[#435955]',
                                                            rowBorder,
                                                        )}
                                                    >
                                                        {formatDate(
                                                            itemDate(item),
                                                        ) ||
                                                            formatDate(
                                                                order.due_date,
                                                            ) || <Dash />}
                                                    </Td>
                                                    {index === 0 && statusCell}
                                                    <Td
                                                        className={cn(
                                                            'font-semibold tabular-nums',
                                                            rowBorder,
                                                        )}
                                                    >
                                                        {nf(itemAmount(item))}
                                                    </Td>
                                                    {index === 0 && actionsCell}
                                                </tr>
                                            );
                                        });
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Footer summary */}
                    {filtered.length > 0 && (
                        <div className="flex items-center justify-between border-t bg-[#F8FAFA] px-[18px] py-3.5">
                            <span className="text-[13px] text-muted-foreground">
                                {filtered.length} طلبات · {totalItems} عناصر
                            </span>
                            <span className="text-sm text-[#435955]">
                                الإجمالي:{' '}
                                <span className="font-bold text-foreground tabular-nums">
                                    {nf(totalAmount)}
                                </span>{' '}
                                <span className="text-xs text-muted-foreground">
                                    ليرة
                                </span>
                            </span>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function Th({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <th
            className={cn(
                'px-3 py-3.5 text-start text-[13px] font-semibold text-muted-foreground',
                className,
            )}
        >
            {children}
        </th>
    );
}

function Td({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return <td className={cn('px-3 py-3.5', className)}>{children}</td>;
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
