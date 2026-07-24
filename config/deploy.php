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

    /*
    |--------------------------------------------------------------------------
    | Compose project name
    |--------------------------------------------------------------------------
    |
    | Needed so DeployLatestRelease can restart the app/scheduler containers
    | after a deploy (`docker compose --project-name ... restart app
    | scheduler`) — see docs/DEPLOYMENT.md. Compose normally infers the
    | project name from the current directory's basename, but inside the
    | container the bind-mounted checkout lives at /var/www/html, which
    | would infer the wrong name. Must match the actual project name on the
    | host (check with `docker compose ls`). Left unset, that restart step
    | is skipped rather than risk targeting the wrong project.
    |
    */

    'compose_project' => env('DEPLOY_COMPOSE_PROJECT'),

];
