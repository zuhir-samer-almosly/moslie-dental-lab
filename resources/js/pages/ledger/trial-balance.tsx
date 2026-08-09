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
    { title: 'ميزان المراجعة', href: '/ledger/trial-balance' },
];

type AccountRow = {
    code: string;
    name: string;
    type: string;
    debit: number;
    credit: number;
};

type Props = {
    accounts: AccountRow[];
    totals: { debit: number; credit: number };
    balanced: boolean;
};

const nf = (value: number) => value.toLocaleString('en-US');

export default function TrialBalance({ accounts, totals, balanced }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ميزان المراجعة" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        ميزان المراجعة
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        مجموع المدين يساوي مجموع الدائن في الدفاتر السليمة
                    </p>
                </div>

                <Card>
                    <CardContent className="flex items-center justify-between p-5">
                        <span className="text-sm text-muted-foreground">
                            حالة الدفاتر
                        </span>
                        <span
                            className={
                                balanced
                                    ? 'text-lg font-bold text-[#047857]'
                                    : 'text-lg font-bold text-[#BE123C]'
                            }
                        >
                            {balanced ? 'متوازنة' : 'غير متوازنة'}
                        </span>
                    </CardContent>
                </Card>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>الرمز</TableHead>
                                <TableHead>الحساب</TableHead>
                                <TableHead className="text-left">
                                    مدين
                                </TableHead>
                                <TableHead className="text-left">
                                    دائن
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {accounts.map((account) => (
                                <TableRow key={account.code}>
                                    <TableCell className="text-muted-foreground tabular-nums">
                                        {account.code}
                                    </TableCell>
                                    <TableCell>{account.name}</TableCell>
                                    <TableCell className="text-left tabular-nums">
                                        {nf(account.debit)}
                                    </TableCell>
                                    <TableCell className="text-left tabular-nums">
                                        {nf(account.credit)}
                                    </TableCell>
                                </TableRow>
                            ))}
                            <TableRow className="font-bold">
                                <TableCell colSpan={2}>الإجمالي</TableCell>
                                <TableCell className="text-left tabular-nums">
                                    {nf(totals.debit)}
                                </TableCell>
                                <TableCell className="text-left tabular-nums">
                                    {nf(totals.credit)}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
