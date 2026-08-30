import { Plus, Trash2 } from 'lucide-react';
import CurrencyToggle from '@/components/money/currency-toggle';
import DollarInput from '@/components/money/decimal-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type PriceRow = {
    name: string;
    /**
     * In the unit its currency is stored in: whole lira, or US cents for a
     * dollar price. The same convention as the `price_list` column itself and
     * as an order item's `original_amount`, so the row is submitted as-is —
     * only the input widget deals in dollars.
     */
    price: number;
    currency: 'SYP' | 'USD';
};

/**
 * Return the distinct trimmed names that appear on more than one row. The
 * price list is submitted as a name→price object, so duplicates would
 * silently collapse (last one wins) — callers use this to block submit and
 * tell the user instead of quietly dropping a price.
 */
export function findDuplicateNames(rows: PriceRow[]): string[] {
    const seen = new Set<string>();
    const duplicates = new Set<string>();
    for (const row of rows) {
        const name = row.name.trim();
        if (name === '') {
            continue;
        }
        if (seen.has(name)) {
            duplicates.add(name);
        }
        seen.add(name);
    }
    return [...duplicates];
}

/**
 * Editable list of work types and their default prices for a dentist.
 * Each row is a free-text name + price; rows can be added or removed.
 * The parent owns the array and submits it (an empty-named rows are
 * filtered out on submit).
 */
export default function PriceListEditor({
    value,
    onChange,
    dentistCurrency = 'SYP',
}: {
    value: PriceRow[];
    onChange: (rows: PriceRow[]) => void;
    /**
     * A dollar dentist is quoted only in dollars, so the per-row currency
     * toggle is meaningless for him and is hidden rather than shown disabled.
     */
    dentistCurrency?: 'SYP' | 'USD';
}) {
    const updateRow = (index: number, patch: Partial<PriceRow>) => {
        const next = [...value];
        next[index] = { ...next[index], ...patch };
        onChange(next);
    };

    const addRow = () =>
        onChange([...value, { name: '', price: 0, currency: dentistCurrency }]);
    const removeRow = (index: number) =>
        onChange(value.filter((_, i) => i !== index));

    return (
        <div className="space-y-3">
            <div className="overflow-hidden rounded-lg border">
                {value.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                        لا توجد أنواع عمل. أضف نوعاً وسعره.
                    </p>
                ) : (
                    value.map((row, index) => {
                        // One decision for both the widget and the unit. When
                        // these two disagreed, a dollar dentist's rows — which
                        // carry no currency of their own, his toggle being
                        // hidden — were parsed as lira and rounded to whole
                        // dollars on every keystroke.
                        const dollars =
                            dentistCurrency === 'USD' || row.currency === 'USD';

                        return (
                            <div
                                key={index}
                                className={`flex items-center gap-3 px-3 py-2.5 ${
                                    index < value.length - 1 ? 'border-b' : ''
                                }`}
                            >
                                <Input
                                    value={row.name}
                                    onChange={(e) =>
                                        updateRow(index, {
                                            name: e.target.value,
                                        })
                                    }
                                    placeholder="اسم نوع العمل..."
                                    className="flex-1"
                                />
                                {dollars ? (
                                    <DollarInput
                                        cents={row.price}
                                        onChange={(cents) =>
                                            updateRow(index, { price: cents })
                                        }
                                        placeholder="السعر"
                                        className="w-28"
                                    />
                                ) : (
                                    <Input
                                        type="number"
                                        min="0"
                                        step="1"
                                        value={row.price || ''}
                                        onChange={(e) =>
                                            updateRow(index, {
                                                // A lira price never has cents.
                                                price:
                                                    Math.round(
                                                        parseFloat(
                                                            e.target.value,
                                                        ),
                                                    ) || 0,
                                            })
                                        }
                                        placeholder="السعر"
                                        className="w-28"
                                    />
                                )}
                                {dentistCurrency === 'USD' ? (
                                    <span
                                        dir="ltr"
                                        className="w-16 text-center text-sm text-muted-foreground"
                                    >
                                        $
                                    </span>
                                ) : (
                                    <CurrencyToggle
                                        value={row.currency}
                                        onChange={(currency) =>
                                            // Switching currency changes what
                                            // the number means, so it is
                                            // cleared rather than silently
                                            // reinterpreted as the other one.
                                            updateRow(index, {
                                                currency,
                                                price: 0,
                                            })
                                        }
                                    />
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeRow(index)}
                                    title="حذف"
                                >
                                    <Trash2 className="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </div>
                        );
                    })
                )}
            </div>

            <Button type="button" variant="outline" size="sm" onClick={addRow}>
                <Plus className="h-4 w-4" />
                إضافة نوع عمل
            </Button>
        </div>
    );
}

export const DEFAULT_WORK_TYPES = [
    'تركيبة زيركون',
    'تركيبة خزف',
    'تلبيسة',
    'جسر',
    'طقم أسنان',
    'فينير',
    'زرعة',
    'تقويم',
];
