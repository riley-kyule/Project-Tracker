<?php
namespace App\Services\Analytics;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
class AnalyticsSyncService
{
    public function __construct(private Ga4DataApiClient $ga4,private SearchConsoleApiClient $gsc,private PopcashApiClient $popcash) {}

    public function sync(Website $website,string $date,string $source='all'): array
    {
        $result=[];
        if(in_array($source,['all','ga4'],true) && filled($website->ga4_property_id)) $result['ga4']=$this->tracked($website,'ga4',$date,fn()=>$this->syncGa4($website,$date));
        if(in_array($source,['all','gsc'],true) && filled($website->gsc_property)) $result['gsc']=$this->tracked($website,'gsc',$date,fn()=>$this->syncGsc($website,$date));
        if(in_array($source,['all','popcash'],true) && filled($website->popcash_campaign_id)) $result['popcash']=$this->tracked($website,'popcash',$date,fn()=>$this->syncPopcash($website,$date));
        return $result;
    }

    private function syncGa4(Website $w,string $date): int
    {
        $total=0; $id=(string)$w->ga4_property_id;
        $metricRows=$this->ga4->report($id,['date'],['totalUsers','sessions','newUsers','engagedSessions','userEngagementDuration'],$date);
        $rows=array_map(fn($r)=>['users'=>(int)$r['totalUsers'],'sessions'=>(int)$r['sessions'],'new_users'=>(int)$r['newUsers'],'engaged_sessions'=>(int)$r['engagedSessions'],'engagement_seconds'=>(float)$r['userEngagementDuration']],$metricRows);
        $total+=$this->replace('analytics_ga4_daily_metrics',$w,$date,$rows);

        $specs=[
            ['analytics_ga4_traffic_sources',['firstUserSource','firstUserMedium'],['totalUsers'],fn($r)=>['source'=>$r['firstUserSource']?:'(direct)','medium'=>$r['firstUserMedium']?:'(none)','users'=>(int)$r['totalUsers']]],
            ['analytics_ga4_devices',['deviceCategory'],['totalUsers'],fn($r)=>['device_category'=>$r['deviceCategory']?:'(not set)','users'=>(int)$r['totalUsers']]],
            ['analytics_ga4_pages',['pageLocation'],['totalUsers','screenPageViews'],fn($r)=>['page_location'=>$r['pageLocation'],'users'=>(int)$r['totalUsers'],'page_views'=>(int)$r['screenPageViews']]],
            ['analytics_ga4_geo',['country','city'],['totalUsers'],fn($r)=>['user_country'=>$r['country']?:null,'city'=>$r['city']?:null,'users'=>(int)$r['totalUsers']]],
        ];
        foreach($specs as [$table,$dims,$metrics,$map]) $total+=$this->replace($table,$w,$date,array_map($map,$this->ga4->report($id,$dims,$metrics,$date)));

        $wanted=array_flip(array_map('strtolower',config('analytics.api.key_events',[])));
        $keyRows=[];
        foreach($this->ga4->report($id,['eventName'],['keyEvents','totalUsers'],$date) as $r){
            if((float)$r['keyEvents']<=0 || ($wanted!==[] && !isset($wanted[strtolower($r['eventName'])]))) continue;
            $keyRows[]=['event_name'=>$r['eventName'],'display_name'=>Str::headline($r['eventName']),'category'=>'key_event','event_count'=>(int)round((float)$r['keyEvents']),'users'=>(int)$r['totalUsers']];
        }
        return $total+$this->replace('analytics_ga4_key_events',$w,$date,$keyRows);
    }

    private function syncGsc(Website $w,string $date): int
    {
        $total=0; $specs=[
            ['analytics_gsc_daily_site',['date'],null],['analytics_gsc_daily_queries',['date','query'],'query'],
            ['analytics_gsc_daily_pages',['date','page'],'url'],['analytics_gsc_daily_countries',['date','country'],'country'],
            ['analytics_gsc_daily_devices',['date','device'],'device'],
        ];
        foreach($specs as [$table,$dimensions,$keyName]){
            $mapped=[];
            foreach($this->gsc->report($w->gsc_property,$dimensions,$date,'web') as $row){
                $impressions=(int)($row['impressions']??0); $item=['search_type'=>'WEB','clicks'=>(int)($row['clicks']??0),'impressions'=>$impressions,'position_sum'=>(float)($row['position']??0)*$impressions];
                if($keyName!==null)$item[$keyName]=$row['keys'][1]??''; $mapped[]=$item;
            }
            $total+=$this->replace($table,$w,$date,$mapped);
        }
        return $total;
    }

    private function syncPopcash(Website $w,string $date): int
    {
        $day=Carbon::parse($date);
        $rows=array_values(array_filter($this->popcash->dailyStats((string)$w->popcash_campaign_id,$day,$day),fn($r)=>$r['data_date']===$date));
        $mapped=array_map(fn($r)=>['money_spent'=>$r['money_spent'],'cpm'=>$r['cpm'],'impressions'=>$r['impressions']],$rows);
        return $this->replace('analytics_popcash_daily_spend',$w,$date,$mapped);
    }

    private function replace(string $table,Website $website,string $date,array $rows): int
    {
        $now=now(); foreach($rows as &$row)$row=['website_id'=>$website->id,'data_date'=>$date,...$row,'created_at'=>$now,'updated_at'=>$now]; unset($row);
        DB::transaction(function()use($table,$website,$date,$rows){ DB::table($table)->where('website_id',$website->id)->whereDate('data_date',$date)->delete(); foreach(array_chunk($rows,1000) as $chunk)DB::table($table)->insert($chunk); });
        return count($rows);
    }

    private function tracked(Website $w,string $source,string $date,callable $callback): array
    {
        $started=now(); DB::table('analytics_sync_runs')->updateOrInsert(['website_id'=>$w->id,'source'=>$source,'data_date'=>$date],['status'=>'running','rows_written'=>0,'error'=>null,'started_at'=>$started,'finished_at'=>null,'created_at'=>$started,'updated_at'=>$started]);
        try { $count=$callback(); DB::table('analytics_sync_runs')->where(['website_id'=>$w->id,'source'=>$source,'data_date'=>$date])->update(['status'=>'success','rows_written'=>$count,'finished_at'=>now(),'updated_at'=>now()]); return ['status'=>'success','rows'=>$count]; }
        catch(Throwable $e){ DB::table('analytics_sync_runs')->where(['website_id'=>$w->id,'source'=>$source,'data_date'=>$date])->update(['status'=>'failed','error'=>Str::limit($e->getMessage(),4000),'finished_at'=>now(),'updated_at'=>now()]); throw $e; }
    }
}
