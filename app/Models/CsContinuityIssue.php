<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A platform/operational issue tracked until verified resolved, accepted as
 * a known exception, or reassigned — Customer Service Board Requirements
 * Specification v1.0 §5.2 "Issue follow-through".
 */
class CsContinuityIssue extends Model
{
    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [self::SEVERITY_LOW, self::SEVERITY_MEDIUM, self::SEVERITY_HIGH, self::SEVERITY_CRITICAL];

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_KNOWN_EXCEPTION = 'known_exception';

    public const STATUS_REASSIGNED = 'reassigned';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_KNOWN_EXCEPTION, self::STATUS_REASSIGNED];

    /** Closed states for "issue follow-through" purposes — reassigning still counts as this employee's copy being closed out. */
    public const CLOSED_STATUSES = [self::STATUS_RESOLVED, self::STATUS_KNOWN_EXCEPTION, self::STATUS_REASSIGNED];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_reported_at' => 'datetime',
            'expected_resolution' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function platformAssignment(): BelongsTo
    {
        return $this->belongsTo(CsPlatformAssignment::class, 'platform_assignment_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachments(): MorphMany
    {
        return $this->evidence();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
