export interface Dentist {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    price_list: Record<string, number> | null;
    created_at: string;
    updated_at: string;
}

export interface Order {
    id: number;
    dentist_id: number;
    dentist?: Dentist;
    due_date: string;
    amount: number;
    status: 'pending' | 'completed' | 'cancelled' | 'recieved';
    notes: string | null;
    meta: Record<string, unknown> | null;
    items?: OrderItem[];
    /** Dentist's outstanding balance carried in from before this order's date. */
    previous_balance?: number;
    created_at: string;
    updated_at: string;
}

export interface OrderItem {
    id: number;
    order_id: number;
    type: string;
    quantity: number;
    price: number;
    notes: string | null;
    meta: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface DentistPayment {
    id: number;
    dentist_id: number;
    dentist?: Dentist;
    amount: number;
    payment_date?: string;
    created_at: string;
    updated_at: string;
}

export interface Employee {
    id: number;
    name: string;
    role: string | null;
    phone: string | null;
    notes: string | null;
    is_active: boolean;
    payments_sum_amount?: number | null;
    created_at: string;
    updated_at: string;
}

export interface EmployeePayment {
    id: number;
    employee_id: number;
    employee?: Employee;
    amount: number;
    payment_date?: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface MaterialPurchase {
    id: number;
    name: string;
    supplier: string | null;
    quantity: string | null;
    amount: number;
    purchase_date?: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export const ORDER_STATUSES: Record<Order['status'], string> = {
    pending: 'قيد الانتظار',
    completed: 'مكتمل',
    cancelled: 'ملغي',
    recieved: 'مستلم',
};
