<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AnalyticsSourceStale extends Notification
{
    use Queueable;

    public function __construct(
        public string $source,
        public string $status,
        public ?string $error,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'analytics_source_stale',
            'source' => $this->source,
            'status' => $this->status,
            'message' => ucfirst($this->source)." analytics data is {$this->status}".($this->error ? ": {$this->error}" : '.'),
        ];
    }
}
