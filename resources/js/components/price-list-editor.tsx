import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type PriceRow = {
    name: string;
    price: number;
};

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
        next[index] = {
            ...next[index],
            [field]:
                field === 'price'
                    ? fieldValue === ''
                        ? 0
                        : parseFloat(fieldValue)
                    : fieldValue,
        };
        onChange(next);
    };

    const addRow = () => onChange([...value, { name: '', price: 0 }]);
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
                                value={row.price || ''}
                                onChange={(e) =>
                                    updateRow(index, 'price', e.target.value)
                                }
                                placeholder="السعر"
                                className="w-32"
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
