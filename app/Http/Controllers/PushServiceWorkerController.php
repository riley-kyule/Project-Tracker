<?php

namespace App\Http\Controllers;

use App\Services\EpePush;
use Illuminate\Http\Response;

class PushServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        return response(EpePush::serviceWorkerScript(), 200, [
            'Content-Type' => 'application/javascript',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
