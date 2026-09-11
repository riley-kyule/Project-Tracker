<?php
namespace App\Services\Analytics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class GscReportQuery
{
    public function isConfigured(): bool { return Schema::hasTable('analytics_gsc_daily_site'); }
    public function dailyRows(string|array|null $d,Carbon $f,Carbon $t): array { return $this->base('analytics_gsc_daily_site',$d,$f,$t)->selectRaw('data_date, SUM(clicks)::bigint clicks, SUM(impressions)::bigint impressions, CASE WHEN SUM(impressions)=0 THEN NULL ELSE SUM(position_sum)/SUM(impressions) END average_position')->groupBy('data_date')->orderBy('data_date')->get()->map(fn($r)=>(array)$r)->all(); }
    public function queries(string|array|null $d,Carbon $f,Carbon $t,int $l=10): array { return $this->breakdown('analytics_gsc_daily_queries','query',$d,$f,$t,$l,true); }
    public function pages(string|array|null $d,Carbon $f,Carbon $t,int $l=10): array { return $this->breakdown('analytics_gsc_daily_pages','url',$d,$f,$t,$l,false); }
    public function countries(string|array|null $d,Carbon $f,Carbon $t,int $l=10): array { return $this->simple('analytics_gsc_daily_countries','country',$d,$f,$t,$l); }
    public function devices(string|array|null $d,Carbon $f,Carbon $t): array { return $this->simple('analytics_gsc_daily_devices','device',$d,$f,$t,null); }
    public function freshness(): array { return DB::table('analytics_gsc_daily_site')->join('websites','websites.id','=','analytics_gsc_daily_site.website_id')->where('search_type','WEB')->select('websites.domain')->selectRaw('MAX(data_date) latest_date, CURRENT_DATE-MAX(data_date) days_behind')->groupBy('websites.domain')->get()->map(fn($r)=>(array)$r)->all(); }
    public function summaryByWebsite(array $domains,Carbon $f,Carbon $t): array
    {
        $rows=$this->base('analytics_gsc_daily_site',$domains,$f,$t)->select('websites.domain')->selectRaw('SUM(clicks)::bigint clicks, SUM(impressions)::bigint impressions, CASE WHEN SUM(impressions)=0 THEN NULL ELSE SUM(position_sum)/SUM(impressions) END average_position')->groupBy('websites.domain')->get();
        return $rows->mapWithKeys(fn($r)=>[$r->domain=>['clicks'=>(int)$r->clicks,'impressions'=>(int)$r->impressions,'average_position'=>$r->average_position===null?null:(float)$r->average_position]])->all();
    }
    private function breakdown(string $table,string $column,string|array|null $d,Carbon $f,Carbon $t,int $limit,bool $position): array
    {
        $select="{$column}, SUM(clicks)::bigint clicks, SUM(impressions)::bigint impressions, CASE WHEN SUM(impressions)=0 THEN NULL ELSE SUM(clicks)::decimal/SUM(impressions) END ctr"; if($position)$select.=', CASE WHEN SUM(impressions)=0 THEN NULL ELSE SUM(position_sum)/SUM(impressions) END average_position';
        return $this->base($table,$d,$f,$t)->selectRaw($select)->groupBy($column)->orderByDesc('clicks')->limit($limit)->get()->map(fn($r)=>(array)$r)->all();
    }
    private function simple(string $table,string $column,string|array|null $d,Carbon $f,Carbon $t,?int $limit): array
    {
        $q=$this->base($table,$d,$f,$t)->select($column)->selectRaw('SUM(clicks)::bigint clicks, SUM(impressions)::bigint impressions')->groupBy($column)->orderByDesc('clicks'); if($limit!==null)$q->limit($limit); return $q->get()->map(fn($r)=>(array)$r)->all();
    }
    private function base(string $table,string|array|null $domain,Carbon $from,Carbon $to): Builder
    {
        $q=DB::table($table)->join('websites','websites.id','=',"{$table}.website_id")->where('search_type','WEB')->whereBetween('data_date',[$from->toDateString(),$to->toDateString()]); if(is_array($domain))$q->whereIn('websites.domain',$domain); elseif($domain!==null)$q->where('websites.domain',$domain); return $q;
    }
}
