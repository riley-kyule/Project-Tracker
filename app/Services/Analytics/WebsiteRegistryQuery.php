<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\BigQueryRunner;

/**
 * The real, authoritative website registry GA4's own `vw_*` views join
 * against (`metadata.websites` — a separate BigQuery dataset from both
 * `analytics_core` and the raw `analytics_<PROPERTY_ID>` datasets; see
 * ANALYTICS_BIGQUERY_FINDINGS.md). Confirmed directly queryable.
 *
 * `domain` is used as the one identifier shared across GA4 (which has no
 * separate ID, only `dataset_id` + `website_domain`) and GSC (whose daily
 * tables carry both `website_id` and `domain`) — `website_id` alone can't
 * serve as the cross-source key because GA4's registry doesn't have one.
 */
class WebsiteRegistryQuery
{
    public function __construct(private BigQueryRunner $runner) {}

    /** @return array<int, array{domain: string, name: string, country: string|null}> */
    public function websites(): array
    {
        $rows = $this->runner->rows(<<<'SQL'
            SELECT website_domain, website_name, country
            FROM `metadata.websites`
            WHERE active = TRUE
            ORDER BY website_name
            SQL);

        return array_map(fn (array $row) => [
            'domain' => $row['website_domain'],
            'name' => $row['website_name'],
            'country' => $row['country'] ?? null,
        ], $rows);
    }
}
