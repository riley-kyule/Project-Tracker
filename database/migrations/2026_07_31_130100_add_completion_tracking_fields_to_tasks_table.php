<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Optional "what I did" note captured at the moment a task enters
            // the completion column — the daily summary prefers this over the
            // bare task title when present (TaskMover::move()).
            $table->text('completion_note')->nullable()->after('completed_at');

            // completed_at alone can't distinguish "completed once, still
            // complete" from "completed, then reopened" — it's simply cleared
            // and reset on every re-entry into the completion column.
            // first_completed_at is set once and never overwritten;
            // reopened_at is set when a previously-completed task leaves the
            // completion column, and cleared again once it re-completes.
            $table->timestamp('first_completed_at')->nullable()->after('completion_note');
            $table->timestamp('reopened_at')->nullable()->after('first_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['completion_note', 'first_completed_at', 'reopened_at']);
        });
    }
};
