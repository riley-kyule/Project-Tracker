<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('approval_status')->nullable()->after('progress_percentage')->index();
            $table->foreignId('approver_id')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approver_id');
            $table->string('approval_note')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropConstrainedForeignId('approver_id');
            $table->dropColumn(['approval_status', 'approved_at', 'approval_note']);
        });
    }
};
