# EWMS

EWMS is a single-company employee work management system for operational work, projects, collaboration, and internal IT support. It is built as a Laravel modular monolith with React and TypeScript through Inertia.js.

## Current capabilities

- Role- and policy-controlled authentication, users, and departments
- Company, department, and restricted Kanban boards
- Tasks, dependencies, recurring work, approvals, time entries, labels, and priorities
- Comments, mentions, checklists, attachments, notifications, and immutable audit history
- IT service desk with SLA due dates, assignment, lifecycle history, resolution reporting, and ticket-to-task conversion
- Projects, country and website registries, dashboards, reports, CSV export, and global search
- Responsive light/dark UI with keyboard navigation and reduced-motion support

## Requirements

- PHP 8.2 or newer with Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, and XML extensions
- Composer 2
- PostgreSQL 15 or newer
- Redis 7 or newer
- Node.js 20 or newer and npm 10 or newer
- A process supervisor for queue workers and a cron-compatible scheduler in production
- HTTPS in production

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the PostgreSQL database and role referenced by `.env`, then run:

```bash
php artisan migrate --seed
npm run build
composer dev
```

The default configuration uses PostgreSQL, Redis-backed queues/cache, database sessions, UTC storage, private local attachments, and log-based email delivery. Set a real mail transport before production use.

Public registration and employee self-deletion are intentionally disabled. Provision employees with the administrator workflow or `php artisan ewms:import-users`.

## Quality checks

```bash
php artisan test
vendor/bin/pint --test
npm run check
composer audit --locked --no-interaction
npm audit --omit=dev --package-lock-only
```

Feature tests use an in-memory SQLite database for speed. Before release, also run migrations and representative tests against the supported PostgreSQL version.

## Production operations

Deployments must run migrations, build immutable frontend assets, restart queue workers, and keep the scheduler active. Configure HTTPS, secure cookies, trusted proxies, backups, log retention, mail, Redis persistence, and monitoring before onboarding users.

See [Production deployment](docs/DEPLOYMENT.md) and [Security model](docs/SECURITY.md) for the full checklist.

## Product principles

- Operational adoption and clear ownership over feature breadth
- Authorization and auditability for critical changes
- Persist before dispatching side effects
- Contextual metrics, never employee-surveillance rankings
- External analytics are asynchronous and never queried during an interactive page request
