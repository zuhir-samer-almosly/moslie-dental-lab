import { cn } from '@/lib/utils';

/**
 * A lira figure read back in dollars — "≈ $208".
 *
 * Deliberately approximate and never stored. The lira is the currency of
 * record: this is the same total looked at through one day's rate, so it moves
 * as the rate moves while the lira figure beside it does not. Renders nothing
 * when no rate is known for that day, rather than inventing one.
 */
export default function ApproxUsd({
    syp,
    rate,
    className,
}: {
    syp: number;
    rate: string | null;
    className?: string;
}) {
    const parsed = rate === null ? NaN : parseFloat(rate);

    if (!Number.isFinite(parsed) || parsed <= 0) {
        return null;
    }

    return (
        <span
            dir="ltr"
            className={cn(
                'text-xs font-normal text-muted-foreground tabular-nums',
                className,
            )}
        >
            ≈ $
            {(syp / parsed).toLocaleString('en-US', {
                maximumFractionDigits: 0,
            })}
        </span>
    );
}
