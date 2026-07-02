import { CalendarDays } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

/** ISO (yyyy-mm-dd) → day-first dd/mm/yyyy for display. */
function isoToDisplay(iso: string): string {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
    if (!m) return '';
    return `${m[3]}/${m[2]}/${m[1]}`;
}

type DateInputProps = {
    value: string;
    onChange: (value: string) => void;
    className?: string;
} & Omit<
    React.ComponentProps<'input'>,
    'type' | 'value' | 'onChange' | 'className'
>;

/**
 * Date field that always DISPLAYS day-first d/m/y regardless of browser locale.
 *
 * A native <input type="date"> renders its date in the browser's locale format
 * (m/d/y for US browsers) and the page cannot override that. So we layer a
 * transparent native input on top of a d/m/y display: the native input keeps
 * the calendar popup and `required` validation, while only the day-first span
 * is visible. The underlying value stays ISO yyyy-mm-dd — nothing server-side
 * changes. Matches the D/M/YYYY convention used by `formatDate` app-wide.
 */
function DateInput({
    value,
    onChange,
    className,
    id,
    ...props
}: DateInputProps) {
    return (
        <div
            className={cn(
                'border-input focus-within:border-ring focus-within:ring-ring/50 relative flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] focus-within:ring-[3px] md:text-sm',
                className,
            )}
        >
            {value ? (
                <span dir="ltr" className="text-foreground tabular-nums">
                    {isoToDisplay(value)}
                </span>
            ) : (
                <span className="text-muted-foreground">يوم/شهر/سنة</span>
            )}
            <CalendarDays className="text-muted-foreground pointer-events-none size-4 shrink-0" />
            {/*
                Transparent native input on top: the real focus target (so native
                `required` validation still anchors here) and the calendar trigger.
                Its own locale-formatted text is invisible via opacity-0, so only
                the day-first span above shows through.
            */}
            <input
                id={id}
                type="date"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onClick={(e) => {
                    const el = e.currentTarget;
                    if (typeof el.showPicker === 'function') {
                        try {
                            el.showPicker();
                        } catch {
                            // showPicker throws if already open / not allowed —
                            // safe to ignore, the field still works.
                        }
                    }
                }}
                className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                {...props}
            />
        </div>
    );
}

export { DateInput };
