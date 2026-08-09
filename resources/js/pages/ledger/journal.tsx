import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Fragment } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DateInput } from '@/components/ui/date-input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    { title: 'قيود اليومية', href: '/ledger/journal' },
];

type JournalLine = {
    id: number;
    debit: number;
    credit: number;
    account: { code: string; name: string };
    dentist: { id: number; name: string } | null;
};

type JournalEntry = {
    id: number;
    entry_date: string;
    description: string;
    lines: JournalLine[];
};

type Props = {
    entries: {
        data: JournalEntry[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    accounts: { code: string; name: string }[];
    filters: { from: string | null; to: string | null; account: string | null };
};

const nf = (value: number) => value.toLocaleString('en-US');

export default function Journal({ entries, accounts, filters }: Props) {
    const go = (
        overrides: Partial<Record<'from' | 'to' | 'account', string>>,
    ) => {
        const next = {
            from: filters.from ?? '',
            to: filters.to ?? '',
            account: filters.account ?? '',
            ...overrides,
        };

        router.get(
            '/ledger/journal',
            Object.fromEntries(
                Object.entries(next).filter(([, value]) => value !== ''),
            ),
            { preserveState: true, preserveScroll: true },
        );
    };

    const goToPage = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="قيود اليومية" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        قيود اليومية
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        كل قيد وكل طرف من طرفيه، لتتبّع أي رقم إلى مصدره
                    </p>
                </div>

                <Card className="py-0">
                    <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs text-muted-foreground">
                                    من
                                </Label>
                                <DateInput
                                    value={filters.from ?? ''}
                                    onChange={(v) => go({ from: v })}
                                    className="w-40"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs text-muted-foreground">
                                    إلى
                                </Label>
                                <DateInput
                                    value={filters.to ?? ''}
                                    onChange={(v) => go({ to: v })}
                                    className="w-40"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs text-muted-foreground">
                                    الحساب
                                </Label>
                                <div className="flex gap-2">
                                    <Select
                                        value={filters.account ?? undefined}
                                        onValueChange={(value) =>
                                            go({ account: value })
                                        }
                                    >
                                        <SelectTrigger className="w-48">
                                            <SelectValue placeholder="الكل" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts.map((account) => (
                                                <SelectItem
                                                    key={account.code}
                                                    value={account.code}
                                                >
                                                    {account.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {filters.account && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => go({ account: '' })}
                                        >
                                            مسح
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>التاريخ</TableHead>
                                <TableHead>البيان / الحساب</TableHead>
                                <TableHead>الطبيب</TableHead>
                                <TableHead className="text-left">
                                    مدين
                                </TableHead>
                                <TableHead className="text-left">
                                    دائن
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entries.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center text-muted-foreground"
                                    >
                                        لا توجد قيود
                                    </TableCell>
                                </TableRow>
                            ) : (
                                entries.data.map((entry) => (
                                    <Fragment key={`entry-${entry.id}`}>
                                        <TableRow className="bg-muted/50">
                                            <TableCell className="text-muted-foreground tabular-nums">
                                                {entry.entry_date}
                                            </TableCell>
                                            <TableCell
                                                colSpan={4}
                                                className="font-medium"
                                            >
                                                {entry.description}
                                            </TableCell>
                                        </TableRow>
                                        {entry.lines.map((line) => (
                                            <TableRow key={`line-${line.id}`}>
                                                <TableCell />
                                                <TableCell className="text-muted-foreground">
                                                    {line.account.name}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {line.dentist?.name ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-left tabular-nums">
                                                    {line.debit > 0
                                                        ? nf(line.debit)
                                                        : ''}
                                                </TableCell>
                                                <TableCell className="text-left tabular-nums">
                                                    {line.credit > 0
                                                        ? nf(line.credit)
                                                        : ''}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </Fragment>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {entries.data.length > 0 && (
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                            صفحة {entries.current_page} من {entries.last_page} (
                            {nf(entries.total)} قيد)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!entries.prev_page_url}
                                onClick={() => goToPage(entries.prev_page_url)}
                            >
                                <ChevronRight className="size-4" />
                                السابق
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!entries.next_page_url}
                                onClick={() => goToPage(entries.next_page_url)}
                            >
                                التالي
                                <ChevronLeft className="size-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
