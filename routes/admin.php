<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DeploymentController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');

    Route::get('labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('labels', [LabelController::class, 'store'])->name('labels.store');
    Route::patch('labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('deployments/check', [DeploymentController::class, 'check'])->name('deployments.check');
    Route::get('deployments/latest', [DeploymentController::class, 'latest'])->name('deployments.latest');
    Route::post('deployments', [DeploymentController::class, 'store'])->name('deployments.store');
    Route::get('deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');
});
