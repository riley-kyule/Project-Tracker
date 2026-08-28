<?php

namespace Tests\Feature\Admin;

use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_view_report_deliveries()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($employee)->get('/admin/report-deliveries')->assertForbidden();
        $this->actingAs($admin)->get('/admin/report-deliveries')->assertOk();
    }

    public function test_deliveries_can_be_filtered_by_status()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $recipient = User::factory()->create()->assignRole('CEO');

        $snapshot = ReportSnapshot::query()->create([
            'report_date' => now()->toDateString(),
            'report_type' => ReportSnapshot::TYPE_CEO_DAILY,
            'department_id' => null,
            'generated_at' => now(),
            'payload' => [],
            'status' => ReportSnapshot::STATUS_GENERATED,
            'version' => 1,
        ]);

        ReportDelivery::query()->create([
            'report_snapshot_id' => $snapshot->id,
            'recipient_user_id' => $recipient->id,
            'status' => ReportDelivery::STATUS_SENT,
            'sent_at' => now(),
        ]);
        ReportDelivery::query()->create([
            'report_snapshot_id' => $snapshot->id,
            'recipient_user_id' => $recipient->id,
            'status' => ReportDelivery::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => 'SMTP timeout',
        ]);

        $props = $this->actingAs($admin)->get('/admin/report-deliveries')->assertOk()->viewData('page')['props'];
        $this->assertSame(2, $props['deliveries']['total']);

        $filtered = $this->actingAs($admin)
            ->get('/admin/report-deliveries?status=failed')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(1, $filtered['deliveries']['total']);
        $this->assertSame('failed', $filtered['deliveries']['data'][0]['status']);
        $this->assertSame('SMTP timeout', $filtered['deliveries']['data'][0]['failure_reason']);
    }

    /**
     * Paginates 50/page; the frontend Pagination component needs the full
     * paginator shape or page 2+ becomes unreachable with no error.
     */
    public function test_report_deliveries_expose_full_pagination_metadata()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $recipient = User::factory()->create()->assignRole('CEO');

        $snapshot = ReportSnapshot::query()->create([
            'report_date' => now()->toDateString(),
            'report_type' => ReportSnapshot::TYPE_CEO_DAILY,
            'department_id' => null,
            'generated_at' => now(),
            'payload' => [],
            'status' => ReportSnapshot::STATUS_GENERATED,
            'version' => 1,
        ]);

        collect(range(1, 60))->each(fn () => ReportDelivery::query()->create([
            'report_snapshot_id' => $snapshot->id,
            'recipient_user_id' => $recipient->id,
            'status' => ReportDelivery::STATUS_SENT,
            'sent_at' => now(),
        ]));

        $props = $this->actingAs($admin)->get('/admin/report-deliveries')->assertOk()->viewData('page')['props'];

        $this->assertSame(50, count($props['deliveries']['data']));
        $this->assertSame(60, $props['deliveries']['total']);
        $this->assertSame(2, $props['deliveries']['last_page']);
        $this->assertNotEmpty($props['deliveries']['links']);
    }
}
