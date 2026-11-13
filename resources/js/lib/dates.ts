/**
 * Calendar-date helpers for `YYYY-MM-DD` strings. Everything here works on
 * the date as written, never through UTC — `new Date('2026-06-15')` is
 * midnight UTC, which is still the 14th west of Greenwich.
 */

const parse = (date: string): Date => {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
};

const format = (date: Date): string =>
    [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');

export const addDays = (date: string, days: number): string => {
    const next = parse(date);
    next.setDate(next.getDate() + days);

    return format(next);
};

/** "الاثنين 15 حزيران 2026" — Arabic names, Latin digits like the rest of the app. */
export const formatLongDate = (date: string): string =>
    parse(date).toLocaleDateString('ar-SY-u-nu-latn', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

/** 15/06/2026 — day first, as the app's date fields read. */
export const formatShortDate = (date: string): string => {
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year}`;
};
