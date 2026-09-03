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

    /** @var list<string> non-fatal notes (e.g. a serial mangled by Excel) */
    public array $warnings = [];

    /** @var list<string> one "tag  name → custodian" line per row, for CLI output */
    public array $preview = [];

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

    /** @return list<string> everything a human should look at, most useful first */
    public function notices(): array
    {
        return [...$this->warnings, ...$this->skipped];
    }
}
