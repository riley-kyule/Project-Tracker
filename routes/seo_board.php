<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Seo\SeoBoardController;
use App\Http\Controllers\Seo\SeoCardItemController;
use App\Http\Controllers\Seo\SeoDailyCardController;
use App\Http\Controllers\Seo\SeoNotificationSettingsController;
use App\Http\Controllers\Seo\SeoSettingsController;
use App\Http\Controllers\Seo\SeoTaskTemplateController;
use App\Http\Controllers\Seo\SeoWeeklyCardController;
use App\Models\Department;
use Illuminate\Support\Facades\Route;

/**
 * Three hubs, not eight pages: "My SEO Board" (employee — today, this week,
 * and history as tabs), "SEO Board (HOD)" (manager — today/week/exceptions/
 * history, items decided inline), and "SEO Board Settings" (templates +
 * notification recipients). Every action route below is unchanged from the
 * original per-page layout — only the GET/view routes were consolidated.
 */
Route::middleware(['auth', 'throttle:api-writes'])->prefix('seo-board')->name('seo-board.')->group(function () {
    Route::get('/', [SeoBoardController::class, 'mine'])->name('mine');

    // A stable, memorable link into the HOD view — which itself lives on "My
    // Department" (DashboardController::department), not a page of its own.
    // Without this, someone who isn't already a department manager (e.g. an
    // Administrator with no department of their own) has no discoverable way
    // in short of guessing a department_id query param.
    Route::get('hod', function () {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();

        return redirect()->route('dashboards.department', ['department_id' => $seo->id]);
    })->name('hod.redirect');

    // Item-level actions (employee status + HOD decisions), shared by daily and weekly cards
    Route::post('items/{item}/status', [SeoCardItemController::class, 'updateStatus'])->name('items.status');
    Route::post('items/{item}/decide', [SeoCardItemController::class, 'decide'])->name('items.decide');
    Route::patch('items/{item}/weight', [SeoCardItemController::class, 'updateWeight'])->name('items.weight');
    Route::patch('items/{item}/target', [SeoCardItemController::class, 'updateTarget'])->name('items.target');
    Route::post('items/{item}/evidence', [AttachmentController::class, 'storeForSeoItem'])->middleware('throttle:uploads')->name('items.evidence.store');

    // HOD actions — the view itself is a section on the existing "My Department"
    // dashboard (DashboardController::department), not a standalone page.
    Route::post('hod/daily/{card}/assign-items', [SeoDailyCardController::class, 'assignItems'])->name('daily.assign-items');
    Route::post('hod/daily/{card}/reopen', [SeoDailyCardController::class, 'reopen'])->name('daily.reopen');
    Route::post('hod/weekly/{employee}/{weekStart}/assign-items', [SeoWeeklyCardController::class, 'assignItems'])->name('weekly.assign-items');
    Route::post('hod/weekly/{card}/approve-plan', [SeoWeeklyCardController::class, 'approvePlan'])->name('weekly.approve-plan');
    Route::post('hod/weekly/{card}/reopen', [SeoWeeklyCardController::class, 'reopen'])->name('weekly.reopen');

    // Settings hub + its actions
    Route::get('settings', [SeoSettingsController::class, 'index'])->name('settings.index');
    Route::post('templates', [SeoTaskTemplateController::class, 'store'])->name('templates.store');
    Route::patch('templates/{template}', [SeoTaskTemplateController::class, 'update'])->name('templates.update');
    Route::delete('templates/{template}', [SeoTaskTemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('settings/notifications/{department}', [SeoNotificationSettingsController::class, 'store'])->name('settings.notifications.store');
    Route::delete('settings/notifications-recipients/{recipient}', [SeoNotificationSettingsController::class, 'destroy'])->name('settings.notifications.destroy');
});
