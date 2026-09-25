<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Cs\CsBoardController;
use App\Http\Controllers\Cs\CsCardItemController;
use App\Http\Controllers\Cs\CsComplaintController;
use App\Http\Controllers\Cs\CsContactQualityReviewController;
use App\Http\Controllers\Cs\CsDailyCardController;
use App\Http\Controllers\Cs\CsNotificationSettingsController;
use App\Http\Controllers\Cs\CsPlatformAssignmentController;
use App\Http\Controllers\Cs\CsSalesRecordController;
use App\Http\Controllers\Cs\CsServiceInteractionController;
use App\Http\Controllers\Cs\CsSettingsController;
use App\Http\Controllers\Cs\CsTaskTemplateController;
use App\Http\Controllers\Cs\CsWeeklyCardController;
use App\Http\Controllers\Cs\CsWeeklyTargetController;
use App\Models\Department;
use Illuminate\Support\Facades\Route;

/**
 * Three hubs, matching the SEO Board's route shape: "My Customer Service
 * Board" (employee), "Customer Service Board (HOD)" (manager, embedded on
 * "My Department"), and "Customer Service Board Settings" (templates +
 * notification recipients). See routes/seo_board.php for the identical
 * pattern this mirrors.
 */
Route::middleware(['auth', 'throttle:api-writes'])->prefix('cs-board')->name('cs-board.')->group(function () {
    Route::get('/', [CsBoardController::class, 'mine'])->name('mine');

    Route::get('hod', function () {
        $cs = Department::query()->where('slug', 'customer-service')->firstOrFail();

        return redirect()->route('dashboards.department', ['department_id' => $cs->id]);
    })->name('hod.redirect');

    // Item-level actions (employee status + HOD decisions), shared by daily and weekly cards
    Route::post('items/{item}/status', [CsCardItemController::class, 'updateStatus'])->name('items.status');
    Route::post('items/{item}/decide', [CsCardItemController::class, 'decide'])->name('items.decide');
    Route::patch('items/{item}/weight', [CsCardItemController::class, 'updateWeight'])->name('items.weight');
    Route::patch('items/{item}/target', [CsCardItemController::class, 'updateTarget'])->name('items.target');
    Route::post('items/{item}/evidence', [AttachmentController::class, 'storeForCsItem'])->middleware('throttle:uploads')->name('items.evidence.store');

    // Daily/weekly card actions
    Route::post('hod/daily/{card}/reopen', [CsDailyCardController::class, 'reopen'])->name('daily.reopen');
    Route::post('hod/weekly/{employee}/{weekStart}/assign-items', [CsWeeklyCardController::class, 'assignItems'])->name('weekly.assign-items');
    Route::post('hod/weekly/{card}/approve-plan', [CsWeeklyCardController::class, 'approvePlan'])->name('weekly.approve-plan');
    Route::post('hod/weekly/{card}/reopen', [CsWeeklyCardController::class, 'reopen'])->name('weekly.reopen');

    // Commercial: sales records (employee logs, HOD clears/flags) and weekly targets (HOD only)
    Route::post('employees/{employee}/sales', [CsSalesRecordController::class, 'store'])->name('sales.store');
    Route::post('sales/{record}/evidence', [AttachmentController::class, 'storeForCsSalesRecord'])->middleware('throttle:uploads')->name('sales.evidence.store');
    Route::post('sales/{record}/clear', [CsSalesRecordController::class, 'clear'])->name('sales.clear');
    Route::post('sales/{record}/flag', [CsSalesRecordController::class, 'flag'])->name('sales.flag');
    Route::post('employees/{employee}/weekly-target', [CsWeeklyTargetController::class, 'store'])->name('weekly-target.store');

    // Platform/country assignment
    Route::post('platform-assignments', [CsPlatformAssignmentController::class, 'store'])->name('platform-assignments.store');
    Route::delete('platform-assignments/{assignment}', [CsPlatformAssignmentController::class, 'destroy'])->name('platform-assignments.destroy');

    // §8 quality controls: response time / resolution (employee-logged), complaints and contact-quality review (HOD-only)
    Route::post('employees/{employee}/interactions', [CsServiceInteractionController::class, 'store'])->name('interactions.store');
    Route::post('interactions/{interaction}/respond', [CsServiceInteractionController::class, 'respond'])->name('interactions.respond');
    Route::post('interactions/{interaction}/resolve', [CsServiceInteractionController::class, 'resolve'])->name('interactions.resolve');
    Route::post('employees/{employee}/complaints', [CsComplaintController::class, 'store'])->name('complaints.store');
    Route::post('complaints/{complaint}/decide', [CsComplaintController::class, 'decide'])->name('complaints.decide');
    Route::post('employees/{employee}/contact-quality-reviews', [CsContactQualityReviewController::class, 'store'])->name('contact-quality-reviews.store');

    // Settings hub + its actions
    Route::get('settings', [CsSettingsController::class, 'index'])->name('settings.index');
    Route::post('templates', [CsTaskTemplateController::class, 'store'])->name('templates.store');
    Route::patch('templates/{template}', [CsTaskTemplateController::class, 'update'])->name('templates.update');
    Route::delete('templates/{template}', [CsTaskTemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('settings/notifications/{department}', [CsNotificationSettingsController::class, 'store'])->name('settings.notifications.store');
    Route::delete('settings/notifications-recipients/{recipient}', [CsNotificationSettingsController::class, 'destroy'])->name('settings.notifications.destroy');
});
