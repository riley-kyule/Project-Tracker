<?php

use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

    Route::get('admin/websites', [WebsiteController::class, 'index'])->name('admin.websites.index');
    Route::post('admin/websites', [WebsiteController::class, 'store'])->name('admin.websites.store');
    Route::patch('admin/websites/{website}', [WebsiteController::class, 'update'])->name('admin.websites.update');
});
