<?php

use App\Http\Controllers\Admin\WebsiteAssignmentController;
use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\Admin\WebsiteWordPressCredentialController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:api-writes'])->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::get('admin/websites', [WebsiteController::class, 'index'])->name('admin.websites.index');
    Route::post('admin/websites', [WebsiteController::class, 'store'])->name('admin.websites.store');
    Route::patch('admin/websites/{website}', [WebsiteController::class, 'update'])->name('admin.websites.update');

    Route::post('admin/websites/{website}/assignments', [WebsiteAssignmentController::class, 'store'])->name('admin.websites.assignments.store');
    Route::delete('admin/website-assignments/{websiteAssignment}', [WebsiteAssignmentController::class, 'destroy'])->name('admin.websites.assignments.destroy');

    Route::post('admin/websites/{website}/wordpress-credential/test', [WebsiteWordPressCredentialController::class, 'test'])->name('admin.websites.wordpress-credential.test');
    Route::post('admin/websites/{website}/wordpress-credential/sync', [WebsiteWordPressCredentialController::class, 'sync'])->name('admin.websites.wordpress-credential.sync');
    Route::delete('admin/websites/{website}/wordpress-credential', [WebsiteWordPressCredentialController::class, 'destroy'])->name('admin.websites.wordpress-credential.destroy');
});
