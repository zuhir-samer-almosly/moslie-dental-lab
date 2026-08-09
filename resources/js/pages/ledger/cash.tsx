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
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'الصندوق', href: '/ledger/cash' },
];

type CashLine = {
    id: number;
    date: string;
    description: string;
    debit: number;
    credit: number;
};

type Props = {
    balance: number;
    lines: CashLine[];
};

const nf = (value: number) => value.toLocaleString('en-US');

export default function Cash({ balance, lines }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الصندوق" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        الصندوق
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        رصيد النقد الحالي وكل حركة دخلت أو خرجت منه
                    </p>
                </div>

                <Card>
                    <CardContent className="flex items-center justify-between p-5">
                        <span className="text-sm text-muted-foreground">
                            الرصيد الحالي
                        </span>
                        <span className="text-2xl font-bold tabular-nums">
                            {nf(balance)}
                        </span>
                    </CardContent>
                </Card>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>التاريخ</TableHead>
                                <TableHead>البيان</TableHead>
                                <TableHead className="text-left">
                                    وارد
                                </TableHead>
                                <TableHead className="text-left">
                                    صادر
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lines.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-center text-muted-foreground"
                                    >
                                        لا توجد حركات
                                    </TableCell>
                                </TableRow>
                            ) : (
                                lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {line.date}
                                        </TableCell>
                                        <TableCell>
                                            {line.description}
                                        </TableCell>
                                        <TableCell className="text-left tabular-nums">
                                            {nf(line.debit)}
                                        </TableCell>
                                        <TableCell className="text-left tabular-nums">
                                            {nf(line.credit)}
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
