<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BigQuery connectivity
    |--------------------------------------------------------------------------
    |
    | Empty by default: every analytics sync job checks GoogleBigQueryRunner::
    | isConfigured() and records a failed sync log (not a crash) until a
    | project, dataset, and credentials file are set.
    |
    */

    'bigquery' => [
        'project_id' => env('BIGQUERY_PROJECT_ID'),
        'location' => env('BIGQUERY_LOCATION', 'US'),

        // Path to a service account key file. Leave blank to fall back to
        // Application Default Credentials (e.g. Workload Identity).
        'credentials_path' => env('BIGQUERY_CREDENTIALS_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GA4 (BigQuery Export)
    |--------------------------------------------------------------------------
    |
    | Native GA4 BigQuery Export creates one dataset per property named
    | "analytics_<property_id>" in the destination project. Override the
    | pattern if your export lands somewhere else.
    |
    */

    'ga4' => [
        'dataset_pattern' => env('GA4_BIGQUERY_DATASET_PATTERN', 'analytics_{property_id}'),

        // Event names counted as "key events" for the daily rollup.
        'key_events' => [
            'WhatsApp', 'Telegram', 'CallNow', 'ViewProfile', 'Favorite',
            'ShareProfile', 'SMS', 'TelNow', 'PWAInstall', 'Viber',
        ],
    ],

];
