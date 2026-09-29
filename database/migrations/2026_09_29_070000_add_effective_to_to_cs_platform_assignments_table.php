<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_platform_assignments', function (Blueprint $table) {
            // Customer Service Board Requirements Specification v1.0 §12:
            // "Historical records must retain the HOD and platform
            // assignment effective at the time, even after ownership
            // changes." Deactivating an assignment previously only flipped
            // is_active, losing the exact date it stopped applying — this
            // closes that so a past date can still be checked against
            // whatever was actually assigned then. Same pattern as
            // department_notification_recipients.effective_to.
            $table->date('effective_to')->nullable()->after('effective_from');
        });
    }

    public function down(): void
    {
        Schema::table('cs_platform_assignments', function (Blueprint $table) {
            $table->dropColumn('effective_to');
        });
    }
};
