import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/**
 * Standard app date display: DD/MMM/YYYY (e.g. 04/Sep/2026). Accepts a
 * `YYYY-MM-DD` string, an ISO datetime, or a Date; returns '—' for empty.
 */
export function fmtDate(value: string | number | Date | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const d = value instanceof Date ? value : new Date(typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value);
    if (Number.isNaN(d.getTime())) return String(value);
    return `${String(d.getDate()).padStart(2, '0')}/${MONTHS[d.getMonth()]}/${d.getFullYear()}`;
}

/** DD/MMM/YYYY HH:mm for timestamps. */
export function fmtDateTime(value: string | number | Date | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const d = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return `${fmtDate(d)} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}
