import { Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import OrderForm from '@/components/order-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Dentist } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الطلبات',
        href: '/orders',
    },
    {
        title: 'إضافة طلب',
        href: '/orders/create',
    },
];

export default function OrdersCreate({ dentists }: { dentists: Dentist[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إضافة طلب" />

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
                            إضافة طلب جديد
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            اختر الطبيب وأضف عناصر الطلب
                        </p>
                    </div>
                </div>

                <OrderForm
                    dentists={dentists}
                    method="post"
                    action="/orders"
                    submitLabel="حفظ"
                    initialValues={{
                        dentist_id: '',
                        status: 'pending',
                        notes: '',
                        items: [],
                    }}
                />
            </div>
        </AppLayout>
    );
}
