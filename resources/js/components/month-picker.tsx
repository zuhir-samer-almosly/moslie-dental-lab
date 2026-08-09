import { CalendarDays } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type MonthPickerProps = {
    /** Month in `YYYY-MM` form. */
    value: string;
    onChange: (month: string) => void;
    className?: string;
};

/**
 * Month picker showing the month in Arabic.
 *
 * The native `<input type="month">` renders its label in the *browser's*
 * locale, not the page's, so it shows "August 2026" on an English browser.
 * We keep the native input (and its picker) but hide it behind a button that
 * carries the Arabic label.
 */
export function MonthPicker({ value, onChange, className }: MonthPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);

    const label = new Date(`${value}-01T00:00:00`).toLocaleDateString('ar-SY', {
        month: 'long',
        year: 'numeric',
    });

    const openPicker = () => {
        const input = inputRef.current;
        if (!input) return;

        input.focus();
        input.showPicker?.();
    };

    return (
        <div className={cn('relative w-44', className)}>
            <input
                ref={inputRef}
                type="month"
                value={value}
                aria-label="اختيار الشهر"
                className="pointer-events-none absolute inset-0 size-full opacity-0"
                onChange={(event) => {
                    if (event.target.value) {
                        onChange(event.target.value);
                    }
                }}
            />
            <Button
                type="button"
                variant="outline"
                className="w-full justify-between font-normal"
                onClick={openPicker}
            >
                <span>{label}</span>
                <CalendarDays className="size-4 opacity-60" />
            </Button>
        </div>
    );
}
