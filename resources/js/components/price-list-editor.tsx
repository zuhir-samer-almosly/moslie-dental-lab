import { Plus, Trash2 } from 'lucide-react';
import CurrencyToggle from '@/components/money/currency-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type PriceRow = {
    name: string;
    /**
     * In the natural unit of its currency: whole lira, or dollars with
     * decimals. The form converts a dollar price to cents on submit, which is
     * what the column holds.
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
}: {
    value: PriceRow[];
    onChange: (rows: PriceRow[]) => void;
}) {
    const updateRow = (
        index: number,
        field: keyof PriceRow,
        fieldValue: string,
    ) => {
        const next = [...value];
        const row = next[index];
        next[index] = {
            ...row,
            [field]:
                field === 'price'
                    ? // A dollar price keeps its cents; a lira one never has any.
                      (row.currency === 'USD'
                          ? parseFloat(fieldValue)
                          : Math.round(parseFloat(fieldValue))) || 0
                    : fieldValue,
        };
        onChange(next);
    };

    const setCurrency = (index: number, currency: PriceRow['currency']) => {
        const next = [...value];
        // Switching currency changes what the number means, so it is cleared
        // rather than silently reinterpreted as the other currency.
        next[index] = { ...next[index], currency, price: 0 };
        onChange(next);
    };

    const addRow = () =>
        onChange([...value, { name: '', price: 0, currency: 'SYP' }]);
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
                    value.map((row, index) => (
                        <div
                            key={index}
                            className={`flex items-center gap-3 px-3 py-2.5 ${
                                index < value.length - 1 ? 'border-b' : ''
                            }`}
                        >
                            <Input
                                value={row.name}
                                onChange={(e) =>
                                    updateRow(index, 'name', e.target.value)
                                }
                                placeholder="اسم نوع العمل..."
                                className="flex-1"
                            />
                            <Input
                                type="number"
                                min="0"
                                step={row.currency === 'USD' ? '0.01' : '1'}
                                value={row.price || ''}
                                onChange={(e) =>
                                    updateRow(index, 'price', e.target.value)
                                }
                                placeholder="السعر"
                                className="w-28"
                            />
                            <CurrencyToggle
                                value={row.currency}
                                onChange={(currency) =>
                                    setCurrency(index, currency)
                                }
                            />
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
                    ))
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
