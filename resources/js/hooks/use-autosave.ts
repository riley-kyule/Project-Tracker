import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Debounced field-level auto-save against an Inertia PATCH endpoint. Callers
 * push partial payloads via `save({ field: value })`; text inputs let the
 * debounce coalesce keystrokes, everything else passes `{ immediate: true }`.
 * `flush()` forces a pending save (call it on blur and on unmount). On error
 * the payload is kept so the next edit — or an explicit `flush()` retry —
 * re-sends it.
 */
export function useAutosave(url: string, { debounceMs = 500 }: { debounceMs?: number } = {}) {
    const [status, setStatus] = useState<SaveStatus>('idle');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const pending = useRef<Record<string, unknown>>({});
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const savedTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const urlRef = useRef(url);
    urlRef.current = url;

    const send = useCallback(() => {
        if (timer.current) {
            clearTimeout(timer.current);
            timer.current = null;
        }
        const payload = pending.current;
        if (Object.keys(payload).length === 0) return;
        pending.current = {};
        setStatus('saving');
        router.patch(urlRef.current, payload as Record<string, string | number | boolean | null>, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setErrors({});
                setStatus('saved');
                if (savedTimer.current) clearTimeout(savedTimer.current);
                savedTimer.current = setTimeout(() => setStatus('idle'), 2000);
            },
            onError: (formErrors) => {
                // Keep unsaved values (newer edits win) so a retry re-sends them.
                pending.current = { ...payload, ...pending.current };
                setErrors(formErrors as Record<string, string>);
                setStatus('error');
            },
        });
    }, []);

    const save = useCallback(
        (patch: Record<string, unknown>, opts: { immediate?: boolean } = {}) => {
            pending.current = { ...pending.current, ...patch };
            if (timer.current) clearTimeout(timer.current);
            if (opts.immediate) {
                send();
                return;
            }
            timer.current = setTimeout(send, debounceMs);
        },
        [send, debounceMs],
    );

    const flush = useCallback(() => {
        if (timer.current || Object.keys(pending.current).length > 0) send();
    }, [send]);

    // Flush anything still pending when the component using this goes away.
    useEffect(() => () => flush(), [flush]);

    return { status, errors, save, flush };
}
