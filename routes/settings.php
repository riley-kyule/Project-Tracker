<?php

use App\Http\Controllers\Settings\GoogleDriveController;
use App\Http\Controllers\Settings\IntegrationSettingsController;
use App\Http\Controllers\Settings\NotificationPreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications', [NotificationPreferencesController::class, 'update'])->name('notifications.update');

    Route::get('settings/integrations', [IntegrationSettingsController::class, 'edit'])->name('integrations.edit');
    Route::patch('settings/integrations', [IntegrationSettingsController::class, 'update'])->name('integrations.update');

    Route::get('settings/integrations/google-drive/connect', [GoogleDriveController::class, 'connect'])->name('integrations.google-drive.connect');
    Route::get('settings/integrations/google-drive/callback', [GoogleDriveController::class, 'callback'])->name('integrations.google-drive.callback');
    Route::delete('settings/integrations/google-drive', [GoogleDriveController::class, 'disconnect'])->name('integrations.google-drive.disconnect');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
