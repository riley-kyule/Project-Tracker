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
        // the product of the two is the worst-case total wait.
        'query_timeout_ms' => (int) env('BIGQUERY_QUERY_TIMEOUT_MS', 10000),
        'query_max_retries' => (int) env('BIGQUERY_QUERY_MAX_RETRIES', 6),
    ],

];
