import { Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import DentistForm from '@/components/dentists/dentist-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'أطباء الأسنان',
        href: '/dentists',
    },
    {
        title: 'إضافة طبيب',
        href: '/dentists/create',
    },
];

export default function DentistsCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إضافة طبيب" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
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
                            إضافة طبيب جديد
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            أدخل بيانات الطبيب وقائمة أسعاره
                        </p>
                    </div>
                </div>

                <div className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="p-5 md:p-6">
                            <DentistForm redirectToIndex />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
