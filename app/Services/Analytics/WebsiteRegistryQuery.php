<?php
namespace App\Services\Analytics;
use App\Models\Website;
class WebsiteRegistryQuery
{
    public function websites(): array
    {
        return Website::query()->with('country:id,name')->where('status','active')->whereNotNull('domain')
            ->where(fn($q)=>$q->whereNotNull('ga4_property_id')->orWhereNotNull('gsc_property'))->orderBy('name')->get()
            ->map(fn(Website $w)=>['domain'=>$w->domain,'name'=>$w->name,'country'=>$w->country?->name])->all();
    }
}
