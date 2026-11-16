import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import CurrencyAmountField, {
    type CurrencyAmount,
} from '@/components/money/currency-amount-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DateInput } from '@/components/ui/date-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { today } from '@/lib/utils';
import type { MaterialPurchase } from '@/types';

/**
 * The one material-purchase form, shared by the create and edit pages. Given
 * a `purchase` it edits that one; without, it records a new one.
 */
export default function MaterialPurchaseForm({
    purchase,
    todayRate,
}: {
    purchase?: MaterialPurchase;
    todayRate: string | null;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: purchase?.name ?? '',
        supplier: purchase?.supplier ?? '',
        quantity: purchase?.quantity ?? '',
        amount: purchase?.amount.toString() ?? '',
        currency: (purchase?.currency ?? 'SYP') as CurrencyAmount['currency'],
        original_amount:
            purchase?.original_amount === null ||
            purchase?.original_amount === undefined
                ? ''
                : (purchase.original_amount / 100).toFixed(2),
        rate: purchase?.rate ?? '',
        purchase_date: purchase
            ? (purchase.purchase_date || purchase.created_at).split('T')[0]
            : today(),
        notes: purchase?.notes ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (purchase) {
            put(`/material-purchases/${purchase.id}`);
        } else {
            post('/material-purchases');
        }
    };

    return (
        <form onSubmit={handleSubmit} className="max-w-2xl">
            <Card className="gap-0 py-0">
                <CardContent className="space-y-6 p-5 md:p-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">المادة</Label>
                        <Input
                            id="name"
                            value={data.name}
                            placeholder="خزف، جبس، زركون..."
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="supplier">المورّد</Label>
                        <Input
                            id="supplier"
                            value={data.supplier}
                            placeholder="من اشتريت منه"
                            onChange={(e) =>
                                setData('supplier', e.target.value)
                            }
                        />
                        <InputError message={errors.supplier} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quantity">الكمية</Label>
                        <Input
                            id="quantity"
                            value={data.quantity}
                            placeholder="مثال: 5 كغ"
                            onChange={(e) =>
                                setData('quantity', e.target.value)
                            }
                        />
                        <InputError message={errors.quantity} />
                    </div>

                    <CurrencyAmountField
                        label="السعر"
                        value={data}
                        onChange={(patch) =>
                            setData((prev) => ({ ...prev, ...patch }))
                        }
                        errors={errors}
                        todayRate={todayRate}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="purchase_date">التاريخ</Label>
                        <DateInput
                            id="purchase_date"
                            value={data.purchase_date}
                            onChange={(value) =>
                                setData('purchase_date', value)
                            }
                            required
                        />
                        <InputError message={errors.purchase_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">ملاحظات</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {purchase ? 'حفظ التعديلات' : 'حفظ'}
                    </Button>
                </CardContent>
            </Card>
        </form>
    );
}
