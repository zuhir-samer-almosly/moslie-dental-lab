import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DateInput } from '@/components/ui/date-input';
import { Label } from '@/components/ui/label';
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
    opening: number | null;
    lines: {
        data: CashLine[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    filters: { from: string | null; to: string | null };
};

const nf = (value: number) => value.toLocaleString('en-US');

export default function Cash({ balance, opening, lines, filters }: Props) {
    const go = (overrides: Partial<Record<'from' | 'to', string>>) => {
        const next = {
            from: filters.from ?? '',
            to: filters.to ?? '',
            ...overrides,
        };

        router.get(
            '/ledger/cash',
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
            <Head title="الصندوق" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        الصندوق
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        رصيد النقد وكل حركة دخلت أو خرجت منه
                    </p>
                </div>

                <Card className="py-0">
                    <CardContent className="flex flex-wrap items-end gap-3 p-4">
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
                        {(filters.from || filters.to) && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => go({ from: '', to: '' })}
                            >
                                مسح
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2">
                    {opening !== null && (
                        <Card>
                            <CardContent className="flex items-center justify-between p-5">
                                <span className="text-sm text-muted-foreground">
                                    الرصيد الافتتاحي
                                </span>
                                <span className="text-2xl font-bold tabular-nums">
                                    {nf(opening)}
                                </span>
                            </CardContent>
                        </Card>
                    )}
                    <Card className={opening === null ? 'sm:col-span-2' : ''}>
                        <CardContent className="flex items-center justify-between p-5">
                            <span className="text-sm text-muted-foreground">
                                {filters.to
                                    ? `الرصيد حتى ${filters.to}`
                                    : 'الرصيد الحالي'}
                            </span>
                            <span className="text-2xl font-bold tabular-nums">
                                {nf(balance)}
                            </span>
                        </CardContent>
                    </Card>
                </div>

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
                            {lines.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-center text-muted-foreground"
                                    >
                                        لا توجد حركات
                                    </TableCell>
                                </TableRow>
                            ) : (
                                lines.data.map((line) => (
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

                {lines.data.length > 0 && (
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                            صفحة {lines.current_page} من {lines.last_page} (
                            {nf(lines.total)} حركة)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!lines.prev_page_url}
                                onClick={() => goToPage(lines.prev_page_url)}
                            >
                                <ChevronRight className="size-4" />
                                السابق
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!lines.next_page_url}
                                onClick={() => goToPage(lines.next_page_url)}
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
