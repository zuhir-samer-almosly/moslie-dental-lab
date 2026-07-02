import { Fragment } from 'react';
import {
    getToothType,
    LOWER_TEETH,
    TOOTH_TYPE_LABELS_AR,
    UPPER_TEETH,
} from '@/lib/teeth';
import type { OrderItem } from '@/types';

/** Shared helpers for rendering order items (patient/teeth/date live in meta). */

export const itemMeta = (item: OrderItem) =>
    (item.meta as Record<string, unknown> | null) ?? {};
export const itemTeeth = (item: OrderItem) =>
    (itemMeta(item).selected_teeth as number[] | undefined) ?? [];
export const itemPatient = (item: OrderItem) =>
    (itemMeta(item).patient_name as string | undefined) ?? '';
export const itemDate = (item: OrderItem) =>
    (itemMeta(item).date as string | undefined) ?? '';
export const itemAmount = (item: OrderItem) =>
    (item.price ?? 0) * (item.quantity ?? 0);

/** Render a date as D/M/YYYY (day-first), the format used across the app. */
export const formatDate = (value: string | null | undefined) => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()}`;
};

export const Dash = () => (
    <span className="text-xs text-muted-foreground">—</span>
);

/**
 * One tooth slot. Every slot is the SAME fixed width so all 16 columns line up
 * and both arches stay centered on the midline (variable widths made the two
 * rows drift out of alignment). Selected → a numbered pill filling the cell;
 * unselected → a faint centered tick.
 */
function ToothSlot({ tooth, selected }: { tooth: number; selected: boolean }) {
    return (
        <span className="flex h-4 w-5 shrink-0 items-center justify-center">
            {selected ? (
                <span
                    title={`${tooth} — ${TOOTH_TYPE_LABELS_AR[getToothType(tooth)]}`}
                    className="inline-flex h-4 w-full items-center justify-center rounded-sm border border-primary/20 bg-primary/15 text-[9px] font-semibold text-primary tabular-nums"
                >
                    {tooth}
                </span>
            ) : (
                <span
                    aria-hidden
                    className="h-3 w-px rounded-sm bg-muted-foreground/25"
                />
            )}
        </span>
    );
}

/** One arch (16 fixed slots) with a divider at the midline (after slot 8). */
function ToothArch({
    teeth,
    selected,
}: {
    teeth: number[];
    selected: Set<number>;
}) {
    return (
        <div className="flex items-center gap-[2px]">
            {teeth.map((tooth, i) => (
                <Fragment key={tooth}>
                    {i === 8 && (
                        <span
                            aria-hidden
                            className="mx-0.5 h-4 w-px shrink-0 bg-border"
                        />
                    )}
                    <ToothSlot tooth={tooth} selected={selected.has(tooth)} />
                </Fragment>
            ))}
        </div>
    );
}

/**
 * Read-only mini "odontogram": every selected tooth shows as a numbered pill in
 * its true anatomical slot (upper arch on top, patient's right on the left);
 * unselected slots are faint ticks that keep the position reference. Forced LTR
 * so the left/right orientation is stable under the app's global RTL and
 * matches the interactive entry chart. The number is real text (prints), and
 * hovering a tooth reveals its Arabic type.
 */
export function TeethOdontogram({ teeth }: { teeth: number[] }) {
    if (teeth.length === 0) return <Dash />;
    const selected = new Set(teeth);
    return (
        <div dir="ltr" className="mx-auto flex w-fit flex-col gap-[3px]">
            <div className="flex items-center gap-1">
                <span className="w-2 text-[8px] font-semibold text-muted-foreground">
                    R
                </span>
                <ToothArch teeth={UPPER_TEETH} selected={selected} />
                <span className="w-2 text-[8px] font-semibold text-muted-foreground">
                    L
                </span>
            </div>
            <div className="flex items-center gap-1">
                <span className="w-2" aria-hidden />
                <ToothArch teeth={LOWER_TEETH} selected={selected} />
                <span className="w-2" aria-hidden />
            </div>
        </div>
    );
}
