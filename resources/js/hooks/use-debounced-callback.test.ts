import { renderHook } from '@testing-library/react';
import { act } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useDebouncedCallback } from './use-debounced-callback';

describe('useDebouncedCallback', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('waits for the delay before calling, coalescing rapid calls into one', () => {
        const fn = vi.fn();
        const { result } = renderHook(() => useDebouncedCallback(fn, 300));

        act(() => {
            result.current.debounced('a');
            vi.advanceTimersByTime(100);
            result.current.debounced('b');
            vi.advanceTimersByTime(100);
            result.current.debounced('c');
        });
        expect(fn).not.toHaveBeenCalled();

        act(() => vi.advanceTimersByTime(300));
        expect(fn).toHaveBeenCalledTimes(1);
        expect(fn).toHaveBeenCalledWith('c');
    });

    it('flush calls immediately and cancels any pending debounced call', () => {
        const fn = vi.fn();
        const { result } = renderHook(() => useDebouncedCallback(fn, 300));

        act(() => {
            result.current.debounced('pending');
            result.current.flush('now');
        });
        expect(fn).toHaveBeenCalledTimes(1);
        expect(fn).toHaveBeenCalledWith('now');

        act(() => vi.advanceTimersByTime(300));
        expect(fn).toHaveBeenCalledTimes(1);
    });

    it('always uses the latest callback without requiring it to be memoized', () => {
        let seen = 0;
        const { result, rerender } = renderHook(({ value }) => useDebouncedCallback(() => (seen = value), 300), { initialProps: { value: 1 } });

        rerender({ value: 2 });
        act(() => {
            result.current.debounced();
            vi.advanceTimersByTime(300);
        });
        expect(seen).toBe(2);
    });
});
