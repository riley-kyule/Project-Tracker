<?php
namespace App\Services\Analytics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class TrafficDashboardQuery
{
    public function isConfigured(): bool { return Schema::hasTable('analytics_ga4_daily_metrics'); }
    public function trafficSources(string|array|null $d,Carbon $f,Carbon $t,int $l=8): array { return $this->base('analytics_ga4_traffic_sources',$d,$f,$t)->select(['source','medium'])->selectRaw('SUM(users)::bigint AS users')->groupBy('source','medium')->orderByDesc('users')->limit($l)->get()->map(fn($r)=>(array)$r)->all(); }
    public function devices(string|array|null $d,Carbon $f,Carbon $t): array { return $this->base('analytics_ga4_devices',$d,$f,$t)->select('device_category')->selectRaw('SUM(users)::bigint AS users')->groupBy('device_category')->orderByDesc('users')->get()->map(fn($r)=>(array)$r)->all(); }
    public function landingPages(string|array|null $d,Carbon $f,Carbon $t,int $l=10): array { return $this->base('analytics_ga4_pages',$d,$f,$t)->select('page_location')->selectRaw('SUM(users)::bigint AS users, SUM(page_views)::bigint AS page_views')->groupBy('page_location')->orderByDesc('page_views')->limit($l)->get()->map(fn($r)=>(array)$r)->all(); }
    public function locations(string|array|null $d,Carbon $f,Carbon $t,int $l=10): array { return $this->base('analytics_ga4_geo',$d,$f,$t)->select('user_country')->selectRaw('SUM(users)::bigint AS users')->groupBy('user_country')->orderByDesc('users')->limit($l)->get()->map(fn($r)=>(array)$r)->all(); }
    public function dailyRows(string|array|null $d,Carbon $f,Carbon $t): array { return $this->base('analytics_ga4_daily_metrics',$d,$f,$t)->selectRaw('data_date AS event_date, SUM(users)::bigint AS users, SUM(sessions)::bigint AS sessions, SUM(engaged_sessions)::bigint AS engaged_sessions')->groupBy('data_date')->orderBy('data_date')->get()->map(fn($r)=>(array)$r)->all(); }
    public function keyEventsTotal(string|array|null $d,Carbon $f,Carbon $t): int { return (int)$this->base('analytics_ga4_key_events',$d,$f,$t)->sum('event_count'); }
    public function keyEventsBreakdown(string|array|null $d,Carbon $f,Carbon $t): array { return $this->base('analytics_ga4_key_events',$d,$f,$t)->selectRaw('display_name AS key_event, category AS key_event_category, SUM(event_count)::bigint AS key_event_count, SUM(users)::bigint AS users')->groupBy('display_name','category')->orderByDesc('key_event_count')->get()->map(fn($r)=>(array)$r)->all(); }
    /** Case-insensitive: UTM values aren't guaranteed consistent casing at the source. */
    private function bySourceMedium(string $table,string|array|null $d,Carbon $f,Carbon $t,string $source,string $medium): Builder
    {
        return $this->base($table,$d,$f,$t)->whereRaw('lower(source) = ?',[strtolower($source)])->whereRaw('lower(medium) = ?',[strtolower($medium)]);
    }
    public function usersBySourceMedium(string|array|null $d,Carbon $f,Carbon $t,string $source,string $medium): int { return (int)$this->bySourceMedium('analytics_ga4_traffic_sources',$d,$f,$t,$source,$medium)->sum('users'); }
    public function sessionsBySourceMedium(string|array|null $d,Carbon $f,Carbon $t,string $source,string $medium): int { return (int)$this->bySourceMedium('analytics_ga4_traffic_sources',$d,$f,$t,$source,$medium)->sum('sessions'); }
    public function keyEventsTotalBySourceMedium(string|array|null $d,Carbon $f,Carbon $t,string $source,string $medium): int { return (int)$this->bySourceMedium('analytics_ga4_key_events',$d,$f,$t,$source,$medium)->sum('event_count'); }
    public function locationsBySourceMedium(string|array|null $d,Carbon $f,Carbon $t,string $source,string $medium,int $l=10): array { return $this->bySourceMedium('analytics_ga4_geo',$d,$f,$t,$source,$medium)->select('user_country')->selectRaw('SUM(users)::bigint AS users')->groupBy('user_country')->orderByDesc('users')->limit($l)->get()->map(fn($r)=>(array)$r)->all(); }
    public function summaryByWebsite(array $domains,Carbon $f,Carbon $t): array
    {
        $rows=$this->base('analytics_ga4_daily_metrics',$domains,$f,$t)->select('websites.domain AS website_domain')->selectRaw('SUM(users)::bigint AS users, SUM(sessions)::bigint AS sessions, CASE WHEN SUM(sessions)=0 THEN NULL ELSE SUM(engaged_sessions)::decimal/SUM(sessions) END AS engagement_rate')->groupBy('websites.domain')->get();
        return $rows->mapWithKeys(fn($r)=>[$r->website_domain=>['users'=>(int)$r->users,'sessions'=>(int)$r->sessions,'engagement_rate'=>$r->engagement_rate===null?null:(float)$r->engagement_rate]])->all();
    }
    private function base(string $table,string|array|null $domain,Carbon $from,Carbon $to): Builder
    {
        $q=DB::table($table)->join('websites','websites.id','=',"{$table}.website_id")->whereBetween('data_date',[$from->toDateString(),$to->toDateString()]);
        if(is_array($domain))$q->whereIn('websites.domain',$domain); elseif($domain!==null)$q->where('websites.domain',$domain); return $q;
    }
}
