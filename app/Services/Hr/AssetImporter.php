<?php

namespace App\Services\Hr;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the company asset register from an ERP export (ERPNext "Asset" list
 * CSV). Rows are keyed by the ERP asset ID when present, otherwise by serial
 * number, and matched against either `assets.asset_tag` or
 * `assets.serial_number` — so it is safe to re-run and to switch between
 * export layouts: existing rows are updated in place, never duplicated.
 *
 * Custodians (when the export has that column) are linked by
 * `employees.staff_number`; an unmatched custodian is reported and the asset
 * left unassigned, so a later run (once the staff are imported) wires it up.
 *
 * Header columns used (order-independent, others ignored): ID, Item Code /
 * Serial Number, Asset Name, Item Name, Location, Asset Category, Purchase
 * Date, Available for Use Date, Total Asset Cost, Purchase Amount, Custodian,
 * Status. Dates may be ISO (2026-08-31) or DD/MM/YYYY (31/08/2026).
 */
class AssetImporter
{
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

    public function importFile(string $path, bool $dryRun = false): AssetImportResult
    {
        $handle = fopen($path, 'rb');

        try {
            return $this->import($handle, $dryRun);
        } finally {
            fclose($handle);
        }
    }

    /** @param  resource  $handle */
    public function import($handle, bool $dryRun = false): AssetImportResult
    {
        $result = new AssetImportResult;

        $header = fgetcsv($handle);
        $header = $header === false ? [] : array_map(fn ($h) => is_string($h) ? trim($h) : $h, $header);

        if (array_intersect(['ID', 'Serial Number', 'Item Code'], $header) === []) {
            $result->fatalError = 'The file needs a header row with an "ID", "Serial Number", or "Item Code" column.';

            return $result;
        }

        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $values = array_slice(array_pad($values, count($header), null), 0, count($header));
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_combine($header, $values));

            $this->importRow($row, $rowNumber, $dryRun, $result);
        }

        return $result;
    }

    /** @param  array<string, string|null>  $row */
    private function importRow(array $row, int $rowNumber, bool $dryRun, AssetImportResult $result): void
    {
        $serial = ($row['Item Code'] ?? $row['Serial Number'] ?? '') ?: null;
        $tag = ($row['ID'] ?? '') ?: $serial;

        if (blank($tag)) {
            $result->skipped[] = "Row {$rowNumber}: no ID or serial number";

            return;
        }

        $result->rowsSeen++;

        if ($serial !== null && preg_match('/^\d(?:\.\d+)?E\+?\d+$/i', $serial)) {
            $result->warnings[] = "Row {$rowNumber}: serial \"{$serial}\" looks mangled by Excel — re-export it as text, then re-upload.";
        }

        $name = ($row['Asset Name'] ?? '') ?: (($row['Item Name'] ?? '') ?: $tag);
        $custodian = $row['Custodian'] ?? '';

        $result->preview[] = "{$tag}  {$name}".($custodian ? "  → {$custodian}" : '');

        $attributes = [
            'name' => $name,
            'serial_number' => $serial,
            'manufacturer' => $this->guessManufacturer($name),
            'purchase_date' => $this->date($row['Purchase Date'] ?? null),
            'purchase_cost' => $this->cost($row['Total Asset Cost'] ?? null, $row['Purchase Amount'] ?? null),
            'location' => ($row['Location'] ?? '') ?: null,
            'status' => $this->baseStatus($row['Status'] ?? ''),
        ];

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($tag, $serial, $row, $attributes, $custodian, $result) {
            $categoryId = $this->resolveCategoryId($row['Asset Category'] ?? null);

            $asset = Asset::withTrashed()
                ->where(function ($q) use ($tag, $serial) {
                    $q->where('asset_tag', $tag);
                    if ($serial !== null) {
                        $q->orWhere('serial_number', $serial);
                    }
                })
                ->first();

            if ($asset) {
                $asset->fill([...$attributes, 'asset_category_id' => $categoryId ?? $asset->asset_category_id])->save();
                $result->updated++;
            } else {
                $asset = Asset::create([...$attributes, 'asset_tag' => $tag, 'asset_category_id' => $categoryId]);
                $result->created++;
            }

            if (blank($custodian) || $asset->status === Asset::STATUS_RETIRED) {
                return;
            }

            $employee = Employee::firstWhere('staff_number', $custodian);
            if (! $employee) {
                $result->unmatchedCustodians[$custodian] = ($result->unmatchedCustodians[$custodian] ?? 0) + 1;

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
            $result->assigned++;
        });
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
        $value = trim($value);

        // DD/MM/YYYY (the app + ERP convention) — parse explicitly; strtotime
        // would read a slashed date as US month/day.
        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y'] as $format) {
            if (Carbon::hasFormat($value, $format)) {
                return Carbon::createFromFormat('!'.$format, $value)->toDateString();
            }
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
