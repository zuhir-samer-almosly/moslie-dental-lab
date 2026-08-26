import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import OrderForm, { today, type OrderItemForm } from '@/components/order-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Dentist, Order } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الطلبات',
        href: '/orders',
    },
    {
        title: 'تعديل طلب',
        href: '#',
    },
];

export default function OrdersEdit({
    order,
    dentists,
    todayRate,
}: {
    order: Order;
    dentists: Dentist[];
    todayRate: string | null;
}) {
    const items: OrderItemForm[] = (order.items || []).map((item) => {
        const meta = item.meta as Record<string, unknown> | null;
        return {
            type: item.type,
            patient_name: (meta?.patient_name as string) || '',
            quantity: item.quantity,
            price: item.price,
            notes: item.notes || '',
            date: (meta?.date as string) || today(),
            selected_teeth: (meta?.selected_teeth as number[]) || [],
            currency: item.currency ?? 'SYP',
            original_amount: item.original_amount ?? 0,
            rate: item.rate ?? '',
        };
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تعديل طلب" />

            {/* No overflow-* here: it would make this a scroll container and
                break the sticky items bar inside OrderForm. */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex max-w-[920px] items-center gap-3.5">
                    <Link
                        href="/orders"
                        title="رجوع إلى الطلبات"
                        className="flex size-10 items-center justify-center rounded-[10px] border bg-card text-[#435955] transition-colors hover:border-primary hover:text-primary"
                    >
                        <ArrowRight className="size-[18px]" />
                    </Link>
                    <div className="space-y-1">
                        <h1 className="text-[26px] font-bold text-foreground">
                            تعديل طلب
                        </h1>
                        <p className="text-[15px] text-muted-foreground">
                            الطلبات ← تعديل طلب
                        </p>
                    </div>
                </div>

                <OrderForm
                    dentists={dentists}
                    todayRate={todayRate}
                    method="put"
                    action={`/orders/${order.id}`}
                    submitLabel="تحديث"
                    initialValues={{
                        dentist_id: order.dentist_id.toString(),
                        status: order.status,
                        notes: order.notes || '',
                        items,
                    }}
                />
            </div>
        </AppLayout>
    );
}
