<?php

namespace App\Services\Hr;

/**
 * Outcome of an {@see AssetImporter} run — counts plus the two things a human
 * needs to act on: rows that couldn't be read, and custodians with no matching
 * employee (their asset imported unassigned).
 */
class AssetImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $assigned = 0;

    public int $rowsSeen = 0;

    public ?string $fatalError = null;

    /** @var list<string> */
    public array $skipped = [];

    /** @var array<string, int> staff number → asset count */
    public array $unmatchedCustodians = [];

    public function summary(): string
    {
        if ($this->fatalError) {
            return $this->fatalError;
        }

        return "Assets: {$this->created} created, {$this->updated} updated. Custodians linked: {$this->assigned}.";
    }

    public function unmatchedList(): string
    {
        $parts = [];
        foreach ($this->unmatchedCustodians as $staffNumber => $count) {
            $parts[] = "{$staffNumber} ({$count})";
        }

        return implode(', ', $parts);
    }

    public function hasUnmatched(): bool
    {
        return $this->unmatchedCustodians !== [];
    }
}
