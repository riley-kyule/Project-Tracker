<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an immutable audit entry for a critical mutation.
     * Rows are insert-only; there is no application path that updates them.
     *
     * $auditable is nullable only for events with no resolvable subject,
     * e.g. a failed login attempt against an email that matches no user.
     */
    public static function log(?Model $auditable, string $event, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => auth()->id(),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'event' => $event,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
