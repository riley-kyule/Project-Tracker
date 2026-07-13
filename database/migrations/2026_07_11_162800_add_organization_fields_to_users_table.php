<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('password')->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->string('job_title')->nullable()->after('manager_id');
            $table->string('status')->default('active')->after('job_title')->index();
            $table->string('timezone')->default('UTC')->after('status');
            $table->jsonb('notification_preferences')->nullable()->after('timezone');
            $table->timestamp('last_login_at')->nullable()->after('notification_preferences');

            $table->index('department_id');
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['job_title', 'status', 'timezone', 'notification_preferences', 'last_login_at']);
        });
    }
};
