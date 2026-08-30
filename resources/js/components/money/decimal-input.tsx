import { useState } from 'react';
import { Input } from '@/components/ui/input';

/**
 * Keep only what a dollar amount may contain: digits, at most one decimal
 * point, and at most two digits after it. Capping the decimals here rather
 * than at parse time means a third digit is refused while typing instead of
 * being silently rounded away after the fact.
 */
const sanitize = (text: string): string => {
    const cleaned = text.replace(/[^\d.]/g, '');
    const [whole, ...rest] = cleaned.split('.');

    return rest.length === 0 ? whole : `${whole}.${rest.join('').slice(0, 2)}`;
};

const toCents = (text: string): number => {
    const parsed = parseFloat(text);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
};

const toText = (cents: number): string => (cents ? String(cents / 100) : '');

/**
 * A dollar amount, typed in dollars and carried in cents.
 *
 * It is deliberately NOT `<input type="number">`. A number input refuses to
 * hold a half-typed decimal — the HTML spec says "11." is not a valid
 * floating-point number, so the browser reports the value as empty — and a
 * controlled numeric round-trip rewrites "10.0" back to "10", eating the next
 * keystroke. That is why "11.50" and "10.05" could not be typed into a dollar
 * price at all. `inputMode="decimal"` keeps the numeric keypad on a phone.
 *
 * The raw text lives here so a half-typed amount survives between keystrokes,
 * while the parent only ever sees whole cents — the unit the price list, the
 * order item and the ledger all already store.
 */
export default function DollarInput({
    cents,
    onChange,
    ...props
}: Omit<React.ComponentProps<typeof Input>, 'value' | 'onChange' | 'type'> & {
    cents: number;
    onChange: (cents: number) => void;
}) {
    const [draft, setDraft] = useState<string | null>(null);

    // The draft is what the user typed; it is shown only while it still means
    // the amount we hold. When the parent sets the amount from somewhere else
    // — the price-list auto-fill, or switching dentist — the draft is stale
    // and the amount itself wins, which keeps this a controlled input.
    const text =
        draft !== null && toCents(draft) === cents ? draft : toText(cents);

    return (
        <Input
            {...props}
            type="text"
            inputMode="decimal"
            dir="ltr"
            value={text}
            onChange={(e) => {
                const next = sanitize(e.target.value);
                setDraft(next);
                onChange(toCents(next));
            }}
        />
    );
}
