<?php

use App\Http\Controllers\PushServiceWorkerController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

// Unauthenticated: a service worker must be fetchable before the browser has
// any session context to register it.
Route::get('push-sw.js', PushServiceWorkerController::class)->name('push.service-worker');

Route::middleware(['auth'])->group(function () {
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
});
