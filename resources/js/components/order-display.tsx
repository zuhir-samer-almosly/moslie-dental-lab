import type { OrderItem } from '@/types'

/** Shared helpers for rendering order items (patient/teeth/date live in meta). */

export const itemMeta = (item: OrderItem) => (item.meta as Record<string, unknown> | null) ?? {}
export const itemTeeth = (item: OrderItem) =>
	(itemMeta(item).selected_teeth as number[] | undefined) ?? []
export const itemPatient = (item: OrderItem) =>
	(itemMeta(item).patient_name as string | undefined) ?? ''
export const itemDate = (item: OrderItem) => (itemMeta(item).date as string | undefined) ?? ''
export const itemAmount = (item: OrderItem) => (item.price ?? 0) * (item.quantity ?? 0)

export const formatDate = (value: string) =>
	value ? new Date(value).toLocaleDateString('en-US') : ''

export const Dash = () => <span className="text-muted-foreground text-xs">—</span>

export function TeethBadges({ teeth }: { teeth: number[] }) {
	if (teeth.length === 0) return <Dash />
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
	)
}
