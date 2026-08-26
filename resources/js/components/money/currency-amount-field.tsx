import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatSyp, rateValue, usdToSyp } from '@/lib/money';

export type CurrencyAmount = {
    currency: 'SYP' | 'USD';
    amount: string;
    original_amount: string;
    rate: string;
};

/**
 * An amount that may be handed over in lira or in dollars.
 *
 * When it arrives in dollars the user types what he was given and the rate he
 * agreed that day; the lira result is previewed live so he can see, before
 * saving, the single figure the books will carry from then on. The preview
 * mirrors the server's rounding — the server still does the real conversion.
 */
export default function CurrencyAmountField({
    value,
    onChange,
    errors,
    todayRate,
    label = 'المبلغ',
}: {
    value: CurrencyAmount;
    onChange: (patch: Partial<CurrencyAmount>) => void;
    errors: Partial<Record<keyof CurrencyAmount, string>>;
    todayRate: string | null;
    /** What this money is called on its own form — "المبلغ", "السعر". */
    label?: string;
}) {
    const dollars = parseFloat(value.original_amount);
    const rate = parseFloat(value.rate);
    const preview =
        Number.isFinite(dollars) &&
        Number.isFinite(rate) &&
        dollars > 0 &&
        rate > 0
            ? usdToSyp(dollars, rate)
            : null;

    const pickCurrency = (currency: 'SYP' | 'USD') =>
        onChange(
            currency === 'USD'
                ? // Offer the last rate the lab recorded; it stays editable,
                  // because the rate that matters is the one actually agreed.
                  {
                      currency,
                      amount: '',
                      rate: value.rate || rateValue(todayRate),
                  }
                : { currency, original_amount: '', rate: '' },
        );

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                <Label>العملة</Label>
                <div className="flex gap-4">
                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="radio"
                            name="currency"
                            value="SYP"
                            checked={value.currency === 'SYP'}
                            onChange={() => pickCurrency('SYP')}
                            className="accent-primary"
                        />
                        <span>ليرة</span>
                    </label>
                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="radio"
                            name="currency"
                            value="USD"
                            checked={value.currency === 'USD'}
                            onChange={() => pickCurrency('USD')}
                            className="accent-primary"
                        />
                        <span>دولار</span>
                    </label>
                </div>
            </div>

            {value.currency === 'SYP' ? (
                <div className="grid gap-2">
                    <Label htmlFor="amount">{label} بالليرة</Label>
                    <Input
                        id="amount"
                        type="number"
                        min="1"
                        value={value.amount}
                        onChange={(e) => onChange({ amount: e.target.value })}
                        required
                    />
                    <InputError message={errors.amount} />
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="original_amount">
                                {label} بالدولار
                            </Label>
                            <Input
                                id="original_amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={value.original_amount}
                                onChange={(e) =>
                                    onChange({
                                        original_amount: e.target.value,
                                    })
                                }
                                required
                            />
                            <InputError message={errors.original_amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="rate">سعر الصرف</Label>
                            <Input
                                id="rate"
                                type="number"
                                step="0.000001"
                                min="0.000001"
                                value={value.rate}
                                onChange={(e) =>
                                    onChange({ rate: e.target.value })
                                }
                                required
                            />
                            <InputError message={errors.rate} />
                        </div>
                    </div>

                    <p className="text-sm text-muted-foreground">
                        {preview === null ? (
                            'أدخل المبلغ وسعر الصرف'
                        ) : (
                            <>
                                يُسجَّل في الحساب:{' '}
                                <span className="font-semibold text-foreground tabular-nums">
                                    {formatSyp(preview)}
                                </span>{' '}
                                ليرة
                            </>
                        )}
                    </p>
                </div>
            )}
        </div>
    );
}
