import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { normalizeArabic } from '@/lib/arabic';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'دفاتر الأطباء', href: '/notebook' },
];

type Row = {
    id: number;
    name: string;
    currency: 'SYP' | 'USD';
    /** What he owes now, from the ledger. Negative when he has overpaid. */
    balance: number;
};

/** دفاتر الأطباء — pick a dentist to open his notebook. */
export default function NotebookIndex({ dentists }: { dentists: Row[] }) {
    const [query, setQuery] = useState('');
    const needle = normalizeArabic(query.trim());
    const shown = needle
        ? dentists.filter((d) => normalizeArabic(d.name).includes(needle))
        : dentists;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="دفاتر الأطباء" />

            <div className="mx-auto flex w-full max-w-[820px] flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold">دفاتر الأطباء</h1>
                    <p className="text-sm text-muted-foreground">
                        اختر طبيباً لفتح دفتره: كل أعماله ودفعاته والرصيد.
                    </p>
                </div>

                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="ابحث باسم الطبيب..."
                    aria-label="بحث"
                    className="h-12 rounded-[10px] text-base"
                />

                {shown.length === 0 ? (
                    <p className="rounded-2xl border border-dashed p-8 text-center text-muted-foreground">
                        لا يوجد طبيب بهذا الاسم.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {shown.map((dentist) => (
                            <li key={dentist.id}>
                                <Link
                                    href={`/notebook/${dentist.id}`}
                                    className="flex min-h-16 items-center gap-3 rounded-2xl border bg-card px-4 py-3 transition-colors hover:border-primary"
                                >
                                    <span className="min-w-0 flex-1 truncate text-lg font-bold">
                                        {dentist.name}
                                    </span>
                                    <span className="flex flex-col items-end">
                                        <span className="text-xs text-muted-foreground">
                                            الرصيد
                                        </span>
                                        <span
                                            dir={
                                                dentist.currency === 'USD'
                                                    ? 'ltr'
                                                    : undefined
                                            }
                                            className={cn(
                                                'text-base font-bold tabular-nums',
                                                // Same convention as كشف حساب:
                                                // owed is rose, credit emerald.
                                                dentist.balance > 0 &&
                                                    'text-rose-600',
                                                dentist.balance < 0 &&
                                                    'text-emerald-600',
                                            )}
                                        >
                                            {formatMoney(
                                                dentist.balance,
                                                dentist.currency,
                                            )}
                                        </span>
                                    </span>
                                    <ChevronLeft className="size-5 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
