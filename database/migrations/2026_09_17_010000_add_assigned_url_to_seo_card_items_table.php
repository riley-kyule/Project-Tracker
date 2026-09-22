<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §4.4/§5.2 — production items are often "work this specific URL/page,"
        // which the fixed template library can't capture since it's assigned
        // per employee per day, not per template.
        Schema::table('seo_card_items', function (Blueprint $table) {
            $table->string('assigned_url')->nullable()->after('quantity_unit');
        });
    }

    public function down(): void
    {
        Schema::table('seo_card_items', function (Blueprint $table) {
            $table->dropColumn('assigned_url');
        });
    }
};
