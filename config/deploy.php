<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Self-service deployment
    |--------------------------------------------------------------------------
    |
    | Lets an Administrator/CEO trigger the documented release sequence
    | (see docs/DEPLOYMENT.md) from the app itself. Requires the app/queue/
    | scheduler containers to bind-mount the real working directory (see
    | docker-compose.yml) rather than bake code into the image — the app/
    | queue/scheduler Dockerfile stage installs git/composer/npm for exactly
    | this. Off by default; only turn on where that bind-mount setup is
    | actually in place.
    |
    */

    'enabled' => (bool) env('DEPLOY_SELF_UPDATE_ENABLED', false),

    'branch' => env('DEPLOY_BRANCH', 'main'),

    'timeout' => (int) env('DEPLOY_TIMEOUT', 600),

];
