<?php

namespace App\Services\Analytics\Contracts;

interface BigQueryRunner
{
    public function isConfigured(): bool;

    /**
     * Run a query and return each result row as an associative array.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $sql, array $parameters = []): array;
}
