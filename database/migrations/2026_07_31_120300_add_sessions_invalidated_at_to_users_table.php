<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set whenever an admin changes this user's role or status — an
            // already-open session is force-logged-out the next time it's seen
            // if it was established before this timestamp. See
            // InvalidateStaleSessions, which compares this against the
            // "authenticated_at" marker GoogleAuthController writes at login.
            $table->timestamp('sessions_invalidated_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sessions_invalidated_at');
        });
    }
};
