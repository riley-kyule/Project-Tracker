<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unique('domain');
            $table->timestamp('synced_from_registry_at')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->dropColumn('synced_from_registry_at');
        });
    }
};
