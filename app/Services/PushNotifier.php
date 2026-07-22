<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a per-user push through Exotic Push Engine's automation/workflow-event
 * path (POST /workflow/track with a subscriberId), not the CRM REST API's
 * site-wide broadcast endpoint — see ANALYTICS_BIGQUERY_FINDINGS.md-style
 * reasoning in the plan this shipped with. Requires an `api_event` automation
 * to already exist in the EPE dashboard for each `$type`; this class only
 * fires the trigger, it can't create that automation.
 *
 * No-ops (logged, not thrown) when EPE isn't configured or the user hasn't
 * opted in yet — same "unavailable until connected" spirit as the Ahrefs/CRM
 * analytics sources, rather than failing the request that triggered it.
 */
class PushNotifier
{
    public function notify(User $user, string $type, array $payload = []): void
    {
        if (! EpePush::isConfigured() || ! $user->epe_subscriber_id) {
            return;
        }

        $response = Http::asJson()->post(rtrim(config('services.epe.api_url'), '/').'/workflow/track', [
            'siteId' => config('services.epe.site_key'),
            'subscriberId' => $user->epe_subscriber_id,
            'triggerEvent' => 'api_event',
            'payload' => ['type' => $type, ...$payload],
        ]);

        if ($response->failed()) {
            Log::warning('EPE push track call failed', [
                'user_id' => $user->id,
                'type' => $type,
                'status' => $response->status(),
            ]);
        }
    }
}
