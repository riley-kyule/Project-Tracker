<?php

return [

    'api' => [
        'enabled' => (bool) env('ANALYTICS_API_ENABLED', false),
        'credentials_path' => env('ANALYTICS_GOOGLE_CREDENTIALS_PATH'),
        'request_timeout' => (int) env('ANALYTICS_API_TIMEOUT', 30),
        'request_delay_ms' => (int) env('ANALYTICS_API_REQUEST_DELAY_MS', 150),
        'key_events' => array_values(array_filter(array_map('trim', explode(',', env(
            'ANALYTICS_GA4_KEY_EVENTS',
            'WhatsApp,Telegram,CallNow,ViewProfile,Favorite,ShareProfile,SMS,TelNow,PWAInstall,Viber'
        ))))),

        // Popcash has no local pipeline until real API credentials are
        // confirmed (endpoint/auth/field names are unverified against
        // Popcash's docs) — off by default so the module doesn't run
        // doomed requests. See PopcashApiClient. Same reasoning as
        // bigquery.ahrefs_enabled below, just for the local-sync path.
        'popcash' => [
            'enabled' => (bool) env('ANALYTICS_POPCASH_ENABLED', false),
            'api_key' => env('ANALYTICS_POPCASH_API_KEY'),
            'base_url' => env('ANALYTICS_POPCASH_BASE_URL', 'https://api.popcash.net'),
            'request_timeout' => (int) env('ANALYTICS_POPCASH_TIMEOUT', 30),
        ],
    ],

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
