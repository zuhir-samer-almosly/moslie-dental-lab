/** One line of the daybook, as DaybookController::lines sends it. */

type LineBase = {
    key: string;
    /** False for a line of a larger order: it is edited on the order page. */
    editable: boolean;
    dentist_id: number;
    dentist_name: string | null;
    /** The dentist's own currency — what `value` is in. */
    currency: 'SYP' | 'USD';
    /** Whole lira, or cents for a dollar dentist. */
    value: number;
    /** US cents, when the line was quoted or handed over in dollars. */
    original_amount: number | null;
    rate: string | null;
    notes: string | null;
};

export type WorkLine = LineBase & {
    kind: 'work';
    order_id: number;
    status: 'pending' | 'completed' | 'cancelled' | 'recieved';
    type: string;
    patient_name: string;
    selected_teeth: number[];
    quantity: number;
    /** Always lira; zero for a dollar dentist. */
    price: number;
    item_currency: 'SYP' | 'USD';
};

export type PaymentLine = LineBase & {
    kind: 'payment';
    payment_id: number;
    /** Always lira — set by the server when the money came in dollars. */
    amount: number;
    payment_currency: 'SYP' | 'USD';
};

export type DaybookLine = WorkLine | PaymentLine;
