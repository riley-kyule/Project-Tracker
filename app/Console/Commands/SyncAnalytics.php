<?php
namespace App\Console\Commands;
use App\Models\Website;
use App\Services\Analytics\AnalyticsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;
class SyncAnalytics extends Command
{
    protected $signature='ewms:sync-analytics {--date=} {--from=} {--to=} {--days=1} {--website=} {--source=all : all, ga4, gsc, or popcash} {--dry-run}';
    protected $description='Synchronize GA4 and GSC reports into local PostgreSQL without BigQuery';
    public function handle(AnalyticsSyncService $sync): int
    {
        if(!config('analytics.api.enabled')){ $this->error('ANALYTICS_API_ENABLED is false.'); return self::FAILURE; }
        $source=$this->option('source'); if(!in_array($source,['all','ga4','gsc','popcash'],true)){ $this->error('--source must be all, ga4, gsc, or popcash'); return self::FAILURE; }
        [$from,$to]=$this->range();
        $sites=Website::query()->where('status','active')->when($this->option('website'),function($query,$value){
            return ctype_digit((string)$value)
                ? $query->where('id',(int)$value)
                : $query->where('domain',$value);
        })->orderBy('id')->get();
        $this->info("Plan: {$sites->count()} websites, {$from->toDateString()}..{$to->toDateString()}, source={$source}");
        if($this->option('dry-run')) return self::SUCCESS;
        $failed=0;
        for($day=$from->copy();$day->lte($to);$day->addDay()) foreach($sites as $i=>$site){
            try { $out=$sync->sync($site,$day->toDateString(),$source); $this->line('['.($i+1)."/{$sites->count()}] {$day->toDateString()} {$site->domain}: ".json_encode($out)); }
            catch(Throwable $e){ $failed++; $this->error("{$day->toDateString()} {$site->domain}: {$e->getMessage()}"); }
            usleep(max(0,(int)config('analytics.api.request_delay_ms'))*1000);
        }
        $this->info("Completed with {$failed} failed website/day batches; reruns are safe."); return $failed===0?self::SUCCESS:self::FAILURE;
    }
    private function range(): array
    {
        if($this->option('date')){ $d=Carbon::parse($this->option('date'))->startOfDay(); return [$d,$d->copy()]; }
        if($this->option('from')||$this->option('to')){ if(!$this->option('from')||!$this->option('to'))throw new \InvalidArgumentException('--from and --to must be supplied together'); $f=Carbon::parse($this->option('from'))->startOfDay();$t=Carbon::parse($this->option('to'))->startOfDay();if($t->lt($f))throw new \InvalidArgumentException('--to must not precede --from');return[$f,$t]; }
        $to=now()->subDays(3)->startOfDay(); return [$to->copy()->subDays(max(1,(int)$this->option('days'))-1),$to];
    }
}
