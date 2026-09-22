<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'report_snapshot_id',
        'recipient_user_id',
        'recipient_email',
        'recipient_name',
        'status',
        'queued_at',
        'sent_at',
        'failed_at',
        'failure_reason',
        'retry_count',
        'admin_alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'admin_alerted_at' => 'datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'report_snapshot_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** The address actually mailed — a linked user's email, or the standalone recipient_email for a non-user recipient. */
    public function resolvedEmail(): ?string
    {
        return $this->recipient?->email ?? $this->recipient_email;
    }

    public function resolvedName(): ?string
    {
        return $this->recipient?->name ?? $this->recipient_name;
    }
}
