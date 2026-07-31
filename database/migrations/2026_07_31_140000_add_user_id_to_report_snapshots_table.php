<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_snapshots', function (Blueprint $table) {
            // Personal (weekly) reports set this and leave department_id null;
            // department/CEO reports leave it null. Never both null+null except
            // for the CEO daily — see the tightened ceo_unique index below.
            $table->foreignId('user_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        // The original department_id-IS-NULL index was written before
        // personal reports existed and only had to disambiguate "CEO daily" —
        // it would incorrectly collide two different employees' weekly
        // summaries on the same date, since it ignores user_id entirely.
        // Replace it with one scoped to "neither dimension set" (CEO daily)
        // and add a matching per-user index for personal reports.
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS report_snapshots_ceo_unique;

            CREATE UNIQUE INDEX report_snapshots_ceo_unique
            ON report_snapshots (report_date, report_type)
            WHERE department_id IS NULL AND user_id IS NULL;

            CREATE UNIQUE INDEX report_snapshots_user_unique
            ON report_snapshots (report_date, report_type, user_id)
            WHERE user_id IS NOT NULL;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS report_snapshots_user_unique;
            DROP INDEX IF EXISTS report_snapshots_ceo_unique;

            CREATE UNIQUE INDEX report_snapshots_ceo_unique
            ON report_snapshots (report_date, report_type)
            WHERE department_id IS NULL;
            SQL);

        Schema::table('report_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
