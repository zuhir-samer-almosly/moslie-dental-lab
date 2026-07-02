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

export function TeethBadges({ teeth }: { teeth: number[] }) {
    if (teeth.length === 0) return <Dash />;
    return (
        <div className="flex flex-wrap gap-1">
            {[...teeth]
                .sort((a, b) => a - b)
                .map((tooth) => (
                    <span
                        key={tooth}
                        className="inline-flex h-5 min-w-[22px] items-center justify-center rounded bg-primary/10 px-1 text-[10px] font-semibold text-primary"
                    >
                        {tooth}
                    </span>
                ))}
        </div>
    );
}
