<?php
namespace App\Services\Analytics;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class SearchConsoleApiClient
{
    public function __construct(private GoogleApiTokenProvider $tokens) {}
    public function report(string $property,array $dimensions,string $date,string $searchType='web'): array
    {
        $rows=[]; $start=0; $limit=25000;
        do {
            $body=['startDate'=>$date,'endDate'=>$date,'dimensions'=>$dimensions,'type'=>strtolower($searchType),'dataState'=>'final','rowLimit'=>$limit,'startRow'=>$start];
            $response=Http::withToken($this->tokens->token(['https://www.googleapis.com/auth/webmasters.readonly']))->acceptJson()->timeout(config('analytics.api.request_timeout'))->retry(4,fn($attempt)=>$attempt*1000)->post('https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($property).'/searchAnalytics/query',$body);
            if(!$response->successful()) throw new RuntimeException("Search Console API {$response->status()}: ".$response->body());
            $page=$response->json('rows',[]); $rows=array_merge($rows,$page); $start+=count($page);
        } while(count($page)===$limit);
        return $rows;
    }
}
