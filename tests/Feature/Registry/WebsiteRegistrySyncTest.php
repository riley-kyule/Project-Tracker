<?php

namespace Tests\Feature\Registry;

use App\Models\Country;
use App\Models\User;
use App\Models\Website;
use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Registry\WebsiteRegistrySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteRegistrySyncTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeRegistry(array $rows): void
    {
        $runner = new class($rows) implements BigQueryRunner
        {
            public function __construct(private array $rows) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                return $this->rows;
            }
        };

        $this->app->instance(BigQueryRunner::class, $runner);
    }

    public function test_sync_creates_new_websites_from_the_registry()
    {
        Country::factory()->create(['name' => 'Kenya']);
        $this->bindFakeRegistry([
            ['website_domain' => 'a.example.com', 'website_name' => 'Site A', 'country' => 'Kenya'],
        ]);

        $result = app(WebsiteRegistrySync::class)->sync();

        $this->assertSame(['created' => 1, 'updated' => 0, 'total' => 1], $result);
        $this->assertDatabaseHas('websites', ['domain' => 'a.example.com', 'name' => 'Site A']);
        $this->assertSame('Kenya', Website::where('domain', 'a.example.com')->first()->country->name);
    }

    public function test_sync_updates_existing_websites_matched_by_domain()
    {
        $website = Website::factory()->create(['domain' => 'a.example.com', 'name' => 'Old name']);
        $this->bindFakeRegistry([
            ['website_domain' => 'a.example.com', 'website_name' => 'New name', 'country' => null],
        ]);

        $result = app(WebsiteRegistrySync::class)->sync();

        $this->assertSame(['created' => 0, 'updated' => 1, 'total' => 1], $result);
        $this->assertSame('New name', $website->refresh()->name);
    }

    public function test_sync_records_an_unmatched_country_name_without_inventing_a_country_row()
    {
        $this->bindFakeRegistry([
            ['website_domain' => 'b.example.com', 'website_name' => 'Site B', 'country' => 'Wakanda'],
        ]);

        app(WebsiteRegistrySync::class)->sync();

        $website = Website::where('domain', 'b.example.com')->first();
        $this->assertNull($website->country_id);
        $this->assertSame('Wakanda', $website->metadata['registry_country']);
        $this->assertDatabaseMissing('countries', ['name' => 'Wakanda']);
    }

    public function test_registry_managers_can_trigger_a_sync_from_the_admin_page()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->bindFakeRegistry([
            ['website_domain' => 'c.example.com', 'website_name' => 'Site C', 'country' => null],
        ]);

        $this->actingAs($admin)->post('/admin/websites/sync')->assertRedirect();

        $this->assertDatabaseHas('websites', ['domain' => 'c.example.com']);
    }

    public function test_non_managers_cannot_trigger_a_sync()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $this->bindFakeRegistry([]);

        $this->actingAs($employee)->post('/admin/websites/sync')->assertForbidden();
    }
}
