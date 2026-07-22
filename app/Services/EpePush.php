<?php

namespace App\Services;

/**
 * Ports the bootstrap config + service worker generation from Exotic Push
 * Engine's Laravel starter package (~/Desktop/exotic-push-engine/integrations/laravel)
 * without taking a dependency on that unpublished package. Kept minimal: only
 * the pieces EWMS actually serves (config/services.php `epe` block, /push-sw.js).
 */
class EpePush
{
    public static function isConfigured(): bool
    {
        return filled(config('services.epe.api_url')) && filled(config('services.epe.site_key'));
    }

    public static function bootstrapConfig(): array
    {
        return [
            'apiUrl' => config('services.epe.api_url', ''),
            'siteKey' => config('services.epe.site_key', ''),
            'serviceWorkerUrl' => '/push-sw.js',
            'appName' => config('app.name'),
        ];
    }

    // Mirrors EPE\LaravelStarter\EpePush::serviceWorkerScript(): install/activate,
    // delivery/click acknowledgement, and a postMessage so open tabs can refresh
    // a notification bell without a reload.
    public static function serviceWorkerScript(): string
    {
        $appName = json_encode((string) config('app.name'));

        return <<<JS
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

function acknowledgeDelivery(payload) {
  if (!payload.deliveryId || !payload.ackUrl) {
    return Promise.resolve();
  }
  return fetch(payload.ackUrl, { method: 'POST' }).catch(() => undefined);
}

function acknowledgeClick(clickUrl) {
  if (!clickUrl) {
    return Promise.resolve();
  }
  return fetch(clickUrl, { method: 'POST' }).catch(() => undefined);
}

self.addEventListener('push', (event) => {
  const payload = event.data ? event.data.json() : {};
  const title = payload.title || {$appName};
  const options = {
    body: payload.body || '',
    icon: payload.icon || undefined,
    image: payload.image || undefined,
    data: {
      url: payload.url || '/',
      clickUrl: payload.clickUrl || null,
    },
  };

  const notifyOpenPages = self.clients
    .matchAll({ type: 'window', includeUncontrolled: true })
    .then((clients) => {
      clients.forEach((client) => client.postMessage({ type: 'epe:push-received' }));
    })
    .catch(() => undefined);

  event.waitUntil(Promise.all([
    self.registration.showNotification(title, options),
    acknowledgeDelivery(payload),
    notifyOpenPages,
  ]));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';
  const clickUrl = event.notification.data && event.notification.data.clickUrl ? event.notification.data.clickUrl : null;
  event.waitUntil(
    Promise.all([
      acknowledgeClick(clickUrl),
      self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        for (const client of clients) {
          if ('focus' in client) {
            client.focus();
            if ('navigate' in client) {
              client.navigate(targetUrl);
            }
            return;
          }
        }
        if (self.clients.openWindow) {
          return self.clients.openWindow(targetUrl);
        }
        return undefined;
      }),
    ])
  );
});
JS;
    }
}
