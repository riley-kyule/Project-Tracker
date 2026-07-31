<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Null means "ordinary comment" — every existing row keeps that
            // meaning unchanged. A non-null value marks it as a structured
            // progress note (see App\Models\Comment::NOTE_TYPES), which the
            // daily-summary builders surface separately from casual comments.
            $table->string('note_type')->nullable()->after('body');
            $table->jsonb('structured_fields')->nullable()->after('note_type');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['note_type', 'structured_fields']);
        });
    }
};
