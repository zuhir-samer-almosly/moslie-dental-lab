import { Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import OrderForm, { today, type OrderItemForm } from '@/components/order-form';
import { Button } from '@/components/ui/button';
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
}: {
    order: Order;
    dentists: Dentist[];
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
        };
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تعديل طلب" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-fit gap-2 text-muted-foreground"
                        onClick={() => window.history.back()}
                    >
                        <ArrowRight className="size-4" />
                        رجوع
                    </Button>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            تعديل طلب
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            تحديث بيانات الطلب وعناصره
                        </p>
                    </div>
                </div>

                <OrderForm
                    dentists={dentists}
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
