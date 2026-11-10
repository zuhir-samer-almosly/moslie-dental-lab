import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { LOWER_TEETH, UPPER_TEETH } from '@/lib/teeth';

const VALID = new Set([...UPPER_TEETH, ...LOWER_TEETH]);

/** "36 44,45" → [36, 44, 45]. Anything that is not a real tooth is ignored. */
const parseTeeth = (text: string): number[] =>
    [
        ...new Set(
            text
                .split(/\D+/)
                .map(Number)
                .filter((tooth) => VALID.has(tooth)),
        ),
    ].sort((a, b) => a - b);

const sameTeeth = (a: number[], b: number[]) =>
    a.length === b.length && a.every((tooth, i) => tooth === b[i]);

/**
 * Teeth by typing their FDI numbers, for entering a sheet without leaving the
 * keyboard. The chart below it stays the same selection seen another way:
 * whatever is typed here lights up there, and a click there shows up here.
 */
export default function TeethInput({
    teeth,
    onChange,
    className,
}: {
    teeth: number[];
    onChange: (teeth: number[]) => void;
    className?: string;
}) {
    const [text, setText] = useState(teeth.join(' '));

    // Keep the half-typed text ("36 4") while it still means the current
    // selection; once the chart changes the selection, show that instead.
    const shown = sameTeeth(parseTeeth(text), teeth) ? text : teeth.join(' ');

    return (
        <Input
            type="text"
            inputMode="numeric"
            dir="ltr"
            data-teeth-input
            value={shown}
            onChange={(e) => {
                setText(e.target.value);

                const next = parseTeeth(e.target.value);
                if (!sameTeeth(next, teeth)) {
                    onChange(next);
                }
            }}
            placeholder="36 44 45"
            className={className}
        />
    );
}
