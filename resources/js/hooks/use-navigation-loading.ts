import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Avoids a skeleton flash on fast/cached navigations — only shows once a visit has been in flight this long. */
const SHOW_DELAY_MS = 200;

/**
 * True while an Inertia visit (page navigation, router.get/post/reload,
 * partial reload) has been in flight for longer than SHOW_DELAY_MS. Backs
 * the app-wide navigation skeleton — see NavigationLoadingOverlay.
 */
export function useNavigationLoading(): boolean {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | null = null;

        const stopStart = router.on('start', () => {
            timer = setTimeout(() => setIsLoading(true), SHOW_DELAY_MS);
        });

        const stopFinish = router.on('finish', () => {
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
