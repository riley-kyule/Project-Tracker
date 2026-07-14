import { type SourceStatus } from '@/types/marketing-statistics';
import { useEffect, useMemo } from 'react';
import { toast } from 'sonner';

const LABELS: Record<string, string> = { ga4: 'GA4', gsc: 'Google Search Console', ahrefs: 'Ahrefs' };

const STATUS_TEXT: Record<string, string> = {
    missing: 'has no data for this range',
    failed: 'failed to load',
    delayed: 'data is delayed',
};

/**
 * Fires a toast per unavailable source instead of a permanent on-page
 * banner — a source that's down stays down until the data changes, but the
 * page shouldn't nag about it in a fixed strip the whole time it's open.
 */
export function useSourceStatusToasts(sources?: Record<string, SourceStatus>) {
    const issues = useMemo(() => Object.entries(sources ?? {}).filter(([, source]) => source.status !== 'ok'), [sources]);
    const signature = JSON.stringify(issues);

    useEffect(() => {
        issues.forEach(([key, source]) => {
            const label = LABELS[key] ?? key;
            const text = STATUS_TEXT[source.status] ?? source.status;
            toast.warning(`${label} ${text}`, { description: source.error ?? undefined });
        });
        // Only refire when the actual issue set changes, not on every render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [signature]);
}
