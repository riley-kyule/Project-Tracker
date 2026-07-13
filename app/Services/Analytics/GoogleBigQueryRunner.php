<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Analytics\Exceptions\BigQueryNotConfiguredException;
use Google\Cloud\BigQuery\BigQueryClient;

/**
 * Thin wrapper around the official Google Cloud SDK. Requires
 * `composer require google/cloud-bigquery`, which is not installed yet —
 * this class is wired up and bound in AppServiceProvider so the only
 * remaining step is adding the package and BIGQUERY_* credentials.
 */
class GoogleBigQueryRunner implements BigQueryRunner
{
    private ?BigQueryClient $client = null;

    public function isConfigured(): bool
    {
        return filled(config('analytics.bigquery.project_id'));
    }

    public function rows(string $sql, array $parameters = []): array
    {
        $results = $this->client()->runQuery(
            $this->client()->query($sql)->parameters($parameters),
        );

        $rows = [];

        foreach ($results as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function client(): BigQueryClient
    {
        if (! $this->isConfigured()) {
            throw BigQueryNotConfiguredException::missingProject();
        }

        return $this->client ??= new BigQueryClient(array_filter([
            'projectId' => config('analytics.bigquery.project_id'),
            'location' => config('analytics.bigquery.location'),
            'keyFilePath' => config('analytics.bigquery.credentials_path'),
        ]));
    }
}
