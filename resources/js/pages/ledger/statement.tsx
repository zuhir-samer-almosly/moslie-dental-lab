import { Head, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import ForeignOrigin from '@/components/money/foreign-origin';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { DateInput } from '@/components/ui/date-input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'كشف حساب', href: '/ledger/statement' },
];

type StatementLine = {
    id: number;
    date: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
    /** What the money was handed over as. Only payments carry a conversion. */
    currency?: 'SYP' | 'USD';
    original_amount?: number | null;
    rate?: string | null;
};

type Props = {
    statement: {
        opening: number;
        lines: StatementLine[];
        closing: number;
    } | null;
    dentist: { id: number; name: string } | null;
    dentists: { id: number; name: string }[];
    /** The currency `statement` is denominated in — the dentist's own. */
    currency: 'SYP' | 'USD';
    filters: {
        dentist_id: number | null;
        from: string | null;
        to: string | null;
    };
};

export default function Statement({
    statement,
    dentist,
    dentists,
    currency,
    filters,
}: Props) {
    const go = (
        overrides: Partial<Record<'dentist_id' | 'from' | 'to', string>>,
    ) => {
        const next = {
            dentist_id: filters.dentist_id?.toString() ?? '',
            from: filters.from ?? '',
            to: filters.to ?? '',
            ...overrides,
        };

        router.get(
            '/ledger/statement',
            Object.fromEntries(
                Object.entries(next).filter(([, value]) => value !== ''),
            ),
            { preserveState: true, preserveScroll: true },
        );
    };

    const printHref = (() => {
        const params = new URLSearchParams(
            Object.entries({
                dentist_id: filters.dentist_id?.toString() ?? '',
                from: filters.from ?? '',
                to: filters.to ?? '',
            }).filter(([, value]) => value !== ''),
        );

        return `/ledger/statement/pdf?${params.toString()}`;
    })();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="كشف حساب" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        كشف حساب
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        حركة مستحقات طبيب واحد مع رصيد متحرك
                    </p>
                </div>

                <Card className="py-0">
                    <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs text-muted-foreground">
                                    الطبيب
                                </Label>
                                <div className="w-56">
                                    <Combobox
                                        value={
                                            filters.dentist_id?.toString() ??
                                            null
                                        }
                                        onChange={(value) =>
                                            go({ dentist_id: value ?? '' })
                                        }
                                        options={dentists.map((d) => ({
                                            value: d.id.toString(),
                                            label: d.name,
                                        }))}
                                        placeholder="اختر طبيباً"
                                        clearable
                                    />
                                </div>
                            </div>
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
                        </div>

                        {statement && (
                            <Button asChild variant="outline">
                                <a
                                    href={printHref}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer className="h-4 w-4" />
                                    طباعة PDF
                                </a>
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {!statement ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        اختر طبيباً لعرض كشف الحساب
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>التاريخ</TableHead>
                                    <TableHead>البيان</TableHead>
                                    <TableHead className="text-left">
                                        مدين
                                    </TableHead>
                                    <TableHead className="text-left">
                                        دائن
                                    </TableHead>
                                    <TableHead className="text-left">
                                        الرصيد
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow className="bg-muted/50">
                                    <TableCell
                                        colSpan={4}
                                        className="font-medium"
                                    >
                                        رصيد افتتاحي
                                        {dentist && ` — ${dentist.name}`}
                                    </TableCell>
                                    <TableCell className="text-left font-medium tabular-nums">
                                        <span
                                            dir={
                                                currency === 'USD'
                                                    ? 'ltr'
                                                    : undefined
                                            }
                                        >
                                            {formatMoney(
                                                statement.opening,
                                                currency,
                                            )}
                                        </span>
                                    </TableCell>
                                </TableRow>
                                {statement.lines.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-center text-muted-foreground"
                                        >
                                            لا توجد حركات في هذه الفترة
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    statement.lines.map((line) => (
                                        <TableRow key={line.id}>
                                            <TableCell className="text-muted-foreground tabular-nums">
                                                {line.date}
                                            </TableCell>
                                            <TableCell>
                                                {line.description}
                                            </TableCell>
                                            <TableCell className="text-left tabular-nums">
                                                {line.debit > 0 ? (
                                                    <span
                                                        dir={
                                                            currency === 'USD'
                                                                ? 'ltr'
                                                                : undefined
                                                        }
                                                    >
                                                        {formatMoney(
                                                            line.debit,
                                                            currency,
                                                        )}
                                                    </span>
                                                ) : (
                                                    ''
                                                )}
                                            </TableCell>
                                            <TableCell className="text-left tabular-nums">
                                                {line.credit > 0 ? (
                                                    <span
                                                        dir={
                                                            currency === 'USD'
                                                                ? 'ltr'
                                                                : undefined
                                                        }
                                                    >
                                                        {formatMoney(
                                                            line.credit,
                                                            currency,
                                                        )}
                                                    </span>
                                                ) : (
                                                    ''
                                                )}
                                                {currency !== 'USD' && (
                                                    <ForeignOrigin
                                                        money={line}
                                                    />
                                                )}
                                            </TableCell>
                                            <TableCell
                                                className={
                                                    // Inverted from every other ledger page on purpose: this is a
                                                    // receivable statement, so a positive balance means the dentist
                                                    // still OWES money (rose) and a negative one means they're in
                                                    // credit / overpaid (emerald). Do not "fix" this to match
                                                    // trial-balance/cash/journal — the user ruled on this explicitly.
                                                    line.balance >= 0
                                                        ? 'text-left text-rose-600 tabular-nums dark:text-rose-400'
                                                        : 'text-left text-emerald-600 tabular-nums dark:text-emerald-400'
                                                }
                                            >
                                                <span
                                                    dir={
                                                        currency === 'USD'
                                                            ? 'ltr'
                                                            : undefined
                                                    }
                                                >
                                                    {formatMoney(
                                                        line.balance,
                                                        currency,
                                                    )}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                            <TableFooter>
                                <TableRow className="font-bold">
                                    <TableCell colSpan={4}>
                                        الرصيد الختامي
                                    </TableCell>
                                    <TableCell
                                        className={
                                            // Same inversion as the per-line balance above: positive = owed = rose.
                                            statement.closing >= 0
                                                ? 'text-left text-rose-600 tabular-nums dark:text-rose-400'
                                                : 'text-left text-emerald-600 tabular-nums dark:text-emerald-400'
                                        }
                                    >
                                        <span
                                            dir={
                                                currency === 'USD'
                                                    ? 'ltr'
                                                    : undefined
                                            }
                                        >
                                            {formatMoney(
                                                statement.closing,
                                                currency,
                                            )}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
