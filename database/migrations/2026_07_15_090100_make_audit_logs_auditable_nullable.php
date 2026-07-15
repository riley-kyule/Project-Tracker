<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Login attempt auditing needs to log against a nullable subject:
     * a failed login with an email that matches no user has nothing to
     * point `auditable_type`/`auditable_id` at.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_type DROP NOT NULL');
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_id DROP NOT NULL');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // SQLite has no ALTER COLUMN; rebuild the table, then recreate the
            // immutability triggers SQLite drops along with the old table.
            DB::unprepared(<<<'SQL'
                CREATE TABLE audit_logs_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    actor_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                    auditable_type VARCHAR NULL,
                    auditable_id INTEGER NULL,
                    event VARCHAR NOT NULL,
                    old_values TEXT NULL,
                    new_values TEXT NULL,
                    ip_address VARCHAR NULL,
                    user_agent VARCHAR NULL,
                    created_at DATETIME NOT NULL
                );

                INSERT INTO audit_logs_new
                    SELECT id, actor_id, auditable_type, auditable_id, event, old_values, new_values, ip_address, user_agent, created_at
                    FROM audit_logs;

                DROP TABLE audit_logs;
                ALTER TABLE audit_logs_new RENAME TO audit_logs;

                CREATE INDEX audit_logs_auditable_type_auditable_id_created_at_index
                    ON audit_logs (auditable_type, auditable_id, created_at);

                CREATE TRIGGER audit_logs_prevent_update
                BEFORE UPDATE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs are immutable');
                END;

                CREATE TRIGGER audit_logs_prevent_delete
                BEFORE DELETE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs are immutable');
                END;
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_type SET NOT NULL');
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_id SET NOT NULL');
        }

        // Not reversed for SQLite: a downgrade would need to first delete or
        // backfill any rows with a null auditable, which isn't safe to guess.
    }
};
