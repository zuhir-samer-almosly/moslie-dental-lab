export interface Dentist {
    id: number;
    name: string;
    /**
     * What this dentist is quoted, billed and paid in. A USD dentist's money
     * is native dollars: cents, converted by nothing, and no rate anywhere.
     */
    currency?: 'SYP' | 'USD';
    /**
     * Whether anything has posted to this dentist's ledger account yet.
     * Populated only where the currency-editing form needs it (the dentists
     * index and edit pages) — absent elsewhere, including the plain
     * `Dentist::all()` pickers on the order and payment pages.
     */
    has_ledger_lines?: boolean;
    gender: 'male' | 'female';
    phone: string | null;
    address: string | null;
    /**
     * A dentist's own prices. `price` is in the natural unit of its currency:
     * whole lira for SYP, cents for USD. A dollar price is a quote — it
     * converts to lira at the day's rate when an order uses it.
     */
    price_list: Record<
        string,
        { price: number; currency: 'SYP' | 'USD' }
    > | null;
    created_at: string;
    updated_at: string;
}

export interface Order {
    id: number;
    dentist_id: number;
    dentist?: Dentist;
    due_date: string;
    amount: number;
    /** What this order is billed in. USD orders hold cents in `original_amount`. */
    currency?: 'SYP' | 'USD';
    /** Total in US cents, when `currency` is USD. `amount` is then 0. */
    original_amount?: number | null;
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
    /** Always lira. Derived from the fields below when the quote was in dollars. */
    price: number;
    currency?: 'SYP' | 'USD';
    /** US cents, when the dentist's price list quoted this work type in dollars. */
    original_amount?: number | null;
    /** Lira per 1 USD, frozen at the day the order used the quote. */
    rate?: string | null;
    notes: string | null;
    meta: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface DentistPayment {
    id: number;
    dentist_id: number;
    dentist?: Dentist;
    /** Always lira — the currency of record. Set from the fields below when foreign. */
    amount: number;
    /** What the money arrived as. Absent on rows written before multi-currency. */
    currency?: 'SYP' | 'USD';
    /** US cents, when `currency` is USD. */
    original_amount?: number | null;
    /** Lira per 1 USD, frozen at the day of the payment. */
    rate?: string | null;
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
    /** Always lira — the currency of record. Set from the fields below when foreign. */
    amount: number;
    /** What the money arrived as. Absent on rows written before multi-currency. */
    currency?: 'SYP' | 'USD';
    /** US cents, when `currency` is USD. */
    original_amount?: number | null;
    /** Lira per 1 USD, frozen at the day of the transaction. */
    rate?: string | null;
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
    /** Always lira — the currency of record. Set from the fields below when foreign. */
    amount: number;
    /** What the money arrived as. Absent on rows written before multi-currency. */
    currency?: 'SYP' | 'USD';
    /** US cents, when `currency` is USD. */
    original_amount?: number | null;
    /** Lira per 1 USD, frozen at the day of the transaction. */
    rate?: string | null;
    purchase_date?: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface Expense {
    id: number;
    category: string;
    description: string | null;
    /** Always lira — the currency of record. Set from the fields below when foreign. */
    amount: number;
    /** What the money arrived as. Absent on rows written before multi-currency. */
    currency?: 'SYP' | 'USD';
    /** US cents, when `currency` is USD. */
    original_amount?: number | null;
    /** Lira per 1 USD, frozen at the day of the transaction. */
    rate?: string | null;
    expense_date?: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

import { usePage } from '@inertiajs/react';

/**
 * Expense categories, defined by the accounts table and shared on every
 * Inertia response. Replaces the constant that had to be hand-synced with
 * the PHP side. is_active-filtered — this is what pickers OFFER.
 */
export function useExpenseCategories(): Record<string, string> {
    return usePage().props.expenseCategories;
}

/**
 * Unfiltered expense-category labels, including deactivated ones, so
 * already-recorded expenses keep their Arabic name instead of falling back
 * to the raw category key once their category is deactivated.
 */
export function useExpenseCategoryLabels(): Record<string, string> {
    return usePage().props.expenseCategoryLabels;
}

export const ORDER_STATUSES: Record<Order['status'], string> = {
    pending: 'قيد الانتظار',
    completed: 'مكتمل',
    cancelled: 'ملغي',
    recieved: 'مستلم',
};
