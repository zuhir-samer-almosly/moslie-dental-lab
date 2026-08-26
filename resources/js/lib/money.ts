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
 * Mirror of `App\Money\Rate::toSyp` for previewing a conversion while the
 * user types. The server always recomputes it — this never decides what is
 * stored, it only shows what will be.
 */
export const usdToSyp = (dollars: number, rate: number): number =>
    Math.round(dollars * rate);
