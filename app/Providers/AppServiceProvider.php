<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Analytics\GoogleBigQueryRunner;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BigQueryRunner::class, GoogleBigQueryRunner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => app()->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()
            : Password::min(8));

        // DB-stored mail/push credentials (Settings > Integrations) override
        // .env on every boot — including queued jobs, which only re-read this
        // when their worker process restarts (hence queue:restart on save).
        CompanySetting::applyRuntimeConfig();
    }
}
