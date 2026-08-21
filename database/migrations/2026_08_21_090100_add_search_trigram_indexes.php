<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SearchController runs `lower(column) like ?` (a leading-wildcard scan) on
 * every keystroke with no index behind it — every search hits a sequential
 * scan on tasks/tickets/boards/users. pg_trgm's GIN operator class supports
 * LIKE directly, so an index on the exact `lower(column)` expression the
 * query already uses lets Postgres pick it up automatically — no query
 * rewrite needed. SQLite (tests, local dev) has no trigram index and keeps
 * doing the same unindexed scan it always has; only Postgres gets the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::unprepared('CREATE INDEX IF NOT EXISTS tasks_title_trgm_idx ON tasks USING gin (lower(title) gin_trgm_ops)');
        DB::unprepared('CREATE INDEX IF NOT EXISTS tasks_description_trgm_idx ON tasks USING gin (lower(description) gin_trgm_ops)');
        DB::unprepared('CREATE INDEX IF NOT EXISTS tickets_title_trgm_idx ON tickets USING gin (lower(title) gin_trgm_ops)');
        DB::unprepared('CREATE INDEX IF NOT EXISTS tickets_description_trgm_idx ON tickets USING gin (lower(description) gin_trgm_ops)');
        DB::unprepared('CREATE INDEX IF NOT EXISTS boards_name_trgm_idx ON boards USING gin (lower(name) gin_trgm_ops)');
        DB::unprepared('CREATE INDEX IF NOT EXISTS users_name_trgm_idx ON users USING gin (lower(name) gin_trgm_ops)');
        DB::unprepared('CREATE INDEX IF NOT EXISTS users_email_trgm_idx ON users USING gin (lower(email) gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP INDEX IF EXISTS users_email_trgm_idx');
        DB::unprepared('DROP INDEX IF EXISTS users_name_trgm_idx');
        DB::unprepared('DROP INDEX IF EXISTS boards_name_trgm_idx');
        DB::unprepared('DROP INDEX IF EXISTS tickets_description_trgm_idx');
        DB::unprepared('DROP INDEX IF EXISTS tickets_title_trgm_idx');
        DB::unprepared('DROP INDEX IF EXISTS tasks_description_trgm_idx');
        DB::unprepared('DROP INDEX IF EXISTS tasks_title_trgm_idx');
    }
};
