<?php

use App\Http\Controllers\Testing\E2eAuthController;
use Illuminate\Support\Facades\Route;

// Route only exists at all outside local/testing + ALLOW_E2E_LOGIN — see
// E2eAuthController's docblock. Registration-time gating (not just the
// controller's own check) means this 404s by simply not being a route,
// rather than depending on the controller remembering to abort.
if (app()->environment(['local', 'testing']) && config('app.allow_e2e_login')) {
    Route::post('_e2e/login', [E2eAuthController::class, 'login'])->middleware('throttle:10,1')->name('e2e.login');
}
