<?php
namespace App\Services\Analytics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class PopcashReportQuery
{
    public function isConfigured(): bool { return Schema::hasTable('analytics_popcash_daily_spend'); }
    public function dailyRows(string|array|null $d,Carbon $f,Carbon $t): array
    {
        return $this->base($d,$f,$t)->selectRaw('data_date, SUM(money_spent) money_spent, SUM(impressions)::bigint impressions, CASE WHEN SUM(impressions)=0 THEN NULL ELSE (SUM(money_spent)/SUM(impressions))*1000 END cpm')->groupBy('data_date')->orderBy('data_date')->get()->map(fn($r)=>(array)$r)->all();
    }
    public function freshness(): array
    {
        return DB::table('analytics_popcash_daily_spend')->join('websites','websites.id','=','analytics_popcash_daily_spend.website_id')->select('websites.domain')->selectRaw('MAX(data_date) latest_date, CURRENT_DATE-MAX(data_date) days_behind')->groupBy('websites.domain')->get()->map(fn($r)=>(array)$r)->all();
    }
    private function base(string|array|null $domain,Carbon $from,Carbon $to): Builder
    {
        $q=DB::table('analytics_popcash_daily_spend')->join('websites','websites.id','=','analytics_popcash_daily_spend.website_id')->whereBetween('data_date',[$from->toDateString(),$to->toDateString()]);
        if(is_array($domain))$q->whereIn('websites.domain',$domain); elseif($domain!==null)$q->where('websites.domain',$domain); return $q;
    }
}
