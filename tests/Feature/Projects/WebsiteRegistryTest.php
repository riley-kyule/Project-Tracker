<?php

namespace Tests\Feature\Projects;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_registry_managers_can_add_websites()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($employee)->get('/admin/websites')->assertOk();

        $this->actingAs($employee)
            ->post('/admin/websites', ['name' => 'Rogue site', 'status' => 'active'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post('/admin/websites', ['name' => 'exotic.example.com', 'status' => 'active', 'ga4_property_id' => 'G-123'])
            ->assertRedirect();

        $this->assertDatabaseHas('websites', ['name' => 'exotic.example.com', 'ga4_property_id' => 'G-123']);
    }

    public function test_website_update_persists_analytics_identifiers()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $website = Website::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/websites/{$website->id}", [
                'name' => $website->name,
                'status' => 'active',
                'gsc_property' => 'sc-domain:example.com',
                'gsc_bigquery_dataset' => 'gsc_export',
            ])
            ->assertRedirect();

        $website->refresh();
        $this->assertSame('sc-domain:example.com', $website->gsc_property);
        $this->assertSame('gsc_export', $website->gsc_bigquery_dataset);
    }
}
