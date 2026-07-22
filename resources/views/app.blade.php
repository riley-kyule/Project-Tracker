<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'EWMS') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <meta name="theme-color" content="#2478be">

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead

        @if (\App\Services\EpePush::isConfigured())
            <script>
                window.ExoticPushEngineConfig = @json(\App\Services\EpePush::bootstrapConfig());
                window.addEventListener('epe:subscribed', function (event) {
                    var csrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
                    fetch('{{ route('push.subscribe') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-XSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ subscriber_id: event.detail.subscriberId }),
                    });
                });
            </script>
            <script defer src="/assets/epe-sdk.js"></script>
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
