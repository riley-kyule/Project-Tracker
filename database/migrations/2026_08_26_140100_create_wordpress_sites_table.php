<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A WordPress-connected site is intentionally its own concept, independent
 * of the BigQuery-driven `websites` registry (GA4/GSC/country/analytics
 * assignments) — this table exists purely to give WordPress credentials and
 * synced users something to belong to, with no analytics fields at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_sites');
    }
};
