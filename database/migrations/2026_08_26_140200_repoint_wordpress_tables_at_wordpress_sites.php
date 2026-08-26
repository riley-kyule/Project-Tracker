<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detaches website_wordpress_credentials/wordpress_users from the
 * BigQuery-driven `websites` table and repoints them at the new, independent
 * `wordpress_sites` table instead (see the prior migration). Backfills one
 * wordpress_sites row per website that currently has WordPress credentials,
 * carrying over just its name/domain — the two fields WordPress ever needed
 * from that table in the first place.
 */
return new class extends Migration
{
    public function up(): void
    {
        $siteIdByWebsiteId = [];

        foreach (DB::table('website_wordpress_credentials')->pluck('website_id')->unique() as $websiteId) {
            $website = DB::table('websites')->find($websiteId);

            if (! $website || ! $website->domain) {
                continue; // WordPressUserClient always requires a domain to add credentials — shouldn't happen, but skip rather than insert a broken row.
            }

            $siteIdByWebsiteId[$websiteId] = DB::table('wordpress_sites')->insertGetId([
                'name' => $website->name,
                'domain' => $website->domain,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('website_wordpress_credentials', function (Blueprint $table) {
            $table->foreignId('wordpress_site_id')->nullable()->after('id')->constrained('wordpress_sites')->cascadeOnDelete();
        });

        foreach ($siteIdByWebsiteId as $websiteId => $siteId) {
            DB::table('website_wordpress_credentials')->where('website_id', $websiteId)->update(['wordpress_site_id' => $siteId]);
        }

        Schema::table('website_wordpress_credentials', function (Blueprint $table) {
            $table->dropUnique(['website_id']);
            $table->dropConstrainedForeignId('website_id');
            $table->unsignedBigInteger('wordpress_site_id')->nullable(false)->change();
            $table->unique('wordpress_site_id');
        });

        Schema::rename('website_wordpress_credentials', 'wordpress_credentials');

        Schema::table('wordpress_users', function (Blueprint $table) {
            $table->foreignId('wordpress_site_id')->nullable()->after('id')->constrained('wordpress_sites')->cascadeOnDelete();
        });

        foreach ($siteIdByWebsiteId as $websiteId => $siteId) {
            DB::table('wordpress_users')->where('website_id', $websiteId)->update(['wordpress_site_id' => $siteId]);
        }

        // A user row whose website was skipped above (no domain) has no
        // wordpress_site_id to point at — delete rather than leave an orphan;
        // the next sync recreates it correctly once that site has a domain.
        DB::table('wordpress_users')->whereNull('wordpress_site_id')->delete();

        Schema::table('wordpress_users', function (Blueprint $table) {
            $table->dropUnique(['website_id', 'wp_user_id']);
            $table->dropConstrainedForeignId('website_id');
            $table->unsignedBigInteger('wordpress_site_id')->nullable(false)->change();
            $table->unique(['wordpress_site_id', 'wp_user_id']);
        });
    }

    public function down(): void
    {
        Schema::rename('wordpress_credentials', 'website_wordpress_credentials');

        $websiteIdBySiteId = [];

        foreach (DB::table('website_wordpress_credentials')->pluck('wordpress_site_id')->unique() as $siteId) {
            $site = DB::table('wordpress_sites')->find($siteId);

            if (! $site) {
                continue;
            }

            $website = DB::table('websites')->where('domain', $site->domain)->first()
                ?? DB::table('websites')->find(DB::table('websites')->insertGetId([
                    'name' => $site->name,
                    'domain' => $site->domain,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

            $websiteIdBySiteId[$siteId] = $website->id;
        }

        Schema::table('website_wordpress_credentials', function (Blueprint $table) {
            $table->foreignId('website_id')->nullable()->after('id')->constrained('websites')->cascadeOnDelete();
        });

        foreach ($websiteIdBySiteId as $siteId => $websiteId) {
            DB::table('website_wordpress_credentials')->where('wordpress_site_id', $siteId)->update(['website_id' => $websiteId]);
        }

        Schema::table('website_wordpress_credentials', function (Blueprint $table) {
            $table->dropUnique(['wordpress_site_id']);
            $table->dropConstrainedForeignId('wordpress_site_id');
            $table->unsignedBigInteger('website_id')->nullable(false)->change();
            $table->unique('website_id');
        });

        Schema::table('wordpress_users', function (Blueprint $table) {
            $table->foreignId('website_id')->nullable()->after('id')->constrained('websites')->cascadeOnDelete();
        });

        foreach ($websiteIdBySiteId as $siteId => $websiteId) {
            DB::table('wordpress_users')->where('wordpress_site_id', $siteId)->update(['website_id' => $websiteId]);
        }

        Schema::table('wordpress_users', function (Blueprint $table) {
            $table->dropUnique(['wordpress_site_id', 'wp_user_id']);
            $table->dropConstrainedForeignId('wordpress_site_id');
            $table->unsignedBigInteger('website_id')->nullable(false)->change();
            $table->unique(['website_id', 'wp_user_id']);
        });
    }
};
