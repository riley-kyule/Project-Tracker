<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Analytics\Exceptions\BigQueryNotConfiguredException;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\ValueInterface;

/** Thin wrapper around the official Google Cloud SDK (google/cloud-bigquery). */
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
            $rows[] = $this->normalize($row);
        }

        return $rows;
    }

    /**
     * DATE/TIME/TIMESTAMP/NUMERIC/BIGNUMERIC/GEOGRAPHY columns come back
     * from this SDK as ValueInterface wrapper objects, not plain scalars —
     * json_encode silently turns an un-normalized one into `{}` (confirmed:
     * broke the GA4 trend chart's date axis even though the numeric columns
     * on the same row were plain PHP ints and worked fine). Every ValueInterface
     * implements __toString(), so this converts them to plain strings
     * everywhere, once, rather than requiring every caller to remember to.
     */
    private function normalize(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof ValueInterface) {
                $row[$key] = (string) $value;
            }
        }

        return $row;
    }

    private function client(): BigQueryClient
    {
        if (! $this->isConfigured()) {
            throw BigQueryNotConfiguredException::missingProject();
        }

        return $this->client ??= new BigQueryClient(array_filter([
            'projectId' => config('analytics.bigquery.project_id'),
            'location' => config('analytics.bigquery.location'),
            'keyFilePath' => $this->resolvedCredentialsPath(),
        ]));
    }

    /**
     * config('analytics.bigquery.credentials_path') is stored relative to
     * the project root (e.g. "storage/app/gcp/bigquery.json") so it reads
     * naturally in .env — but the BigQuery client resolves keyFilePath
     * against the process's current working directory, not the app root,
     * so a relative path only works by coincidence depending on how PHP
     * was invoked (confirmed: worked from a CLI script run from the repo
     * root, failed under the actual web/queue workers). Always resolve
     * through base_path() to remove that dependency entirely.
     */
    private function resolvedCredentialsPath(): ?string
    {
        $path = config('analytics.bigquery.credentials_path');

        if (blank($path)) {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
