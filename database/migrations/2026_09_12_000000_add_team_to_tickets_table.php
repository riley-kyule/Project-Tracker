<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // 'it'|'rnd'|'both' — see Ticket::TEAMS. Existing rows predate the
            // R&D queue entirely, so they backfill to 'it', the only team that
            // ever serviced tickets until now.
            $table->string('team', 8)->default('it')->after('department_id');
            $table->index('team');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('team');
        });
    }
};
