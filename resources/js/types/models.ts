export interface Dentist {
	id: number
	name: string
	email: string | null
	phone: string | null
	address: string | null
	created_at: string
	updated_at: string
}

export interface Order {
	id: number
	dentist_id: number
	dentist?: Dentist
	due_date: string
	amount: number
	status: 'pending' | 'completed' | 'cancelled' | 'recieved'
	notes: string | null
	meta: Record<string, unknown> | null
	items?: OrderItem[]
	created_at: string
	updated_at: string
}

export interface OrderItem {
	id: number
	order_id: number
	type: string
	quantity: number
	price: number
	notes: string | null
	meta: Record<string, unknown> | null
	created_at: string
	updated_at: string
}

export interface DentistPayment {
	id: number
	dentist_id: number
	dentist?: Dentist
	amount: number
	created_at: string
	updated_at: string
}

import { t } from '@/lib/translations';

export const getWorkTypes = () => [
	'تركيبة زيركون',
	'تركيبة خزف',
	'تلبيسة',
	'جسر',
	'طقم أسنان',
	'فينير',
	'زرعة',
	'تقويم',
] as const

export const getOrderStatuses = (): Record<Order['status'], string> => ({
	pending: t('dashboard.pending_orders'),
	completed: t('orders.status') + ' - ' + 'مكتمل', // Simplification to reuse some translations
	cancelled: t('orders.status') + ' - ' + 'ملغي',
	recieved: t('orders.status') + ' - ' + 'مستلم',
})

// Add these to translations manually if needed, or just leave them static for now since the main UI strings are the priority. 
// Actually, let's keep them static for now to avoid refactoring every place that uses them until we are sure.
// Reverting this plan and keeping them as is for now to avoid breaking the create/edit forms.
export const WORK_TYPES = [
	'تركيبة زيركون',
	'تركيبة خزف',
	'تلبيسة',
	'جسر',
	'طقم أسنان',
	'فينير',
	'زرعة',
	'تقويم',
] as const

export const ORDER_STATUSES: Record<Order['status'], string> = {
	pending: 'قيد الانتظار',
	completed: 'مكتمل',
	cancelled: 'ملغي',
	recieved: 'مستلم',
}
