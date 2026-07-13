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
    ],

];
