<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            // Search Console Bulk Data Export lets each property choose its own
            // destination dataset at link time; unlike GA4 it can't be derived
            // from gsc_property, so it has to be recorded explicitly.
            $table->string('gsc_bigquery_dataset')->nullable()->after('gsc_property');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('gsc_bigquery_dataset');
        });
    }
};
