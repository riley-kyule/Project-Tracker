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

/**
 * Human-readable length of time from a minute count (rounded). Examples:
 * 0.4 → 'under a minute', 21 → '21m', 185 → '3h 5m', 3120 → '2d 4h'.
 */
export function fmtDuration(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined || Number.isNaN(minutes)) return '—';
    const total = Math.max(0, Math.round(minutes));
    if (total < 1) return 'under a minute';
    if (total < 60) return `${total}m`;
    if (total < 1440) {
        const h = Math.floor(total / 60);
        const m = total % 60;
        return m === 0 ? `${h}h` : `${h}h ${m}m`;
    }
    const d = Math.floor(total / 1440);
    const h = Math.floor((total % 1440) / 60);
    return h === 0 ? `${d}d` : `${d}d ${h}h`;
}

/** YYYY-MM-DD (or ISO datetime) → 'DD/MM/YYYY' for date inputs. Empty/invalid → ''. */
export function toDmy(value: string | null | undefined): string {
    if (!value) return '';
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    if (!match) return '';
    const [, y, m, d] = match;
    return `${d}/${m}/${y}`;
}

/**
 * 'DD/MM/YYYY' (also accepts d/m/yy, '-' or '.' separators) → 'YYYY-MM-DD'.
 * Returns null when the text can't be parsed or the date isn't real.
 */
export function parseDmy(text: string): string | null {
    const match = /^\s*(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})\s*$/.exec(text);
    if (!match) return null;
    const day = Number(match[1]);
    const month = Number(match[2]);
    let year = Number(match[3]);
    if (match[3].length === 2) year += year < 70 ? 2000 : 1900;
    if (month < 1 || month > 12 || day < 1 || day > 31) return null;
    const iso = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const check = new Date(`${iso}T00:00:00`);
    if (Number.isNaN(check.getTime()) || check.getDate() !== day || check.getMonth() + 1 !== month) return null;
    return iso;
}
