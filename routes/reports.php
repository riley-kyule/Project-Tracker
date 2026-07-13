<?php

use App\Http\Controllers\WebsiteReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('my-reports')->name('my-reports.')->group(function () {
    Route::get('/', [WebsiteReportController::class, 'index'])->name('index');
    Route::get('export', [WebsiteReportController::class, 'export'])->name('export');
});
