import { formatRate, formatUsd } from '@/lib/money';
import { cn } from '@/lib/utils';

export type ForeignMoney = {
    currency?: 'SYP' | 'USD';
    original_amount?: number | null;
    rate?: string | null;
};

/**
 * What a lira figure was before it was converted — "$100.50 × 13".
 *
 * This is the proof the doctor reads: it says which day's rate his money was
 * taken at, so two $100 payments a fortnight apart visibly land on different
 * lira figures. Renders nothing for money that arrived as lira.
 *
 * Forced `dir="ltr"`: the page is RTL, and without it the bidi algorithm
 * reorders the dollar sign and the rate around the number.
 */
export default function ForeignOrigin({
    money,
    className,
}: {
    money: ForeignMoney;
    className?: string;
}) {
    if (
        money.currency !== 'USD' ||
        money.original_amount === null ||
        money.original_amount === undefined ||
        money.rate === null ||
        money.rate === undefined
    ) {
        return null;
    }

    return (
        <span
            dir="ltr"
            className={cn(
                'block text-xs font-normal text-muted-foreground tabular-nums',
                className,
            )}
        >
            ${formatUsd(money.original_amount)} × {formatRate(money.rate)}
        </span>
    );
}
