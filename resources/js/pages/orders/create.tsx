import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import OrderForm from '@/components/order-form';
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
                            إضافة طلب جديد
                        </h1>
                        <p className="text-[15px] text-muted-foreground">
                            الطلبات ← طلب جديد
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
