<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Distinct from recurrence_rule_id (RecurrenceRule generates brand
            // new task instances from a template): this resets the same task
            // in place — back to its board's Ready column — on a fixed clock,
            // regardless of what column it's currently sitting in.
            $table->string('auto_reset_frequency')->nullable()->after('reopened_at');
            $table->timestamp('last_auto_reset_at')->nullable()->after('auto_reset_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['auto_reset_frequency', 'last_auto_reset_at']);
        });
    }
};
