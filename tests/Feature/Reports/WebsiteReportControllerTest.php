<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Website;
use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WebsiteReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeRunner(): void
    {
        $runner = new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    return [['event_date' => '2026-07-01', 'users' => 40, 'sessions' => 55, 'engaged_sessions' => 20]];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 3]];
                }

                if (str_contains($sql, 'gsc_daily_site')) {
                    return [['data_date' => '2026-07-01', 'clicks' => 12, 'impressions' => 300, 'average_position' => 4.2]];
                }

                if (str_contains($sql, 'ahrefs_daily_site')) {
                    throw new RuntimeException('Not found: Table burnished-stone-421212:analytics_core.ahrefs_daily_site');
                }

                return [];
            }
        };

        $this->app->instance(BigQueryRunner::class, $runner);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/my-reports')->assertRedirect('/login');
    }

    public function test_a_user_with_no_assignments_sees_an_empty_state()
    {
        $user = User::factory()->create()->assignRole('Employee');

        $response = $this->actingAs($user)->get('/my-reports')->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertSame([], $props['assigned_websites']);
        $this->assertNull($props['report']);
    }

    public function test_a_user_can_only_report_on_their_own_assigned_websites()
    {
        $this->bindFakeRunner();
        $user = User::factory()->create()->assignRole('Marketing');
        $mine = Website::factory()->create(['domain' => 'mine.example.com']);
        $notMine = Website::factory()->create(['domain' => 'other.example.com']);
        $mine->assignments()->create(['user_id' => $user->id, 'team' => 'marketing']);

        $response = $this->actingAs($user)
            ->get("/my-reports?website_ids[]={$mine->id}&website_ids[]={$notMine->id}&date_from=2026-07-01&date_to=2026-07-01")
            ->assertOk();

        $report = $response->viewData('page')['props']['report'];
        $this->assertSame([$mine->id], array_column($report['marketing']['websites'], 'id'));
    }

    public function test_marketing_report_includes_ga4_and_gsc_kpis_with_ahrefs_reported_as_failed()
    {
        $this->bindFakeRunner();
        $user = User::factory()->create()->assignRole('Marketing');
        $website = Website::factory()->create(['domain' => 'mine.example.com']);
        $website->assignments()->create(['user_id' => $user->id, 'team' => 'marketing']);

        $response = $this->actingAs($user)
            ->get("/my-reports?website_ids[]={$website->id}&date_from=2026-07-01&date_to=2026-07-01")
            ->assertOk();

        $marketing = $response->viewData('page')['props']['report']['marketing'];
        $this->assertSame('ok', $marketing['ga4']['status']);
        $this->assertSame(40, $marketing['ga4']['kpis']['aggregate_property_users']['current']);
        $this->assertSame('ok', $marketing['gsc']['status']);
        $this->assertSame('failed', $marketing['ahrefs']['status']);
    }

    public function test_customer_service_report_is_reported_as_unavailable()
    {
        $user = User::factory()->create()->assignRole('Customer Service');
        $website = Website::factory()->create();
        $website->assignments()->create(['user_id' => $user->id, 'team' => 'customer_service']);

        $response = $this->actingAs($user)
            ->get("/my-reports?website_ids[]={$website->id}&date_from=2026-07-01&date_to=2026-07-01")
            ->assertOk();

        $customerService = $response->viewData('page')['props']['report']['customer_service'];
        $this->assertSame('failed', $customerService['status']);
        $this->assertNotNull($customerService['error']);
    }

    public function test_export_returns_a_pdf_download()
    {
        $this->bindFakeRunner();
        $user = User::factory()->create()->assignRole('Marketing');
        $website = Website::factory()->create(['domain' => 'mine.example.com']);
        $website->assignments()->create(['user_id' => $user->id, 'team' => 'marketing']);

        $response = $this->actingAs($user)
            ->get("/my-reports/export?website_ids[]={$website->id}&date_from=2026-07-01&date_to=2026-07-01");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
