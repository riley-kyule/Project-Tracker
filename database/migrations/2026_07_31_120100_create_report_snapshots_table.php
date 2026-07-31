<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('report_type');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('generated_at');
            $table->jsonb('payload');
            $table->string('status')->default('generated');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        // A single unique index on (report_date, report_type, department_id) would not
        // stop duplicate CEO summaries: Postgres and SQLite both treat every NULL as
        // distinct, so two department_id-null rows for the same day would never collide.
        // Partial indexes close that gap for the department and CEO cases separately —
        // this constraint, not a last_sent_on column, is what makes report generation
        // idempotent under concurrent/overlapping scheduler runs.
        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX report_snapshots_department_unique
            ON report_snapshots (report_date, report_type, department_id)
            WHERE department_id IS NOT NULL;

            CREATE UNIQUE INDEX report_snapshots_ceo_unique
            ON report_snapshots (report_date, report_type)
            WHERE department_id IS NULL;
            SQL);

        // Immutable once generated: a retry inserts a new row (version + 1) rather
        // than mutating history — same pattern as audit_logs
        // (see make_audit_logs_immutable.php).
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_report_snapshot_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'report_snapshots are immutable';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS report_snapshots_immutable ON report_snapshots;

                CREATE TRIGGER report_snapshots_immutable
                BEFORE UPDATE OR DELETE ON report_snapshots
                FOR EACH ROW EXECUTE FUNCTION prevent_report_snapshot_mutation();
                SQL);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS report_snapshots_prevent_update;

                CREATE TRIGGER report_snapshots_prevent_update
                BEFORE UPDATE ON report_snapshots
                BEGIN
                    SELECT RAISE(ABORT, 'report_snapshots are immutable');
                END;

                DROP TRIGGER IF EXISTS report_snapshots_prevent_delete;

                CREATE TRIGGER report_snapshots_prevent_delete
                BEFORE DELETE ON report_snapshots
                BEGIN
                    SELECT RAISE(ABORT, 'report_snapshots are immutable');
                END;
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS report_snapshots_immutable ON report_snapshots;
                DROP FUNCTION IF EXISTS prevent_report_snapshot_mutation();
                SQL);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS report_snapshots_prevent_update;
                DROP TRIGGER IF EXISTS report_snapshots_prevent_delete;
                SQL);
        }

        Schema::dropIfExists('report_snapshots');
    }
};
