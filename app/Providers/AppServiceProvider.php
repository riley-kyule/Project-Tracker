<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Analytics\GoogleBigQueryRunner;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        // A first, low-friction slow-query signal via Laravel's own logger —
        // production-only so local/test runs (where N+1s are expected during
        // development) don't get noisy. Only sees queries issued through the
        // query builder/Eloquent, not raw connections — see docs/DEPLOYMENT.md
        // for the Postgres-side log_min_duration_statement complement.
        if ($this->app->isProduction()) {
            $threshold = (int) config('database.slow_query_threshold_ms');

            DB::listen(function (QueryExecuted $query) use ($threshold) {
                if ($query->time >= $threshold) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'time_ms' => $query->time,
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }
    }
}
