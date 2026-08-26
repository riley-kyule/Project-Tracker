<?php

use App\Http\Controllers\Admin\CompanySettingController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DeploymentController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\QueueHealthController;
use App\Http\Controllers\Admin\ReportDeliveryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WordPressSiteController;
use App\Http\Controllers\Admin\WordPressUserBulkActionController;
use App\Http\Controllers\Admin\WordPressUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:api-writes'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');

    Route::patch('company-settings', [CompanySettingController::class, 'update'])->name('company-settings.update');

    Route::get('labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('labels', [LabelController::class, 'store'])->name('labels.store');
    Route::patch('labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('deployments/check', [DeploymentController::class, 'check'])->name('deployments.check');
    Route::get('deployments/latest', [DeploymentController::class, 'latest'])->name('deployments.latest');
    Route::post('deployments', [DeploymentController::class, 'store'])->name('deployments.store');
    Route::get('deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');

    Route::get('queue-health', [QueueHealthController::class, 'index'])->name('queue-health.index');
    Route::get('report-deliveries', [ReportDeliveryController::class, 'index'])->name('report-deliveries.index');

    Route::get('wordpress-users', [WordPressUserController::class, 'index'])->name('wordpress-users.index');
    Route::post('wordpress-users/sync', [WordPressUserController::class, 'syncAll'])->name('wordpress-users.sync-all');
    Route::post('wordpress-users/bulk-add', [WordPressUserBulkActionController::class, 'add'])->name('wordpress-users.bulk-add');
    Route::post('wordpress-users/bulk-change-role', [WordPressUserBulkActionController::class, 'changeRole'])->name('wordpress-users.bulk-change-role');
    Route::post('wordpress-users/bulk-update-email', [WordPressUserBulkActionController::class, 'updateEmail'])->name('wordpress-users.bulk-update-email');
    Route::delete('wordpress-users/bulk-delete', [WordPressUserBulkActionController::class, 'destroy'])->name('wordpress-users.bulk-delete');

    Route::post('wordpress-users/sites', [WordPressSiteController::class, 'store'])->name('wordpress-users.sites.store');
    Route::patch('wordpress-users/sites/{site}', [WordPressSiteController::class, 'update'])->name('wordpress-users.sites.update');
    Route::delete('wordpress-users/sites/{site}', [WordPressSiteController::class, 'destroy'])->name('wordpress-users.sites.destroy');
    Route::post('wordpress-users/sites/{site}/test', [WordPressSiteController::class, 'test'])->name('wordpress-users.sites.test');
    Route::post('wordpress-users/sites/{site}/sync', [WordPressSiteController::class, 'sync'])->name('wordpress-users.sites.sync');
});
