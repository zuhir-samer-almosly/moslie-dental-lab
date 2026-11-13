import { normalizeArabic } from '@/lib/arabic';
import { rateValue, usdToSyp } from '@/lib/money';
import type { Dentist } from '@/types';

/**
 * Reading a dentist's price list while a line is being typed. Shared by the
 * order form and the daybook, so both price a work type the same way.
 */

/** The money fields of an order line that a price-list entry fills in. */
export type Quote = {
    /** Always lira. Zero for a dollar dentist. */
    price: number;
    currency: 'SYP' | 'USD';
    /** US cents, when the line is in dollars. */
    original_amount: number;
    rate: string;
};

/**
 * The price-list entry a typed name refers to. Spelling variants of one
 * name (إعادة / اعادة) find the same entry, so the line is priced as it is
 * typed; the server then files it under the price list's own spelling.
 */
export function priceListKey(
    dentist: Dentist | undefined,
    type: string,
): string | null {
    const list = dentist?.price_list ?? {};
    if (type in list) {
        return type;
    }

    const wanted = normalizeArabic(type);
    if (wanted === '') {
        return null;
    }

    return (
        Object.keys(list).find((name) => normalizeArabic(name) === wanted) ??
        null
    );
}

/**
 * The price fields a work type implies, from the dentist's own list — null
 * when he has no price for it.
 *
 * A dollar quote is converted here, at the rate recorded for today, and
 * the item carries lira from then on — the quote and its rate ride along
 * so the invoice can show what the price came from. If no rate has been
 * recorded yet the rate is left empty for the user to type; the price is
 * then 0 until they do, rather than silently pricing the work at nothing.
 */
export function quoteFor(
    dentist: Dentist | undefined,
    type: string,
    todayRate: string | null,
): Quote | null {
    const key = priceListKey(dentist, type);
    const entry = key ? dentist?.price_list?.[key] : undefined;
    if (!entry) {
        return null;
    }

    // A dollar dentist's price list is dollars, full stop — it fills
    // straight into the dollar field with no rate and no conversion.
    // His lira price is always zero.
    if (dentist?.currency === 'USD') {
        return {
            currency: 'USD',
            original_amount: entry.price,
            rate: '',
            price: 0,
        };
    }

    if (entry.currency === 'USD') {
        const rate = rateValue(todayRate);
        const parsed = parseFloat(rate);
        return {
            currency: 'USD',
            original_amount: entry.price,
            rate,
            price: Number.isFinite(parsed)
                ? usdToSyp(entry.price / 100, parsed)
                : 0,
        };
    }

    return {
        currency: 'SYP',
        original_amount: 0,
        rate: '',
        // Round: legacy price lists may hold decimals, but the server
        // only accepts integer item prices.
        price: Math.round(entry.price),
    };
}

/**
 * A dentist's priced work types, the ones he orders most first — so quick
 * buttons and the top of a dropdown are what is actually typed all day, not
 * whatever order the price list was written in.
 */
export function rankedWorkTypes(
    dentist: Dentist | undefined,
    usage: string[],
): string[] {
    const names = Object.keys(dentist?.price_list ?? {});
    const rank = (name: string) => {
        const index = usage.indexOf(name);

        return index === -1 ? usage.length : index;
    };

    // Stable sort: unused types keep their price-list order.
    return [...names].sort((a, b) => rank(a) - rank(b));
}
