<?php
namespace App\Services\Analytics;
use Google\Auth\Credentials\ServiceAccountCredentials;
use RuntimeException;
class GoogleApiTokenProvider
{
    private array $cached = [];
    public function token(array $scopes): string
    {
        sort($scopes); $key=implode('|',$scopes);
        if(isset($this->cached[$key]) && $this->cached[$key]['expires']>time()+60) return $this->cached[$key]['token'];
        $path=(string)config('analytics.api.credentials_path');
        if($path==='' ) throw new RuntimeException('ANALYTICS_GOOGLE_CREDENTIALS_PATH is not configured.');
        $path=str_starts_with($path,'/')?$path:base_path($path);
        if(!is_file($path)) throw new RuntimeException("Analytics credentials file not found: {$path}");
        $json=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        $credentials=new ServiceAccountCredentials($scopes,$json);
        $result=$credentials->fetchAuthToken();
        if(empty($result['access_token'])) throw new RuntimeException('Google did not return an analytics access token.');
        $this->cached[$key]=['token'=>$result['access_token'],'expires'=>time()+(int)($result['expires_in']??3600)];
        return $this->cached[$key]['token'];
    }
}
