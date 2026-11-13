import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Banknote, FileText, Wrench } from 'lucide-react';
import {
    describe,
    describePeriod,
    type NotebookProps,
} from '@/components/notebook/types';
import { Button } from '@/components/ui/button';
import { DateInput } from '@/components/ui/date-input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatShortDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

// Same convention as كشف حساب: what he owes is rose, a credit is emerald.
const balanceClass = (balance: number) =>
    balance >= 0 ? 'text-rose-600' : 'text-emerald-600';

/**
 * دفتر الطبيب — one dentist's work and payments in date order, with the
 * balance after every line, the way his paper notebook reads.
 */
export default function NotebookShow({
    dentist,
    opening,
    closing,
    lines,
    filters,
}: NotebookProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'دفاتر الأطباء', href: '/notebook' },
        { title: dentist.name, href: `/notebook/${dentist.id}` },
    ];

    const ltr = dentist.currency === 'USD' ? 'ltr' : undefined;
    const money = (value: number) => formatMoney(value, dentist.currency);

    const query: Record<string, string> = filters.all
        ? { all: '1' }
        : {
              ...(filters.from ? { from: filters.from } : {}),
              ...(filters.to ? { to: filters.to } : {}),
          };

    const go = (next: Record<string, string>) =>
        router.get(`/notebook/${dentist.id}`, next, { preserveScroll: true });

    const setRange = (field: 'from' | 'to', value: string) => {
        const range = {
            from: filters.from ?? '',
            to: filters.to ?? '',
            [field]: value,
        };

        go(
            Object.fromEntries(
                Object.entries(range).filter(([, date]) => date !== ''),
            ),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`دفتر ${dentist.name}`} />

            <div className="mx-auto flex w-full max-w-[980px] flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex items-center gap-3">
                    <Link
                        href="/notebook"
                        aria-label="رجوع إلى دفاتر الأطباء"
                        className="flex size-12 shrink-0 items-center justify-center rounded-[10px] border bg-card transition-colors hover:border-primary"
                    >
                        <ArrowRight className="size-5" />
                    </Link>
                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-2xl font-bold">
                            {dentist.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {describePeriod(filters)}
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="h-12 rounded-[10px] px-4 text-base"
                    >
                        <a
                            href={`/notebook/${dentist.id}/pdf?${new URLSearchParams(query)}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <FileText className="size-5" />
                            PDF
                        </a>
                    </Button>
                </div>

                {/* Period */}
                <div className="grid grid-cols-2 items-end gap-3 rounded-2xl border bg-card p-4 md:flex">
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="from">من</Label>
                        <DateInput
                            id="from"
                            value={filters.from ?? ''}
                            onChange={(value) => setRange('from', value)}
                            className="h-12 rounded-[10px] text-base"
                        />
                    </div>
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="to">إلى</Label>
                        <DateInput
                            id="to"
                            value={filters.to ?? ''}
                            onChange={(value) => setRange('to', value)}
                            className="h-12 rounded-[10px] text-base"
                        />
                    </div>
                    <Button
                        variant={filters.all ? 'outline' : 'default'}
                        onClick={() => go({})}
                        className="h-12 rounded-[10px] px-5 text-base"
                    >
                        هذا الشهر
                    </Button>
                    <Button
                        variant={filters.all ? 'default' : 'outline'}
                        onClick={() => go({ all: '1' })}
                        className="h-12 rounded-[10px] px-5 text-base"
                    >
                        كل السجل
                    </Button>
                </div>

                {/* Balance */}
                <div className="grid grid-cols-2 gap-3">
                    <div className="flex flex-col gap-1 rounded-2xl border bg-card p-4">
                        <span className="text-sm text-muted-foreground">
                            الرصيد السابق
                        </span>
                        <span
                            dir={ltr}
                            className={cn(
                                'text-xl font-bold tabular-nums',
                                balanceClass(opening),
                            )}
                        >
                            {money(opening)}
                        </span>
                    </div>
                    <div className="flex flex-col gap-1 rounded-2xl border bg-card p-4">
                        <span className="text-sm text-muted-foreground">
                            الرصيد الحالي
                        </span>
                        <span
                            dir={ltr}
                            className={cn(
                                'text-xl font-bold tabular-nums',
                                balanceClass(closing),
                            )}
                        >
                            {money(closing)}
                        </span>
                    </div>
                </div>

                {/* Lines */}
                {lines.length === 0 ? (
                    <p className="rounded-2xl border border-dashed p-8 text-center text-muted-foreground">
                        لا يوجد شيء في هذه الفترة.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {lines.map((line) => (
                            <li
                                key={line.key}
                                className="flex items-center gap-3 rounded-2xl border bg-card p-3 md:p-4"
                            >
                                <div
                                    className={cn(
                                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                                        line.kind === 'work'
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                                    )}
                                >
                                    {line.kind === 'work' ? (
                                        <Wrench className="size-5" />
                                    ) : (
                                        <Banknote className="size-5" />
                                    )}
                                </div>

                                <div className="min-w-0 flex-1">
                                    <p className="text-base font-bold">
                                        {describe(line)}
                                    </p>
                                    <p className="text-[15px] text-muted-foreground">
                                        <span className="tabular-nums">
                                            {formatShortDate(line.date)}
                                        </span>
                                        {line.kind === 'work' &&
                                            line.patient_name &&
                                            ` — ${line.patient_name}`}
                                        {line.kind === 'work' &&
                                            line.selected_teeth.length > 0 && (
                                                <>
                                                    {' — '}
                                                    <span
                                                        dir="ltr"
                                                        className="tabular-nums"
                                                    >
                                                        {line.selected_teeth.join(
                                                            ' ',
                                                        )}
                                                    </span>
                                                </>
                                            )}
                                    </p>
                                </div>

                                <div className="flex shrink-0 flex-col items-end gap-0.5">
                                    <span
                                        dir="ltr"
                                        className={cn(
                                            'text-base font-bold tabular-nums',
                                            line.kind === 'payment' &&
                                                'text-emerald-700 dark:text-emerald-400',
                                        )}
                                    >
                                        {line.kind === 'payment' ? '−' : '+'}
                                        {money(line.value)}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        الرصيد{' '}
                                        <span
                                            dir={ltr}
                                            className={cn(
                                                'font-semibold tabular-nums',
                                                balanceClass(line.balance),
                                            )}
                                        >
                                            {money(line.balance)}
                                        </span>
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
