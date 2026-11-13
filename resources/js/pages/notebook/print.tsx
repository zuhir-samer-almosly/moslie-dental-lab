import { Head } from '@inertiajs/react';
import {
    describe,
    describePeriod,
    type NotebookProps,
} from '@/components/notebook/types';
import { usePrintReady } from '@/hooks/use-print-ready';
import { formatShortDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';

/**
 * A dentist's notebook with no app chrome — the page headless Chromium loads
 * to produce the PDF (see NotebookController::pdf). Skips AppLayout like the
 * other print pages: it is served from a signed route with no `auth.user`.
 */

const cell = 'border border-black p-2';

// Matches the on-screen notebook and كشف حساب: owed is rose, credit emerald.
const balanceCell = (balance: number) =>
    `${cell} text-left tabular-nums ${balance >= 0 ? 'text-rose-600' : 'text-emerald-600'}`;

export default function NotebookPrint({
    dentist,
    opening,
    closing,
    lines,
    filters,
}: NotebookProps) {
    usePrintReady();

    const ltr = dentist.currency === 'USD' ? 'ltr' : undefined;
    const money = (value: number) => (
        <span dir={ltr}>{formatMoney(value, dentist.currency)}</span>
    );

    return (
        <>
            <Head title={`دفتر ${dentist.name}`} />
            <div dir="rtl" className="pdf-statement p-6">
                <h1 className="mb-1 text-xl font-bold">
                    دفتر الطبيب — {dentist.name}
                </h1>
                <p className="mb-4 text-sm text-muted-foreground">
                    {describePeriod(filters)}
                </p>

                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th className={`${cell} text-right`}>التاريخ</th>
                            <th className={`${cell} text-right`}>البيان</th>
                            <th className={`${cell} text-right`}>المريض</th>
                            <th className={`${cell} text-right`}>الأسنان</th>
                            <th className={`${cell} text-left`}>عمل</th>
                            <th className={`${cell} text-left`}>دفعة</th>
                            <th className={`${cell} text-left`}>الرصيد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colSpan={6} className={`${cell} font-medium`}>
                                الرصيد السابق
                            </td>
                            <td className={balanceCell(opening)}>
                                {money(opening)}
                            </td>
                        </tr>
                        {lines.map((line) => (
                            <tr key={line.key}>
                                <td className={`${cell} tabular-nums`}>
                                    {formatShortDate(line.date)}
                                </td>
                                <td className={cell}>{describe(line)}</td>
                                <td className={cell}>
                                    {line.kind === 'work'
                                        ? line.patient_name
                                        : ''}
                                </td>
                                <td
                                    className={`${cell} tabular-nums`}
                                    dir="ltr"
                                >
                                    {line.kind === 'work'
                                        ? line.selected_teeth.join(' ')
                                        : ''}
                                </td>
                                <td
                                    className={`${cell} text-left tabular-nums`}
                                >
                                    {line.kind === 'work' && money(line.value)}
                                </td>
                                <td
                                    className={`${cell} text-left tabular-nums`}
                                >
                                    {line.kind === 'payment' &&
                                        money(line.value)}
                                </td>
                                <td className={balanceCell(line.balance)}>
                                    {money(line.balance)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="font-bold">
                            <td colSpan={6} className={cell}>
                                الرصيد الحالي
                            </td>
                            <td className={balanceCell(closing)}>
                                {money(closing)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </>
    );
}
