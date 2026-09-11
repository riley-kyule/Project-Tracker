import { useEffect, useRef } from 'react';

/**
 * Returns a stable function that delays calling `callback` until `delayMs`
 * has passed with no further calls — for driving a server round-trip (a
 * filter's `router.get`) off free typing without firing one request per
 * keystroke. The latest `callback` is always used (via a ref, not a
 * dependency) so callers don't need to memoize it themselves, and any
 * pending call is cancelled on unmount.
 */
export function useDebouncedCallback<Args extends unknown[]>(callback: (...args: Args) => void, delayMs: number) {
    const callbackRef = useRef(callback);
    callbackRef.current = callback;

    const timeoutRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(timeoutRef.current), []);

    return {
        debounced: (...args: Args) => {
            clearTimeout(timeoutRef.current);
            timeoutRef.current = setTimeout(() => callbackRef.current(...args), delayMs);
        },
        flush: (...args: Args) => {
            clearTimeout(timeoutRef.current);
            callbackRef.current(...args);
        },
    };
}
