<?php
namespace App\Services\Analytics;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class Ga4DataApiClient
{
    public function __construct(private GoogleApiTokenProvider $tokens) {}
    public function report(string $propertyId,array $dimensions,array $metrics,string $date): array
    {
        $rows=[]; $offset=0; $limit=100000;
        do {
            $body=['dateRanges'=>[['startDate'=>$date,'endDate'=>$date]],'dimensions'=>array_map(fn($n)=>['name'=>$n],$dimensions),'metrics'=>array_map(fn($n)=>['name'=>$n],$metrics),'limit'=>(string)$limit,'offset'=>(string)$offset,'keepEmptyRows'=>false];
            $response=Http::withToken($this->tokens->token(['https://www.googleapis.com/auth/analytics.readonly']))->acceptJson()->timeout(config('analytics.api.request_timeout'))->retry(4,fn($attempt)=>$attempt*1000)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport",$body);
            if(!$response->successful()) throw new RuntimeException("GA4 Data API {$response->status()}: ".$response->body());
            $payload=$response->json();
            foreach($payload['rows']??[] as $row){ $out=[]; foreach($dimensions as $i=>$name)$out[$name]=$row['dimensionValues'][$i]['value']??null; foreach($metrics as $i=>$name)$out[$name]=$row['metricValues'][$i]['value']??null; $rows[]=$out; }
            $offset+=count($payload['rows']??[]); $total=(int)($payload['rowCount']??0);
        } while($offset<$total);
        return $rows;
    }
}
