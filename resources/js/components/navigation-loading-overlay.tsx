import { Skeleton } from '@/components/ui/skeleton';
import { useNavigationLoading } from '@/hooks/use-navigation-loading';

/**
 * App-wide "something is coming" signal for ordinary page navigation —
 * every Inertia visit (clicking a nav link, a filter Apply button, a
 * partial reload) fires the same router 'start'/'finish' events this
 * reads, so one mount here covers every page without per-page skeletons.
 * Deferred-prop sections (Marketing Statistics breakdowns, etc.) have
 * their own more specific skeletons since those load *within* an
 * already-painted page rather than during the navigation itself.
 */
export function NavigationLoadingOverlay() {
    const isLoading = useNavigationLoading();

    if (!isLoading) {
        return null;
    }

    return (
        <div
            className="bg-background/80 absolute inset-0 z-40 flex flex-col gap-4 overflow-hidden p-4 backdrop-blur-[1px]"
            role="status"
            aria-live="polite"
            aria-label="Loading"
        >
            <Skeleton className="h-7 w-48" />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} className="h-24 rounded-xl" />
                ))}
            </div>
            <Skeleton className="h-64 rounded-xl" />
            <div className="grid gap-4 lg:grid-cols-2">
                <Skeleton className="h-48 rounded-xl" />
                <Skeleton className="h-48 rounded-xl" />
            </div>
        </div>
    );
}
