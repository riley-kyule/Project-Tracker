<?php

namespace App\Console\Commands;

use App\Jobs\SyncWordPressUsersForSite;
use App\Models\WordPressSite;
use App\Services\WordPress\WordPressSiteConnector;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bulk-imports the results of ops/wordpress-onboarding/onboard.js — one CSV
 * row per site, already carrying a generated Application Password. Existing
 * sites (matched by domain) get their credential updated in place rather
 * than failing, so this is safe to re-run against an updated export.
 */
class ImportWordPressSites extends Command
{
    protected $signature = 'wordpress:import-sites {path : Path to the results CSV}';

    protected $description = 'Bulk-connect WordPress sites from a CSV of name,domain,wp_username,wp_app_password[,status,error]';

    public function handle(WordPressSiteConnector $connector): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if (! $header || ! in_array('domain', $header, true)) {
            $this->error('CSV must have a header row including at least: name,domain,wp_username,wp_app_password');
            fclose($handle);

            return self::FAILURE;
        }

        $connected = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);

            // Only status=ok rows carry a real Application Password — everything
            // else (two_factor_required, login_failed, ...) needs manual handling
            // and is reported, not silently dropped.
            if (array_key_exists('status', $record) && $record['status'] !== 'ok') {
                $skipped++;
                $this->line("  skip  {$record['domain']} ({$record['status']}: ".($record['error'] ?? '').')');

                continue;
            }

            $domain = trim($record['domain'] ?? '');
            $name = trim($record['name'] ?? '') ?: $domain;
            $wpUsername = trim($record['wp_username'] ?? '');
            $wpAppPassword = trim($record['wp_app_password'] ?? '');

            if ($domain === '' || $wpUsername === '' || $wpAppPassword === '') {
                $failed++;
                $this->warn("  FAIL  {$domain}: missing domain/wp_username/wp_app_password");

                continue;
            }

            try {
                $existing = WordPressSite::query()->where('domain', $domain)->first();

                if ($existing) {
                    $connector->reconnect($existing, $wpUsername, $wpAppPassword);
                    SyncWordPressUsersForSite::dispatch($existing->credential->id);
                    $updated++;
                    $this->line("  ok    {$domain} (updated existing)");
                } else {
                    $site = $connector->connect($name, $domain, $wpUsername, $wpAppPassword);
                    SyncWordPressUsersForSite::dispatch($site->credential->id);
                    $connected++;
                    $this->line("  ok    {$domain} (connected)");
                }
            } catch (Throwable $e) {
                $failed++;
                $this->warn("  FAIL  {$domain}: {$e->getMessage()}");
            }
        }

        fclose($handle);

        $this->newLine();
        $this->info("Connected: {$connected}  Updated: {$updated}  Skipped: {$skipped}  Failed: {$failed}");

        if ($skipped + $failed > 0) {
            $this->comment('Skipped/failed rows need manual attention — see the notes above and the source CSV\'s status/error columns.');
        }

        if ($connected + $updated > 0) {
            $this->comment('A sync job was queued for every connected/updated site — check /admin/wordpress-users shortly for status.');
        }

        return self::SUCCESS;
    }
}
