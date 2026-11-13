/** What NotebookController::build sends for one dentist's notebook. */

type LineBase = {
    key: string;
    date: string;
    /** In the dentist's own currency: whole lira, or cents. */
    value: number;
    /** What the dentist owed once this line was written. */
    balance: number;
    notes: string | null;
};

export type NotebookLine =
    | (LineBase & {
          kind: 'work';
          type: string;
          patient_name: string;
          selected_teeth: number[];
          quantity: number;
      })
    | (LineBase & {
          kind: 'payment';
          payment_currency: 'SYP' | 'USD';
          original_amount: number | null;
          rate: string | null;
      });

export type NotebookProps = {
    dentist: { id: number; name: string; currency: 'SYP' | 'USD' };
    opening: number;
    closing: number;
    lines: NotebookLine[];
    filters: { from: string | null; to: string | null; all: boolean };
};

/** "زيركون × 3" or "دفعة — نقداً": the line in words. */
export const describe = (line: NotebookLine): string =>
    line.kind === 'work'
        ? `${line.type}${line.quantity > 1 ? ` × ${line.quantity}` : ''}`
        : `دفعة${line.notes ? ` — ${line.notes}` : ''}`;

/** The period in words, for a heading. */
export const describePeriod = (filters: NotebookProps['filters']): string => {
    if (filters.all) {
        return 'كل السجل';
    }

    const show = (date: string | null) =>
        date ? date.split('-').reverse().join('/') : '—';

    return `من ${show(filters.from)} إلى ${show(filters.to)}`;
};
