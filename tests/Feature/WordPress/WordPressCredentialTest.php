<?php

namespace Tests\Feature\WordPress;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteWordPressCredential;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_view_or_manage_wordpress_credentials()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $website = Website::factory()->create();

        $this->actingAs($employee)->get('/settings/integrations/wordpress')->assertForbidden();
        $this->actingAs($employee)->post('/settings/integrations/wordpress', [
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'secret secret secret secret',
        ])->assertForbidden();
    }

    public function test_ceo_can_add_credentials_and_the_password_is_never_serialized_to_the_frontend()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create();

        $this->actingAs($ceo)->post('/settings/integrations/wordpress', [
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'abcd 1234 efgh 5678',
        ])->assertRedirect();

        $credential = WebsiteWordPressCredential::query()->where('website_id', $website->id)->firstOrFail();
        $this->assertSame('abcd 1234 efgh 5678', $credential->wp_app_password);

        $response = $this->actingAs($ceo)->get('/settings/integrations/wordpress')->assertOk();
        $props = $response->viewData('page')['props'];
        $row = collect($props['websites'])->firstWhere('id', $website->id);

        $this->assertTrue($row['credential']['wp_app_password_set']);
        $this->assertArrayNotHasKey('wp_app_password', $row['credential']);
    }

    public function test_a_blank_password_on_update_preserves_the_existing_one()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create();
        $credential = WebsiteWordPressCredential::query()->create([
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'original password',
        ]);

        $this->actingAs($ceo)->patch("/settings/integrations/wordpress/{$credential->id}", [
            'wp_username' => 'renamed-admin',
            'wp_app_password' => '',
        ])->assertRedirect();

        $credential->refresh();
        $this->assertSame('renamed-admin', $credential->wp_username);
        $this->assertSame('original password', $credential->wp_app_password);
    }

    public function test_deleting_a_credential_also_clears_its_cached_wordpress_users()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create();
        $credential = WebsiteWordPressCredential::query()->create([
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'secret',
        ]);
        WordPressUser::query()->create([
            'website_id' => $website->id,
            'wp_user_id' => 1,
            'username' => 'jdoe',
            'roles' => ['administrator'],
            'synced_at' => now(),
        ]);

        $this->actingAs($ceo)->delete("/settings/integrations/wordpress/{$credential->id}")->assertRedirect();

        $this->assertDatabaseMissing('website_wordpress_credentials', ['id' => $credential->id]);
        $this->assertDatabaseMissing('wordpress_users', ['website_id' => $website->id]);
    }
}
