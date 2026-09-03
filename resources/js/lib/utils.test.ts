import { describe, expect, it } from 'vitest';
import { fmtDate, fmtDateTime, fmtDuration, parseDmy, toDmy } from './utils';

describe('fmtDate', () => {
    it('formats a YYYY-MM-DD string as DD/MMM/YYYY without timezone drift', () => {
        expect(fmtDate('2026-09-05')).toBe('05/Sep/2026');
    });

    it('returns an em dash for empty input', () => {
        expect(fmtDate('')).toBe('—');
        expect(fmtDate(null)).toBe('—');
    });
});

describe('fmtDateTime', () => {
    it('appends 24h HH:mm', () => {
        expect(fmtDateTime('2026-09-05T14:32:00')).toBe('05/Sep/2026 14:32');
    });
});

describe('fmtDuration', () => {
    it.each([
        [0.4, 'under a minute'],
        [21, '21m'],
        [20.516666, '21m'],
        [60, '1h'],
        [185, '3h 5m'],
        [1440, '1d'],
        [3120, '2d 4h'],
    ])('%d minutes → %s', (input, expected) => {
        expect(fmtDuration(input)).toBe(expected);
    });
});

describe('toDmy / parseDmy', () => {
    it('round-trips an ISO date', () => {
        expect(toDmy('2026-09-05')).toBe('05/09/2026');
        expect(parseDmy('05/09/2026')).toBe('2026-09-05');
    });

    it('accepts alternative separators and 2-digit years', () => {
        expect(parseDmy('5-9-26')).toBe('2026-09-05');
        expect(parseDmy('05.09.2026')).toBe('2026-09-05');
    });

    it('rejects impossible dates', () => {
        expect(parseDmy('31/02/2026')).toBeNull();
        expect(parseDmy('12/34/2026')).toBeNull();
        expect(parseDmy('nonsense')).toBeNull();
    });

    it('toDmy is empty for blank/invalid input', () => {
        expect(toDmy('')).toBe('');
        expect(toDmy(null)).toBe('');
    });
});
