import { type SourceStatus } from '@/types/marketing-statistics';

const LABELS: Record<string, string> = { ga4: 'GA4', gsc: 'Google Search Console', ahrefs: 'Ahrefs' };

const STATUS_TEXT: Record<string, string> = {
    missing: 'has no data for this range',
    failed: 'failed to load',
    delayed: 'data is delayed',
};

export function SourceStatusBanner({ sources }: { sources: Record<string, SourceStatus> }) {
    const issues = Object.entries(sources).filter(([, source]) => source.status !== 'ok');

    if (issues.length === 0) {
        return null;
    }

    return (
        <div className="rounded-md border border-dashed border-amber-400/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            <p className="font-medium">Some sources aren't fully available for this view:</p>
            <ul className="mt-1 list-inside list-disc space-y-0.5">
                {issues.map(([key, source]) => (
                    <li key={key}>
                        {LABELS[key] ?? key} {STATUS_TEXT[source.status] ?? source.status}
                        {source.error && <span className="text-muted-foreground ml-1 font-mono text-xs">({source.error})</span>}
                    </li>
                ))}
            </ul>
        </div>
    );
}
