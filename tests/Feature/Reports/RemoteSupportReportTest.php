<?php

namespace Tests\Feature\Reports;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoteSupportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_requires_permission()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $this->actingAs($employee)->get('/reports/remote-support')->assertForbidden();
    }

    public function test_report_aggregates_by_method_and_exports_csv()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $category = TicketCategory::query()->firstOrFail();

        Ticket::factory()->count(3)->create([
            'category_id' => $category->id,
            'status' => Ticket::STATUS_RESOLVED,
            'resolution_method' => 'remote',
            'resolved_at' => now()->subDay(),
            'time_spent_minutes' => 30,
        ]);
        Ticket::factory()->create([
            'category_id' => $category->id,
            'status' => Ticket::STATUS_RESOLVED,
            'resolution_method' => 'onsite',
            'resolved_at' => now()->subDay(),
            'time_spent_minutes' => 90,
        ]);
        // Outside the date range: ignored.
        Ticket::factory()->create([
            'category_id' => $category->id,
            'status' => Ticket::STATUS_RESOLVED,
            'resolution_method' => 'remote',
            'resolved_at' => now()->subDays(90),
        ]);

        $response = $this->actingAs($ceo)->get('/reports/remote-support')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(4, $props['totals']['resolved']);
        $this->assertSame(3, $props['byMethod']['remote']);
        $this->assertSame(1, $props['byMethod']['onsite']);

        $csv = $this->actingAs($ceo)->get('/reports/remote-support?format=csv')->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('resolution_method', $content);
        $this->assertSame(3, substr_count($content, ',remote,'));
        $this->assertSame(1, substr_count($content, ',onsite,'));
    }
}
