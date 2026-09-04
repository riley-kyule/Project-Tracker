<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BigQuery connectivity
    |--------------------------------------------------------------------------
    |
    | EWMS reads pre-aggregated analytics from an existing BigQuery reporting
    | dataset (analytics_core) that a separate pipeline already maintains —
    | see database/bigquery/README.md. Empty by default: TrafficDashboardQuery
    | checks GoogleBigQueryRunner::isConfigured() and returns a graceful
    | "not configured" response instead of crashing.
    |
    */

    'bigquery' => [
        'project_id' => env('BIGQUERY_PROJECT_ID'),
        'location' => env('BIGQUERY_LOCATION', 'US'),

        // Path to a service account key file. Leave blank to fall back to
        // Application Default Credentials (e.g. Workload Identity).
        'credentials_path' => env('BIGQUERY_CREDENTIALS_PATH'),

        // GoogleBigQueryRunner passes these straight to the SDK's runQuery()
        // as 'timeoutMs'/'maxRetries'. Without an explicit maxRetries, the
        // SDK polls for job completion indefinitely — a stuck query would
        // tie up the PHP-FPM worker handling the request with no upper
        // bound. query_timeout_ms is how long each individual poll waits;
        // the product of the two is the worst-case total wait. Kept well
        // under a typical 60s web-server proxy timeout so a slow query
        // degrades to a caught "source failed" instead of a 502.
        'query_timeout_ms' => (int) env('BIGQUERY_QUERY_TIMEOUT_MS', 12000),
        'query_max_retries' => (int) env('BIGQUERY_QUERY_MAX_RETRIES', 3),

        // Ahrefs has no BigQuery pipeline yet (analytics_core.ahrefs_daily_site
        // does not exist) — every Ahrefs query fails. Off by default so the
        // module doesn't run doomed queries or show a permanent "failed"
        // badge; flip on once the pipeline lands. See AhrefsReportQuery.
        'ahrefs_enabled' => (bool) env('ANALYTICS_AHREFS_ENABLED', false),
    ],

];
