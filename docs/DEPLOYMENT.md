# Production deployment

## Required configuration

Start from `.env.example`, inject secrets through the deployment platform, and never commit the production environment file.

At minimum, set:

```dotenv
APP_NAME=EWMS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ewms.example.com
APP_TIMEZONE=UTC
APP_KEY=base64:generated-secret

ALLOW_REGISTRATION=false
ALLOW_ACCOUNT_DELETION=false

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

DB_CONNECTION=pgsql
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
```

Use distinct database and Redis credentials with the least privileges required by the application. Configure a real `MAIL_MAILER` and organizational sender address so password resets and notifications are deliverable.

If TLS terminates at a reverse proxy or load balancer, configure Laravel's trusted proxy handling so secure URLs, cookies, client IP audit data, and HSTS are correct.

## Release sequence

```bash
composer install --no-dev --classmap-authoritative --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Run `php artisan test`, `vendor/bin/pint --test`, `npm run check`, and dependency audits in CI before deploying the artifact. Do not build from a mutable working directory on the production host.

## Long-running processes

- Run `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` under Supervisor, systemd, or an equivalent orchestrator.
- Run `php artisan schedule:run` every minute. The scheduler generates recurring tasks and due notifications.
- Monitor failed jobs and queue latency. Establish a documented retry or discard procedure.

## Data and files

- Back up PostgreSQL and private attachments together, encrypt backups, and test restores.
- Define retention for database sessions, logs, failed jobs, notifications, and attachments.
- Keep attachments outside the public web root. Downloads must continue through authorized application routes.
- The upload layer validates extension, MIME content, size, checksum, and parent-record access. Integrate malware scanning before accepting higher-risk formats or external users.

## Release verification

- Confirm `/up` succeeds from the load balancer.
- Exercise login, a board mutation, an attachment download, a ticket lifecycle change, and audit history with representative roles.
- Verify secure cookie attributes, security headers, mail delivery, queue processing, scheduled commands, backups, and error reporting.
- Roll back application code only with a database-compatible release. Review every migration's `down` path before executing rollback in production.
