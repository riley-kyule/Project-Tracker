<?php

namespace Tests\Feature\Analytics;

use App\Models\Website;
use App\Services\Analytics\TrafficDashboardQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrafficDashboardQuerySourceMediumTest extends TestCase
{
    use RefreshDatabase;

    private function seedTrafficSource(Website $website, string $date, string $source, string $medium, int $users, int $sessions): void
    {
        DB::table('analytics_ga4_traffic_sources')->insert([
            'website_id' => $website->id, 'data_date' => $date, 'source' => $source, 'medium' => $medium,
            'users' => $users, 'sessions' => $sessions, 'engaged_sessions' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedKeyEvent(Website $website, string $date, string $source, string $medium, int $count): void
    {
        DB::table('analytics_ga4_key_events')->insert([
            'website_id' => $website->id, 'data_date' => $date, 'event_name' => 'whatsapp_click', 'display_name' => 'Whatsapp Click',
            'category' => 'key_event', 'source' => $source, 'medium' => $medium, 'event_count' => $count, 'users' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_users_and_sessions_by_source_medium_only_sum_matching_rows()
    {
        $website = Website::factory()->create();
        $date = now()->subDay()->toDateString();

        $this->seedTrafficSource($website, $date, 'popcash', 'cpm', 100, 50);
        $this->seedTrafficSource($website, $date, 'google', 'organic', 900, 400);

        $query = new TrafficDashboardQuery;
        $from = Carbon::parse($date);
        $to = Carbon::parse($date);

        $this->assertSame(100, $query->usersBySourceMedium(null, $from, $to, 'popcash', 'cpm'));
        $this->assertSame(50, $query->sessionsBySourceMedium(null, $from, $to, 'popcash', 'cpm'));
    }

    public function test_source_medium_matching_is_case_insensitive()
    {
        $website = Website::factory()->create();
        $date = now()->subDay()->toDateString();

        // UTM values aren't guaranteed consistent casing at the source.
        $this->seedTrafficSource($website, $date, 'Popcash', 'CPM', 42, 20);

        $query = new TrafficDashboardQuery;
        $from = Carbon::parse($date);
        $to = Carbon::parse($date);

        $this->assertSame(42, $query->usersBySourceMedium(null, $from, $to, 'popcash', 'cpm'));
    }

    public function test_key_events_total_by_source_medium_excludes_other_channels()
    {
        $website = Website::factory()->create();
        $date = now()->subDay()->toDateString();

        $this->seedKeyEvent($website, $date, 'popcash', 'cpm', 5);
        $this->seedKeyEvent($website, $date, 'facebook', 'social', 30);

        $query = new TrafficDashboardQuery;
        $from = Carbon::parse($date);
        $to = Carbon::parse($date);

        $this->assertSame(5, $query->keyEventsTotalBySourceMedium(null, $from, $to, 'popcash', 'cpm'));
    }
}
