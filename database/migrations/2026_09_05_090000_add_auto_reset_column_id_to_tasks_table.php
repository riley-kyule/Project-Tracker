<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Where an auto-reset should drop the task. Null = fall back to the
            // board's Ready column (see ResetRecurringTask). Keeps the "some
            // tasks uncheck but never move" case fixable per task.
            $table->foreignId('auto_reset_column_id')
                ->nullable()
                ->after('last_auto_reset_at')
                ->constrained('board_columns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auto_reset_column_id');
        });
    }
};
