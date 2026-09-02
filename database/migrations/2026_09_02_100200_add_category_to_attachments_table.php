<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR employee documents reuse the polymorphic `attachments` table but need to
 * be filed by kind (contract, ID copy, certificate, KRA/NSSF letter, …).
 * Nullable and unused by existing task/ticket attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('category')->nullable()->after('scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
