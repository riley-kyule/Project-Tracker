<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // Comma-separated list of email domains allowed to sign in, e.g. "example.com,example.org".
        'allowed_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_ALLOWED_DOMAINS', '')),
        ))),
    ],

    // Exotic Push Engine — per-user browser push. Left blank, PushNotifier
    // no-ops until both are set (same "unavailable until configured" pattern
    // as the Ahrefs/CRM analytics sources).
    'epe' => [
        'api_url' => env('EPE_API_URL'),
        'site_key' => env('EPE_SITE_KEY'),
    ],

];
