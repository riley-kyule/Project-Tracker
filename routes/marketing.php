<?php

use App\Http\Controllers\MarketingStatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('marketing-statistics')->name('marketing-statistics.')->group(function () {
    Route::get('/', [MarketingStatisticsController::class, 'overview'])->name('overview');
    Route::get('ga4', [MarketingStatisticsController::class, 'ga4'])->name('ga4');
    Route::get('gsc', [MarketingStatisticsController::class, 'gsc'])->name('gsc');
    Route::get('ahrefs', [MarketingStatisticsController::class, 'ahrefs'])->name('ahrefs');
    Route::get('comparison', [MarketingStatisticsController::class, 'comparison'])->name('comparison');
    Route::get('freshness', [MarketingStatisticsController::class, 'freshness'])->name('freshness');
});
