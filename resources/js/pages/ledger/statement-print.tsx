import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import ForeignOrigin from '@/components/money/foreign-origin';

/**
 * The bare statement document, with no app chrome around it — this is the
 * page headless Chromium loads to produce the PDF (see
 * LedgerController::statementPdf). Deliberately skips AppLayout: the sidebar
 * and header are noise on paper, and the layout reads `auth.user`, which is
 * null on the signed, unauthenticated route this page is served from.
 */

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
    filters: {
        dentist_id: number | null;
        from: string | null;
        to: string | null;
    };
};

const nf = (value: number) => value.toLocaleString('en-US');

export default function StatementPrint({ statement, dentist, filters }: Props) {
    // No appearance cookie is sent by the headless browser, so the page would
    // otherwise follow the renderer's system preference. Paper is always light.
    useEffect(() => {
        document.documentElement.classList.remove('dark');
    }, []);

    return (
        <>
            <Head title="كشف حساب" />
            <div dir="rtl" className="p-6">
                <h1 className="mb-1 text-xl font-bold">
                    كشف حساب{dentist && ` — ${dentist.name}`}
                </h1>
                {(filters.from || filters.to) && (
                    <p className="mb-4 text-sm text-muted-foreground">
                        من {filters.from ?? '—'} إلى {filters.to ?? '—'}
                    </p>
                )}

                {!statement ? (
                    <p className="text-muted-foreground">لا يوجد كشف حساب</p>
                ) : (
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="border border-black p-2 text-right">
                                    التاريخ
                                </th>
                                <th className="border border-black p-2 text-right">
                                    البيان
                                </th>
                                <th className="border border-black p-2 text-left">
                                    مدين
                                </th>
                                <th className="border border-black p-2 text-left">
                                    دائن
                                </th>
                                <th className="border border-black p-2 text-left">
                                    الرصيد
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    colSpan={4}
                                    className="border border-black p-2 font-medium"
                                >
                                    رصيد افتتاحي
                                </td>
                                <td className="border border-black p-2 text-left tabular-nums">
                                    {nf(statement.opening)}
                                </td>
                            </tr>
                            {statement.lines.map((line) => (
                                <tr key={line.id}>
                                    <td className="border border-black p-2 tabular-nums">
                                        {line.date}
                                    </td>
                                    <td className="border border-black p-2">
                                        {line.description}
                                    </td>
                                    <td className="border border-black p-2 text-left tabular-nums">
                                        {line.debit > 0 ? nf(line.debit) : ''}
                                    </td>
                                    <td className="border border-black p-2 text-left tabular-nums">
                                        {line.credit > 0 ? nf(line.credit) : ''}
                                        <ForeignOrigin money={line} />
                                    </td>
                                    <td
                                        className={
                                            // Matches statement.tsx's inverted convention so the PDF agrees with
                                            // the on-screen page: positive (owed) = rose, negative (credit) =
                                            // emerald. Do not "fix" this to the other ledger pages' convention.
                                            line.balance >= 0
                                                ? 'border border-black p-2 text-left text-rose-600 tabular-nums'
                                                : 'border border-black p-2 text-left text-emerald-600 tabular-nums'
                                        }
                                    >
                                        {nf(line.balance)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="font-bold">
                                <td
                                    colSpan={4}
                                    className="border border-black p-2"
                                >
                                    الرصيد الختامي
                                </td>
                                <td
                                    className={
                                        statement.closing >= 0
                                            ? 'border border-black p-2 text-left text-rose-600 tabular-nums'
                                            : 'border border-black p-2 text-left text-emerald-600 tabular-nums'
                                    }
                                >
                                    {nf(statement.closing)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                )}
            </div>
        </>
    );
}
