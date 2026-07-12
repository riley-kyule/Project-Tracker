<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // CREATE OR REPLACE + a preceding DROP TRIGGER keep this safe to
            // re-run: migrate:fresh drops tables but not raw SQL functions,
            // so a plain CREATE would fail as a duplicate on a second run.
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_audit_log_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs are immutable';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs;

                CREATE TRIGGER audit_logs_immutable
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation();
                SQL);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS audit_logs_prevent_update;

                CREATE TRIGGER audit_logs_prevent_update
                BEFORE UPDATE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs are immutable');
                END;

                DROP TRIGGER IF EXISTS audit_logs_prevent_delete;

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
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs;
                DROP FUNCTION IF EXISTS prevent_audit_log_mutation();
                SQL);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS audit_logs_prevent_update;
                DROP TRIGGER IF EXISTS audit_logs_prevent_delete;
                SQL);
        }
    }
};
