<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Self-service deployment
    |--------------------------------------------------------------------------
    |
    | Lets an Administrator/CEO trigger the documented release sequence
    | (see docs/DEPLOYMENT.md) from the app itself. Off by default: enable
    | only on hosts where the web/queue process is meant to own its own
    | working directory (e.g. a single-server deployment), never behind an
    | immutable-artifact pipeline.
    |
    */

    'enabled' => (bool) env('DEPLOY_SELF_UPDATE_ENABLED', false),

    'branch' => env('DEPLOY_BRANCH', 'main'),

    'timeout' => (int) env('DEPLOY_TIMEOUT', 600),

];
