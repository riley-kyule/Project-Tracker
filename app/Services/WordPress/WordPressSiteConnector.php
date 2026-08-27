<?php

namespace App\Services\WordPress;

use App\Models\WordPressSite;
use App\Services\AuditLogger;

/**
 * Shared "connect a site" logic — used by the web form (Admin\WordPressSiteController::store())
 * and the bulk CSV importer (wordpress:import-sites) alike, so both stay in sync.
 */
class WordPressSiteConnector
{
    public function connect(string $name, string $domain, string $wpUsername, string $wpAppPassword): WordPressSite
    {
        $site = WordPressSite::create(['name' => $name, 'domain' => $domain]);

        $credential = $site->credential()->create([
            'wp_username' => $wpUsername,
            'wp_app_password' => $wpAppPassword,
        ]);

        AuditLogger::log($credential, 'created', [], ['wordpress_site_id' => $site->id, 'wp_username' => $wpUsername]);

        return $site;
    }

    /** Used by the bulk importer for a domain that's already connected — updates credentials in place rather than failing. */
    public function reconnect(WordPressSite $site, string $wpUsername, string $wpAppPassword): void
    {
        $credential = $site->credential;
        $old = $credential->only(['wp_username']);

        $credential->update(['wp_username' => $wpUsername, 'wp_app_password' => $wpAppPassword]);

        AuditLogger::log($credential, 'updated', $old, $credential->only(['wp_username']));
    }
}
