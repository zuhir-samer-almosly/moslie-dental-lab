import type { InertiaLinkProps } from '@inertiajs/react';
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Today as `yyyy-mm-dd` in the user's LOCAL timezone — the value a date input
 * expects. Not `toISOString()`, which is UTC: in Damascus (UTC+3) that still
 * reads as yesterday until 03:00, so a form opened after midnight defaulted
 * every new record to the previous day.
 */
export function today(): string {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}
