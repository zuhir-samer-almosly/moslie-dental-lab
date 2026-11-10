import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const pad = (n: number) => String(n).padStart(2, '0');

const toIso = (year: number, month: number, day: number) =>
    `${year}-${pad(month)}-${pad(day)}`;

/** A real calendar date, or null — 31/02 and 00/00 are refused. */
function validIso(year: number, month: number, day: number): string | null {
    const date = new Date(year, month - 1, day);

    return date.getFullYear() === year &&
        date.getMonth() === month - 1 &&
        date.getDate() === day &&
        year >= 1900 &&
        year <= 2999
        ? toIso(year, month, day)
        : null;
}

/** ISO (yyyy-mm-dd) → day-first dd/mm/yyyy for display. */
function isoToDisplay(iso: string): string {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
    if (!m) return '';
    return `${m[3]}/${m[2]}/${m[1]}`;
}

function isoParts(iso: string) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);

    return m
        ? { year: Number(m[1]), month: Number(m[2]), day: Number(m[3]) }
        : null;
}

function todayParts() {
    const now = new Date();

    return {
        year: now.getFullYear(),
        month: now.getMonth() + 1,
        day: now.getDate(),
    };
}

function shiftDays(iso: string, days: number): string {
    const parts = isoParts(iso) ?? todayParts();
    const date = new Date(parts.year, parts.month - 1, parts.day + days);

    return toIso(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

/**
 * What a typed date means. Day-first, and forgiving: the month and year may
 * be left out and default to the current ones, so "5" is the 5th of this
 * month and "5/11" is the 5th of November this year.
 *
 * Returns '' for an emptied field and null for text that is not a date.
 */
function parseTyped(text: string): string | null {
    const parts = text.split(/\D+/).filter(Boolean);
    if (parts.length === 0) {
        return '';
    }

    const now = todayParts();
    let [day, month, year] = parts.map(Number);

    // One unbroken run of digits: 0511 or 05112026.
    if (parts.length === 1 && parts[0].length > 2) {
        const digits = parts[0];
        if (![4, 6, 8].includes(digits.length)) {
            return null;
        }
        day = Number(digits.slice(0, 2));
        month = Number(digits.slice(2, 4));
        year = digits.length > 4 ? Number(digits.slice(4)) : now.year;
    }

    month ??= now.month;
    year ??= now.year;
    if (year < 100) {
        year += 2000;
    }

    return parts.length > 3 ? null : validIso(year, month, day);
}

/**
 * Put the slashes in while digits are typed, so 05112026 becomes 05/11/2026
 * without reaching for the slash key. Text that already carries its own
 * separators ("5/11") is left exactly as typed.
 */
function withSlashes(raw: string): string {
    const continued = /^(\d{2})\/(\d{3,})$/.exec(raw);
    const digits = /^\d+$/.test(raw)
        ? raw
        : continued
          ? continued[1] + continued[2]
          : null;

    if (digits === null || digits.length <= 2) {
        return raw;
    }

    return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)]
        .filter(Boolean)
        .join('/');
}

// The Levantine month names the rest of the app shows (ar-SY).
const MONTHS = [
    'كانون الثاني',
    'شباط',
    'آذار',
    'نيسان',
    'أيار',
    'حزيران',
    'تموز',
    'آب',
    'أيلول',
    'تشرين الأول',
    'تشرين الثاني',
    'كانون الأول',
];

// The week starts on Saturday.
const WEEKDAYS = ['س', 'ح', 'ن', 'ث', 'ر', 'خ', 'ج'];

function Calendar({
    value,
    onPick,
    clearable,
}: {
    value: string;
    onPick: (iso: string) => void;
    clearable: boolean;
}) {
    const today = todayParts();
    const selected = isoParts(value);
    const [view, setView] = React.useState(() => ({
        year: (selected ?? today).year,
        month: (selected ?? today).month,
    }));

    const move = (months: number) => {
        const date = new Date(view.year, view.month - 1 + months, 1);
        setView({ year: date.getFullYear(), month: date.getMonth() + 1 });
    };

    // getDay(): Sunday = 0 … Saturday = 6; shift so Saturday leads the row.
    const offset = (new Date(view.year, view.month - 1, 1).getDay() + 1) % 7;
    const daysInMonth = new Date(view.year, view.month, 0).getDate();
    const todayIso = toIso(today.year, today.month, today.day);

    const navClass =
        'flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground';
    const quickClass =
        'rounded-lg px-2.5 py-1.5 text-xs font-medium text-primary hover:bg-secondary';

    return (
        <div className="w-[280px] space-y-2" dir="rtl">
            <div className="flex items-center justify-between">
                <button
                    type="button"
                    className={navClass}
                    onClick={() => move(-1)}
                    aria-label="الشهر السابق"
                >
                    <ChevronRight className="size-4" />
                </button>
                <span className="text-sm font-semibold">
                    {MONTHS[view.month - 1]}{' '}
                    <span className="tabular-nums">{view.year}</span>
                </span>
                <button
                    type="button"
                    className={navClass}
                    onClick={() => move(1)}
                    aria-label="الشهر التالي"
                >
                    <ChevronLeft className="size-4" />
                </button>
            </div>

            <div className="grid grid-cols-7 text-center">
                {WEEKDAYS.map((day) => (
                    <span
                        key={day}
                        className="py-1 text-xs font-medium text-muted-foreground"
                    >
                        {day}
                    </span>
                ))}
                {Array.from({ length: offset }, (_, i) => (
                    <span key={`blank-${i}`} />
                ))}
                {Array.from({ length: daysInMonth }, (_, i) => {
                    const iso = toIso(view.year, view.month, i + 1);

                    return (
                        <button
                            key={iso}
                            type="button"
                            onClick={() => onPick(iso)}
                            className={cn(
                                'mx-auto flex size-9 items-center justify-center rounded-lg text-sm tabular-nums hover:bg-accent',
                                iso === todayIso &&
                                    'font-bold text-primary ring-1 ring-primary/40',
                                iso === value &&
                                    'bg-primary font-bold text-primary-foreground hover:bg-primary',
                            )}
                        >
                            {i + 1}
                        </button>
                    );
                })}
            </div>

            <div className="flex items-center gap-1 border-t pt-2">
                <button
                    type="button"
                    className={quickClass}
                    onClick={() => onPick(todayIso)}
                >
                    اليوم
                </button>
                <button
                    type="button"
                    className={quickClass}
                    onClick={() => onPick(shiftDays(todayIso, -1))}
                >
                    أمس
                </button>
                {clearable && value !== '' && (
                    <button
                        type="button"
                        className={cn(
                            quickClass,
                            'ms-auto text-muted-foreground',
                        )}
                        onClick={() => onPick('')}
                    >
                        مسح
                    </button>
                )}
            </div>
        </div>
    );
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
 * Date field built for typing: day-first, in Latin digits, whatever the
 * browser's locale.
 *
 * Type the digits and the slashes appear (05112026 → 05/11/2026); leave out
 * the month or year and the current one is assumed ("5" is the 5th of this
 * month); ↑ and ↓ step a day. The calendar button opens an Arabic calendar
 * for picking with the mouse instead.
 *
 * The value handed to `onChange` stays ISO yyyy-mm-dd — nothing server-side
 * changes. Matches the D/M/YYYY convention used by `formatDate` app-wide.
 *
 * This replaced a transparent native `<input type="date">`: its calendar came
 * up in the browser's language, and what was being typed into its hidden
 * segments could not be seen.
 */
function DateInput({
    value,
    onChange,
    className,
    required,
    disabled,
    onKeyDown,
    onBlur,
    onFocus,
    ...props
}: DateInputProps) {
    // Non-null only while the text is being edited; otherwise the field shows
    // the committed value, so a change from outside is always reflected.
    const [draft, setDraft] = React.useState<string | null>(null);
    const justFocused = React.useRef(false);

    /** Settle the typed text: a date is kept, anything else is dropped. */
    const commit = () => {
        if (draft === null) {
            return;
        }

        const parsed = parseTyped(draft);
        setDraft(null);

        if (parsed !== null && parsed !== value && !(parsed === '' && required)) {
            onChange(parsed);
        }
    };

    const pending = draft !== null && draft !== isoToDisplay(value);

    return (
        <div
            className={cn(
                'relative flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 md:text-sm',
                disabled && 'pointer-events-none opacity-50',
                className,
            )}
        >
            <input
                type="text"
                inputMode="numeric"
                autoComplete="off"
                dir="ltr"
                placeholder="يوم/شهر/سنة"
                required={required}
                disabled={disabled}
                value={draft ?? isoToDisplay(value)}
                onChange={(e) => {
                    const text = withSlashes(e.target.value);
                    setDraft(text);

                    // A complete date takes effect at once, so nothing is
                    // left waiting on a blur.
                    if (/^\d{2}\/\d{2}\/\d{4}$/.test(text)) {
                        const parsed = parseTyped(text);
                        if (parsed && parsed !== value) {
                            onChange(parsed);
                        }
                    }
                }}
                onFocus={(e) => {
                    // Selected, so typing replaces the date instead of
                    // having to clear it first.
                    e.currentTarget.select();
                    justFocused.current = true;
                    onFocus?.(e);
                }}
                onMouseUp={(e) => {
                    // The click that focused the field would otherwise drop
                    // a caret into it and undo the selection made above.
                    if (justFocused.current) {
                        justFocused.current = false;
                        e.preventDefault();
                    }
                }}
                onBlur={(e) => {
                    justFocused.current = false;
                    commit();
                    onBlur?.(e);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        setDraft(null);
                        onChange(
                            shiftDays(value, e.key === 'ArrowUp' ? 1 : -1),
                        );
                        // Re-select once the new date is drawn, so digits
                        // typed next replace it rather than trail after it.
                        const input = e.currentTarget;
                        requestAnimationFrame(() => input.select());
                    } else if (e.key === 'Enter' && pending) {
                        // The first Enter only settles a half-typed date. Let
                        // through, it would reach a form handler still holding
                        // the old value — and submit, or add a line, with it.
                        e.preventDefault();
                        e.stopPropagation();
                        commit();

                        return;
                    }

                    onKeyDown?.(e);
                }}
                className="w-full min-w-0 flex-1 bg-transparent text-right text-foreground tabular-nums outline-none placeholder:text-muted-foreground"
                {...props}
            />

            <Popover className="flex">
                <PopoverButton
                    type="button"
                    tabIndex={-1}
                    disabled={disabled}
                    aria-label="فتح التقويم"
                    className="-m-1 flex items-center rounded p-1 text-muted-foreground outline-none hover:text-foreground"
                >
                    <CalendarDays className="size-4 shrink-0" />
                </PopoverButton>
                <PopoverPanel
                    anchor={{ to: 'bottom end', gap: 8 }}
                    className="z-50 rounded-xl border bg-popover p-3 text-popover-foreground shadow-lg"
                >
                    {({ close }) => (
                        <Calendar
                            value={value}
                            clearable={!required}
                            onPick={(iso) => {
                                setDraft(null);
                                if (iso !== value) {
                                    onChange(iso);
                                }
                                close();
                            }}
                        />
                    )}
                </PopoverPanel>
            </Popover>
        </div>
    );
}

export { DateInput };
