<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import the company asset register from an ERP export (ERPNext "Asset" list
 * CSV). Matches on the ERP asset ID → `assets.asset_tag`, so it is safe to
 * re-run: existing rows are updated in place, never duplicated. Custodians are
 * linked by `employees.staff_number`; an unmatched custodian is reported and
 * the asset is left unassigned, so a later run (once the staff are imported)
 * wires up the assignment.
 *
 * Expected header (order-independent, extra columns ignored):
 *   ID, Item Code, Asset Name, Item Name, Location, Asset Category,
 *   Purchase Date, Available for Use Date, Total Asset Cost, Purchase Amount,
 *   Custodian, Status
 */
class ImportAssets extends Command
{
    protected $signature = 'ewms:hr-import-assets {path : Path to the ERP asset CSV} {--dry-run : Parse and report without writing}';

    protected $description = 'Import company assets (and their custodians) from an ERP CSV export';

    /** ERP category name (lower-cased) → EWMS category name. Unknowns are created verbatim (singularised). */
    private const CATEGORY_MAP = [
        'laptops' => 'Laptop',
        'laptop' => 'Laptop',
        'desktops' => 'Desktop',
        'desktop' => 'Desktop',
        'phones' => 'Phone',
        'phone' => 'Phone',
        'tablets' => 'Tablet',
        'tablet' => 'Tablet',
        'monitors' => 'Monitor',
        'monitor' => 'Monitor',
        'peripheral devices' => 'Peripheral',
        'peripherals' => 'Peripheral',
    ];

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);

        if ($header === false || ! in_array('ID', $header, true)) {
            fclose($handle);
            $this->error('CSV must have an "ID" column (the ERP asset ID).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;
        $assigned = 0;
        $skipped = [];
        $unmatched = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $values = array_slice(array_pad($values, count($header), null), 0, count($header));
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_combine($header, $values));

            $tag = $row['ID'] ?? null;
            if (blank($tag)) {
                $skipped[] = "Row {$rowNumber}: no ID";

                continue;
            }

            $name = ($row['Asset Name'] ?? '') ?: (($row['Item Name'] ?? '') ?: $tag);
            $custodian = $row['Custodian'] ?? '';

            $attributes = [
                'name' => $name,
                'serial_number' => ($row['Item Code'] ?? '') ?: null,
                'manufacturer' => $this->guessManufacturer($name),
                'purchase_date' => $this->date($row['Purchase Date'] ?? null),
                'purchase_cost' => $this->cost($row['Total Asset Cost'] ?? null, $row['Purchase Amount'] ?? null),
                'location' => ($row['Location'] ?? '') ?: null,
                'status' => $this->baseStatus($row['Status'] ?? ''),
            ];

            $this->line("  {$tag}  {$name}".($custodian ? "  → {$custodian}" : ''));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($tag, $row, $attributes, $custodian, &$created, &$updated, &$assigned, &$unmatched) {
                $categoryId = $this->resolveCategoryId($row['Asset Category'] ?? null);

                $asset = Asset::withTrashed()->firstWhere('asset_tag', $tag);
                if ($asset) {
                    $asset->fill([...$attributes, 'asset_category_id' => $categoryId ?? $asset->asset_category_id])->save();
                    $updated++;
                } else {
                    $asset = Asset::create([...$attributes, 'asset_tag' => $tag, 'asset_category_id' => $categoryId]);
                    $created++;
                }

                if (blank($custodian) || $asset->status === Asset::STATUS_RETIRED) {
                    return;
                }

                $employee = Employee::firstWhere('staff_number', $custodian);
                if (! $employee) {
                    $unmatched[$custodian] = ($unmatched[$custodian] ?? 0) + 1;

                    return;
                }

                $open = $asset->assignments()->whereNull('returned_at')->latest('assigned_at')->first();
                if ($open && $open->employee_id === $employee->id) {
                    return;
                }
                $open?->update(['returned_at' => now()]);

                $asset->assignments()->create([
                    'employee_id' => $employee->id,
                    'assigned_at' => $this->date($row['Available for Use Date'] ?? null)
                        ?? $this->date($row['Purchase Date'] ?? null)
                        ?? now()->toDateString(),
                    'notes' => 'Imported from ERP',
                ]);
                $asset->update(['status' => Asset::STATUS_ASSIGNED]);
                $assigned++;
            });
        }

        fclose($handle);

        foreach ($skipped as $line) {
            $this->warn($line);
        }

        if ($unmatched !== []) {
            $this->newLine();
            $this->warn('Custodians with no matching employee — asset left unassigned:');
            foreach ($unmatched as $staffNumber => $count) {
                $this->warn("  {$staffNumber}  ({$count} asset(s))");
            }
            $this->warn('Re-run this command once those staff exist to wire up the assignments.');
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run — nothing written.'
            : "Assets: {$created} created, {$updated} updated. Assignments created: {$assigned}.");

        return self::SUCCESS;
    }

    private function baseStatus(string $erpStatus): string
    {
        return match (Str::lower($erpStatus)) {
            'scrapped', 'sold' => Asset::STATUS_RETIRED,
            default => Asset::STATUS_IN_STOCK,
        };
    }

    private function resolveCategoryId(?string $erpName): ?int
    {
        if (blank($erpName)) {
            return null;
        }

        $name = self::CATEGORY_MAP[Str::lower($erpName)] ?? Str::of($erpName)->singular()->title()->toString();

        return AssetCategory::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        )->id;
    }

    private function guessManufacturer(string $name): ?string
    {
        return match (true) {
            Str::contains($name, ['MacBook', 'iMac', 'iPad'], ignoreCase: true) => 'Apple',
            Str::startsWith($name, 'Mac ') => 'Apple',
            Str::contains($name, 'TECNO', ignoreCase: true) => 'TECNO',
            Str::startsWith($name, 'HP ') => 'HP',
            Str::contains($name, 'Logitech', ignoreCase: true) => 'Logitech',
            Str::contains($name, 'Getac', ignoreCase: true) => 'Getac',
            default => null,
        };
    }

    private function date(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    private function cost(?string ...$values): ?float
    {
        foreach ($values as $value) {
            if (filled($value) && is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return null;
    }
}
