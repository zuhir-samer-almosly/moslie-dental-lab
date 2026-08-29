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
import { formatMoney } from '@/lib/money';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ميزان المراجعة', href: '/ledger/trial-balance' },
];

type Currency = 'SYP' | 'USD';

type AccountRow = {
    code: string;
    name: string;
    type: string;
    currency: Currency;
    debit: number;
    credit: number;
};

type Props = {
    accounts: AccountRow[];
};

const sum = (rows: AccountRow[], key: 'debit' | 'credit') =>
    rows.reduce((total, row) => total + row[key], 0);

export default function TrialBalance({ accounts }: Props) {
    // Each currency is its own closed system — a SYP row and a USD row never
    // belong in the same sum. Two independent groups, two independent totals,
    // two independent balance checks; nothing here ever adds one to the
    // other. A lab with no dollar activity never produces a `usdAccounts`
    // entry, so it sees exactly one block, same as before this split.
    const sypAccounts = accounts.filter((a) => a.currency !== 'USD');
    const usdAccounts = accounts.filter((a) => a.currency === 'USD');
    const hasUsd = usdAccounts.length > 0;

    const sypDebit = sum(sypAccounts, 'debit');
    const sypCredit = sum(sypAccounts, 'credit');
    const usdDebit = sum(usdAccounts, 'debit');
    const usdCredit = sum(usdAccounts, 'credit');

    // The books are sound only if every currency balances on its own —
    // never derived from a combined debit/credit sum, which could mask a
    // SYP-side gap that happens to cancel against a USD-side one.
    const balanced =
        sypDebit === sypCredit && (!hasUsd || usdDebit === usdCredit);

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
                                    ? 'text-lg font-bold text-emerald-600 dark:text-emerald-400'
                                    : 'text-lg font-bold text-rose-600 dark:text-rose-400'
                            }
                        >
                            {balanced ? 'متوازنة' : 'غير متوازنة'}
                        </span>
                    </CardContent>
                </Card>

                <CurrencyBlock
                    title={hasUsd ? 'بالليرة' : undefined}
                    currency="SYP"
                    accounts={sypAccounts}
                    debit={sypDebit}
                    credit={sypCredit}
                />

                {hasUsd && (
                    <CurrencyBlock
                        title="بالدولار"
                        currency="USD"
                        accounts={usdAccounts}
                        debit={usdDebit}
                        credit={usdCredit}
                    />
                )}
            </div>
        </AppLayout>
    );
}

function CurrencyBlock({
    title,
    currency,
    accounts,
    debit,
    credit,
}: {
    title?: string;
    currency: Currency;
    accounts: AccountRow[];
    debit: number;
    credit: number;
}) {
    const isUsd = currency === 'USD';

    return (
        <div className="space-y-2">
            {title && (
                <h2 className="text-sm font-semibold text-muted-foreground">
                    {title}
                </h2>
            )}
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
                        {accounts.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="text-center text-muted-foreground"
                                >
                                    لا توجد حسابات
                                </TableCell>
                            </TableRow>
                        ) : (
                            <>
                                {accounts.map((account) => (
                                    <TableRow key={account.code}>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {account.code}
                                        </TableCell>
                                        <TableCell>
                                            {account.name}
                                        </TableCell>
                                        <TableCell className="text-left tabular-nums">
                                            <span
                                                dir={
                                                    isUsd ? 'ltr' : undefined
                                                }
                                            >
                                                {formatMoney(
                                                    account.debit,
                                                    currency,
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-left tabular-nums">
                                            <span
                                                dir={
                                                    isUsd ? 'ltr' : undefined
                                                }
                                            >
                                                {formatMoney(
                                                    account.credit,
                                                    currency,
                                                )}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="font-bold">
                                    <TableCell colSpan={2}>
                                        الإجمالي
                                    </TableCell>
                                    <TableCell className="text-left tabular-nums">
                                        <span
                                            dir={isUsd ? 'ltr' : undefined}
                                        >
                                            {formatMoney(debit, currency)}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-left tabular-nums">
                                        <span
                                            dir={isUsd ? 'ltr' : undefined}
                                        >
                                            {formatMoney(credit, currency)}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </>
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
