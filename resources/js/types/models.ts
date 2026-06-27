export interface Dentist {
	id: number
	name: string
	email: string | null
	phone: string | null
	address: string | null
	price_list: Record<string, number> | null
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
	payment_date?: string
	created_at: string
	updated_at: string
}

export const ORDER_STATUSES: Record<Order['status'], string> = {
	pending: 'قيد الانتظار',
	completed: 'مكتمل',
	cancelled: 'ملغي',
	recieved: 'مستلم',
}
