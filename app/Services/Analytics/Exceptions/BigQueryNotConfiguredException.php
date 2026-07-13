<?php

namespace App\Services\Analytics\Exceptions;

use RuntimeException;

class BigQueryNotConfiguredException extends RuntimeException
{
    public static function missingProject(): self
    {
        return new self('BigQuery is not configured: set BIGQUERY_PROJECT_ID (and credentials) in .env.');
    }
}
