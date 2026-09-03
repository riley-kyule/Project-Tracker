<?php

namespace Tests\Feature\Hr;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $body): string
    {
        $header = 'ID,Item Code,Asset Name,Location,Company,Purchase Date,Item Name,Asset Category,Asset Type,Asset Owner,'
            .'Available for Use Date,Total Asset Cost,Custodian,Status,Department,Purchase Amount';
        $path = tempnam(sys_get_temp_dir(), 'ewms-assets');
        file_put_contents($path, $header."\n".$body."\n");

        return $path;
    }

    public function test_it_imports_assets_maps_categories_and_links_custodians(): void
    {
        $holder = Employee::factory()->create(['staff_number' => 'HR-EMP-00038']);

        $path = $this->csv(implode("\n", [
            'ACC-ASS-2026-00038,FVFHP0ESQ05N,Macbook Pro,Bullpen,EOA,2026-08-31,Macbook Pro,Laptops,Existing Asset,Company,2026-09-01,60000.0,HR-EMP-00038,Submitted,IT,0.0',
            'ACC-ASS-2026-00030,2026LZ0AR578-2,Logitech Wireless Mouse,Beauty Duty,EOA,2026-03-28,Logitech Wireless Mouse,Peripheral Devices,Existing Asset,Company,2026-03-30,500.0,,Submitted,Marketing,0.0',
            'ACC-ASS-2026-00006,C02MPE2VG083,MacBook Air (Early 2014),Office,EOA,2023-08-16,MacBook Air,Laptops,Existing Asset,Company,2023-08-16,18000.0,,Scrapped,,0.0',
        ]));

        $this->artisan('ewms:hr-import-assets', ['path' => $path])->assertSuccessful();
        unlink($path);

        $mac = Asset::firstWhere('asset_tag', 'ACC-ASS-2026-00038');
        $this->assertSame('Macbook Pro', $mac->name);
        $this->assertSame('FVFHP0ESQ05N', $mac->serial_number);
        $this->assertSame('Apple', $mac->manufacturer);
        $this->assertSame('60000.00', $mac->purchase_cost);
        $this->assertSame('2026-08-31', $mac->purchase_date->toDateString());
        $this->assertSame('Laptop', $mac->category->name);
        $this->assertSame(Asset::STATUS_ASSIGNED, $mac->status);
        $this->assertSame($holder->id, $mac->currentAssignment->employee_id);
        $this->assertSame('2026-09-01', $mac->currentAssignment->assigned_at->toDateString());

        $mouse = Asset::firstWhere('asset_tag', 'ACC-ASS-2026-00030');
        $this->assertSame('Peripheral', $mouse->category->name);
        $this->assertSame(Asset::STATUS_IN_STOCK, $mouse->status);
        $this->assertCount(0, $mouse->assignments);

        $scrapped = Asset::firstWhere('asset_tag', 'ACC-ASS-2026-00006');
        $this->assertSame(Asset::STATUS_RETIRED, $scrapped->status);

        $this->assertSame(1, AssetCategory::where('name', 'Laptop')->count());
    }

    public function test_re_running_updates_in_place_and_backfills_a_late_custodian(): void
    {
        $row = 'ACC-ASS-2026-00037,4426317000024835,TECNO SPARK 50,Field,EOA,2026-05-26,TECNO SPARK 50,Phones,Existing Asset,Company,2026-05-26,20500.0,HR-EMP-00037,Submitted,CS,0.0';

        // First pass: the custodian doesn't exist yet.
        $path = $this->csv($row);
        $this->artisan('ewms:hr-import-assets', ['path' => $path])->assertSuccessful();

        $asset = Asset::firstWhere('asset_tag', 'ACC-ASS-2026-00037');
        $this->assertSame(Asset::STATUS_IN_STOCK, $asset->status);
        $this->assertCount(0, $asset->assignments);

        // Staff imported later, then re-run.
        $emp = Employee::factory()->create(['staff_number' => 'HR-EMP-00037']);
        $this->artisan('ewms:hr-import-assets', ['path' => $path])->assertSuccessful();
        unlink($path);

        $this->assertSame(1, Asset::where('asset_tag', 'ACC-ASS-2026-00037')->count());
        $asset->refresh();
        $this->assertSame(Asset::STATUS_ASSIGNED, $asset->status);
        $this->assertSame($emp->id, $asset->currentAssignment->employee_id);
    }

    public function test_the_page_upload_imports_and_flags_unmatched_custodians(): void
    {
        $hr = User::factory()->create()->assignRole('HR Manager');
        Employee::factory()->create(['staff_number' => 'HR-EMP-00001']);

        $body = implode("\n", [
            'ACC-ASS-2026-00004,PMKC0DQMM4,iPad Pro,CEO,EOA,2024-05-01,iPad Pro,Tablets,Existing Asset,Company,2024-05-01,67000.0,HR-EMP-00001,Submitted,,0.0',
            'ACC-ASS-2026-00003,FYHPJX3HHX,MacBook Pro (2024),CEO,EOA,2025-11-10,MacBook Pro,Laptops,Existing Asset,Company,2025-11-10,200000.0,HR-EMP-00099,Submitted,,0.0',
        ]);
        $path = $this->csv($body);
        $file = new UploadedFile($path, 'assets.csv', 'text/csv', null, true);

        $response = $this->actingAs($hr)->post('/hr/assets/import', ['file' => $file]);
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('error', fn ($m) => str_contains($m, 'HR-EMP-00099'));
        unlink($path);

        $this->assertSame(2, Asset::count());
        $this->assertSame(Asset::STATUS_ASSIGNED, Asset::firstWhere('asset_tag', 'ACC-ASS-2026-00004')->status);
        $this->assertSame(Asset::STATUS_IN_STOCK, Asset::firstWhere('asset_tag', 'ACC-ASS-2026-00003')->status);
    }

    public function test_the_page_upload_is_gated_to_asset_managers(): void
    {
        $viewer = User::factory()->create()->assignRole('Employee');
        $path = $this->csv('ACC-ASS-2026-00001,SER1,Thing,Loc,EOA,2026-01-01,Thing,Laptops,Existing Asset,Company,2026-01-01,100.0,,Submitted,,0.0');
        $file = new UploadedFile($path, 'assets.csv', 'text/csv', null, true);

        $this->actingAs($viewer)->post('/hr/assets/import', ['file' => $file])->assertForbidden();
        unlink($path);
        $this->assertSame(0, Asset::count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $path = $this->csv('ACC-ASS-2026-00001,SER1,Thing,Loc,EOA,2026-01-01,Thing,Laptops,Existing Asset,Company,2026-01-01,100.0,,Submitted,,0.0');

        $this->artisan('ewms:hr-import-assets', ['path' => $path, '--dry-run' => true])->assertSuccessful();
        unlink($path);

        $this->assertSame(0, Asset::count());
    }
}
