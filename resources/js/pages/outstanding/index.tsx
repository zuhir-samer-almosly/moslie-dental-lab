import { Head } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
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
        title: 'الأرصدة المستحقة',
        href: '/outstanding',
    },
];

type DentistBalance = {
    id: number;
    name: string;
    phone: string | null;
    orders_total: number;
    payments_total: number;
    outstanding: number;
};

type OutstandingProps = {
    dentists: DentistBalance[];
    totalOutstanding: number;
};

const nf = (value: number) => value.toLocaleString('en-US');

export default function OutstandingIndex({
    dentists,
    totalOutstanding,
}: OutstandingProps) {
    const owing = dentists.filter((d) => d.outstanding > 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الأرصدة المستحقة" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        الأرصدة المستحقة
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        إجمالي ما لم يُدفع لكل طبيب (كل الطلبات ناقص كل
                        المدفوعات)
                    </p>
                </div>

                <Card>
                    <CardContent className="flex items-center justify-between p-5">
                        <span className="text-sm text-muted-foreground">
                            إجمالي المستحق على جميع الأطباء
                        </span>
                        <span className="text-2xl font-bold tabular-nums">
                            {nf(totalOutstanding)}
                        </span>
                    </CardContent>
                </Card>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>الطبيب</TableHead>
                                <TableHead>الهاتف</TableHead>
                                <TableHead className="text-left">
                                    إجمالي الطلبات
                                </TableHead>
                                <TableHead className="text-left">
                                    إجمالي المدفوعات
                                </TableHead>
                                <TableHead className="text-left">
                                    الرصيد المستحق
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {owing.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center text-muted-foreground"
                                    >
                                        لا توجد أرصدة مستحقة
                                    </TableCell>
                                </TableRow>
                            ) : (
                                owing.map((dentist) => (
                                    <TableRow key={dentist.id}>
                                        <TableCell className="font-medium">
                                            {dentist.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {dentist.phone ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-left tabular-nums">
                                            {nf(dentist.orders_total)}
                                        </TableCell>
                                        <TableCell className="text-left tabular-nums">
                                            {nf(dentist.payments_total)}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-left font-semibold tabular-nums',
                                                'text-red-600 dark:text-red-400',
                                            )}
                                        >
                                            {nf(dentist.outstanding)}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
