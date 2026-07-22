<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['subscriber_id' => ['required', 'string', 'max:255']]);

        $request->user()->update(['epe_subscriber_id' => $validated['subscriber_id']]);

        return back();
    }
}
