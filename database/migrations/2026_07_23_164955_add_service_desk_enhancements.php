<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->unsignedInteger('response_gap_minutes')->nullable()->after('resolution_minutes');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('requester_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('first_responded_at');
            $table->timestamp('last_response_at')->nullable()->after('assigned_at');
            $table->string('closed_reason')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['assigned_at', 'last_response_at', 'closed_reason']);
        });

        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn('response_gap_minutes');
        });
    }
};
