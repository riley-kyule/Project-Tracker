import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type ComparisonMode, type DateRange, type MarketingFilters, type MarketingWebsite } from '@/types/marketing-statistics';
import { router } from '@inertiajs/react';
import { useState } from 'react';

const ALL_SITES = 'all';

const RANGE_OPTIONS: { value: DateRange; label: string }[] = [
    { value: 'last_7_days', label: 'Last 7 days' },
    { value: 'last_30_days', label: 'Last 30 days' },
    { value: 'last_90_days', label: 'Last 90 days' },
    { value: 'custom', label: 'Custom range' },
];

const COMPARISON_OPTIONS: { value: ComparisonMode; label: string }[] = [
    { value: 'none', label: 'No comparison' },
    { value: 'previous_period', label: 'Previous period' },
    { value: 'previous_year', label: 'Previous year' },
    { value: 'custom', label: 'Custom range' },
];

const DEFAULTS: MarketingFilters = {
    website_id: ALL_SITES,
    range: 'last_30_days',
    date_from: '',
    date_to: '',
    comparison: 'none',
    compare_from: '',
    compare_to: '',
};

function toDateInput(date: Date): string {
    return date.toISOString().slice(0, 10);
}

export function FilterBar({ websites, selected, basePath }: { websites: MarketingWebsite[]; selected: MarketingFilters; basePath: string }) {
    const [pending, setPending] = useState<MarketingFilters>(selected);

    const apply = () => {
        const params: Record<string, string> = {
            website_id: pending.website_id,
            range: pending.range,
            comparison: pending.comparison,
        };

        if (pending.range === 'custom') {
            params.date_from = pending.date_from;
            params.date_to = pending.date_to;
        }

        if (pending.comparison === 'custom') {
            params.compare_from = pending.compare_from ?? '';
            params.compare_to = pending.compare_to ?? '';
        }

        router.get(basePath, params, { preserveState: true, preserveScroll: true });
    };

    const reset = () => {
        setPending(DEFAULTS);
        router.get(basePath, {}, { preserveState: true, preserveScroll: true });
    };

    const maxDate = toDateInput(new Date(Date.now() - 86400000));

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-end gap-2 rounded-xl border p-3">
            <div className="grid gap-1">
                <label className="text-muted-foreground text-xs">Website</label>
                <Select value={pending.website_id} onValueChange={(value) => setPending((p) => ({ ...p, website_id: value }))}>
                    <SelectTrigger className="w-52">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_SITES}>All Sites</SelectItem>
                        {websites.map((website) => (
                            <SelectItem key={website.website_id} value={website.website_id}>
                                {website.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid gap-1">
                <label className="text-muted-foreground text-xs">Date range</label>
                <Select value={pending.range} onValueChange={(value) => setPending((p) => ({ ...p, range: value as DateRange }))}>
                    <SelectTrigger className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {RANGE_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {pending.range === 'custom' && (
                <div className="flex items-end gap-1">
                    <input
                        type="date"
                        value={pending.date_from}
                        max={pending.date_to || maxDate}
                        onChange={(e) => setPending((p) => ({ ...p, date_from: e.target.value }))}
                        className="border-input bg-background h-10 rounded-md border px-2 text-sm"
                    />
                    <span className="text-muted-foreground pb-2 text-sm">to</span>
                    <input
                        type="date"
                        value={pending.date_to}
                        min={pending.date_from}
                        max={maxDate}
                        onChange={(e) => setPending((p) => ({ ...p, date_to: e.target.value }))}
                        className="border-input bg-background h-10 rounded-md border px-2 text-sm"
                    />
                </div>
            )}

            <div className="grid gap-1">
                <label className="text-muted-foreground text-xs">Comparison</label>
                <Select value={pending.comparison} onValueChange={(value) => setPending((p) => ({ ...p, comparison: value as ComparisonMode }))}>
                    <SelectTrigger className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {COMPARISON_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {pending.comparison === 'custom' && (
                <div className="flex items-end gap-1">
                    <input
                        type="date"
                        value={pending.compare_from ?? ''}
                        max={pending.compare_to || maxDate}
                        onChange={(e) => setPending((p) => ({ ...p, compare_from: e.target.value }))}
                        className="border-input bg-background h-10 rounded-md border px-2 text-sm"
                    />
                    <span className="text-muted-foreground pb-2 text-sm">to</span>
                    <input
                        type="date"
                        value={pending.compare_to ?? ''}
                        min={pending.compare_from ?? undefined}
                        max={maxDate}
                        onChange={(e) => setPending((p) => ({ ...p, compare_to: e.target.value }))}
                        className="border-input bg-background h-10 rounded-md border px-2 text-sm"
                    />
                </div>
            )}

            <div className="ml-auto flex gap-2">
                <Button variant="outline" onClick={reset} type="button">
                    Reset
                </Button>
                <Button onClick={apply} type="button">
                    Apply
                </Button>
            </div>
        </div>
    );
}
