import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Avoids a skeleton flash on fast/cached navigations — only shows once a visit has been in flight this long. */
const SHOW_DELAY_MS = 200;

/**
 * True while a genuine full page navigation (a link click, a filter Apply,
 * router.get/post to a new page) has been in flight for longer than
 * SHOW_DELAY_MS. Backs the app-wide navigation skeleton — see
 * NavigationLoadingOverlay.
 *
 * Deliberately ignores partial/deferred visits (`visit.only.length > 0`) —
 * Inertia's <Deferred> fetches its props via `router.reload({ only: [...] })`
 * in the background *after* a page has already painted real content, which
 * fires these same 'start'/'finish' events. Without this filter, every
 * deferred-prop fetch would re-show this overlay on top of already-loaded
 * content; those sections already have their own local skeleton fallback.
 */
export function useNavigationLoading(): boolean {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | null = null;

        const stopStart = router.on('start', (event) => {
            if (event.detail.visit.only.length > 0) {
                return;
            }
            timer = setTimeout(() => setIsLoading(true), SHOW_DELAY_MS);
        });

        const stopFinish = router.on('finish', (event) => {
            if (event.detail.visit.only.length > 0) {
                return;
            }
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
            setIsLoading(false);
        });

        return () => {
            stopStart();
            stopFinish();
            if (timer) {
                clearTimeout(timer);
            }
        };
    }, []);

    return isLoading;
}
