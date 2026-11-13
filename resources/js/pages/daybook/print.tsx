import { Head } from '@inertiajs/react';
import type { DaybookLine } from '@/components/daybook/types';
import { usePrintReady } from '@/hooks/use-print-ready';
import { formatLongDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';

/**
 * One day's sheet with no app chrome — the page headless Chromium loads to
 * produce the PDF (see DaybookController::pdf). Skips AppLayout for the same
 * reason the other print pages do: it reads `auth.user`, which is null on the
 * signed, unauthenticated route this is served from.
 */

const cell = 'border border-black p-2';

const sumOf = (lines: DaybookLine[], kind: DaybookLine['kind']) => {
    const sum = { SYP: 0, USD: 0 };
    for (const line of lines) {
        if (line.kind === kind) {
            sum[line.currency] += line.value;
        }
    }

    return sum;
};

function Sum({ sum }: { sum: { SYP: number; USD: number } }) {
    return (
        <>
            {formatMoney(sum.SYP)} ل.س
            {sum.USD !== 0 && (
                <>
                    {' + '}
                    <span dir="ltr">{formatMoney(sum.USD, 'USD')}</span>
                </>
            )}
        </>
    );
}

export default function DaybookPrint({
    date,
    lines,
}: {
    date: string;
    lines: DaybookLine[];
}) {
    usePrintReady();

    return (
        <>
            <Head title={`يومية ${date}`} />
            <div dir="rtl" className="pdf-statement p-6">
                <h1 className="mb-1 text-xl font-bold">اليومية</h1>
                <p className="mb-4 text-sm text-muted-foreground">
                    {formatLongDate(date)}
                </p>

                {lines.length === 0 ? (
                    <p className="text-muted-foreground">
                        لا يوجد شيء مسجّل في هذا اليوم.
                    </p>
                ) : (
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className={`${cell} text-right`}>#</th>
                                <th className={`${cell} text-right`}>الطبيب</th>
                                <th className={`${cell} text-right`}>المريض</th>
                                <th className={`${cell} text-right`}>البيان</th>
                                <th className={`${cell} text-right`}>
                                    الأسنان
                                </th>
                                <th className={`${cell} text-left`}>عمل</th>
                                <th className={`${cell} text-left`}>دفعة</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line, index) => {
                                const money = (
                                    <span
                                        dir={
                                            line.currency === 'USD'
                                                ? 'ltr'
                                                : undefined
                                        }
                                    >
                                        {formatMoney(line.value, line.currency)}
                                    </span>
                                );

                                return (
                                    <tr key={line.key}>
                                        <td className={`${cell} tabular-nums`}>
                                            {index + 1}
                                        </td>
                                        <td className={cell}>
                                            {line.dentist_name}
                                        </td>
                                        <td className={cell}>
                                            {line.kind === 'work'
                                                ? line.patient_name
                                                : ''}
                                        </td>
                                        <td className={cell}>
                                            {line.kind === 'work'
                                                ? `${line.type}${line.quantity > 1 ? ` × ${line.quantity}` : ''}`
                                                : `دفعة${line.notes ? ` — ${line.notes}` : ''}`}
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
                                            {line.kind === 'work' && money}
                                        </td>
                                        <td
                                            className={`${cell} text-left tabular-nums`}
                                        >
                                            {line.kind === 'payment' && money}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot>
                            <tr className="font-bold">
                                <td colSpan={5} className={cell}>
                                    المجموع
                                </td>
                                <td
                                    className={`${cell} text-left tabular-nums`}
                                >
                                    <Sum sum={sumOf(lines, 'work')} />
                                </td>
                                <td
                                    className={`${cell} text-left tabular-nums`}
                                >
                                    <Sum sum={sumOf(lines, 'payment')} />
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                )}
            </div>
        </>
    );
}
