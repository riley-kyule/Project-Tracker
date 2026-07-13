export type MarketingWebsite = { website_id: string; domain: string; name: string };

export type Kpi = {
    current: number | null;
    comparison: number | null;
    absolute_change: number | null;
    percentage_change: number | null;
    data_source: string;
    last_updated: string | null;
};

export type SourceStatus = { status: 'ok' | 'missing' | 'failed'; error: string | null };

export type DateRange = 'last_7_days' | 'last_30_days' | 'last_90_days' | 'custom';
export type ComparisonMode = 'none' | 'previous_period' | 'previous_year' | 'custom';

export type MarketingFilters = {
    website_id: string;
    range: DateRange;
    date_from: string;
    date_to: string;
    comparison: ComparisonMode;
    compare_from?: string | null;
    compare_to?: string | null;
};
