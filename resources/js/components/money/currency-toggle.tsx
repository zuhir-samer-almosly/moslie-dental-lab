import { cn } from '@/lib/utils';

export type Currency = 'SYP' | 'USD';

/**
 * A two-way currency picker, small enough to sit inside a table row or beside
 * a field label.
 *
 * Deliberately not the app's Combobox: that exists to make long, searchable
 * lists bearable, and a choice between two things reads better as two things
 * you can see at once than as a dropdown you have to open.
 */
export default function CurrencyToggle({
    value,
    onChange,
    className,
}: {
    value: Currency;
    onChange: (currency: Currency) => void;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex shrink-0 overflow-hidden rounded-md border',
                className,
            )}
        >
            {(
                [
                    ['SYP', 'ل.س'],
                    ['USD', '$'],
                ] as const
            ).map(([code, label]) => (
                <button
                    key={code}
                    type="button"
                    onClick={() => onChange(code)}
                    aria-pressed={value === code}
                    className={cn(
                        'w-10 py-1.5 text-xs transition-colors',
                        value === code
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted',
                    )}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}
