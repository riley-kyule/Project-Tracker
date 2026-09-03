<?php

namespace App\Console\Commands;

use App\Services\Hr\AssetImporter;
use Illuminate\Console\Command;

/**
 * CLI wrapper around {@see AssetImporter}. Safe to re-run (matches on the ERP
 * asset ID) — see the service for the column mapping and custodian handling.
 */
class ImportAssets extends Command
{
    protected $signature = 'ewms:hr-import-assets {path : Path to the ERP asset CSV} {--dry-run : Parse and report without writing}';

    protected $description = 'Import company assets (and their custodians) from an ERP CSV export';

    public function handle(AssetImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $result = $importer->importFile($path, (bool) $this->option('dry-run'));

        if ($result->fatalError) {
            $this->error($result->fatalError);

            return self::FAILURE;
        }

        foreach ($result->skipped as $line) {
            $this->warn($line);
        }

        if ($result->hasUnmatched()) {
            $this->newLine();
            $this->warn('Custodians with no matching employee — asset left unassigned:');
            $this->warn('  '.$result->unmatchedList());
            $this->warn('Re-run this command once those staff exist to wire up the assignments.');
        }

        $this->newLine();
        $this->info($this->option('dry-run')
            ? "Dry run — {$result->rowsSeen} row(s) parsed, nothing written."
            : $result->summary());

        return self::SUCCESS;
    }
}
