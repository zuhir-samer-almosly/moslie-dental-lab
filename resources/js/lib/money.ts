/**
 * Money formatting. The lira is this lab's currency of record: every stored
 * figure is whole lira, and a dollar amount is provenance shown beside it.
 *
 * Until now `value.toLocaleString('en-US')` was copy-pasted into every page.
 * New money display should come through here so a change lands in one place.
 */

/** Whole lira, grouped. */
export const formatSyp = (value: number): string =>
    value.toLocaleString('en-US');

/** US cents rendered as dollars, always with both decimal places. */
export const formatUsd = (cents: number): string =>
    (cents / 100).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

/** A stored rate ("13.000000") without its trailing zeroes. */
export const formatRate = (rate: string | number): string => {
    const value = typeof rate === 'string' ? parseFloat(rate) : rate;
    return Number.isFinite(value)
        ? value.toLocaleString('en-US', { maximumFractionDigits: 6 })
        : '';
};

/**
 * A stored rate as a plain editable number: "130.000000" becomes "130".
 *
 * Deliberately not `formatRate`, which groups thousands — a rate of 12,500
 * with a separator in it would break both the number input and the server's
 * numeric validation.
 */
export const rateValue = (rate: string | null | undefined): string => {
    if (rate === null || rate === undefined || rate === '') {
        return '';
    }
    const value = parseFloat(rate);
    return Number.isFinite(value) ? String(value) : '';
};

/**
 * Mirror of `App\Money\Rate::toSyp` for previewing a conversion while the
 * user types. The server always recomputes it — this never decides what is
 * stored, it only shows what will be.
 */
export const usdToSyp = (dollars: number, rate: number): number =>
    Math.round(dollars * rate);

/**
 * A stored figure rendered in its own currency: whole lira grouped, or cents
 * as dollars with both decimals. The single place a currency decides how a
 * number reads.
 *
 * The sign is pulled out and placed before the `$`, not inside `formatUsd`'s
 * own output — `` `$${formatUsd(-3000)}` `` would read "$-30.00", which is
 * not how a negative dollar figure is written. This matters here: a credit
 * balance (an overpaid dentist) is exactly where a negative dollar figure
 * first reaches this function, on a document a dentist receives.
 */
export const formatMoney = (
    value: number,
    currency: 'SYP' | 'USD' = 'SYP',
): string => {
    if (currency !== 'USD') {
        return formatSyp(value);
    }

    return value < 0 ? `-$${formatUsd(-value)}` : `$${formatUsd(value)}`;
};
