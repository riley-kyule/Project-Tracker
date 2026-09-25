<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A customer complaint, linked to the responsible interaction and an HOD
 * decision — Customer Service Board Requirements Specification v1.0 §8
 * "Customer complaints". A substantiated complaint feeds the weekly
 * "Customer-service quality" achievement calculation and gives the HOD a
 * documented reason to reduce a specific item's score through the ordinary
 * decide() reason field.
 */
class CsComplaint extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_SUBSTANTIATED = 'substantiated';

    public const STATUS_UNSUBSTANTIATED = 'unsubstantiated';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_SUBSTANTIATED, self::STATUS_UNSUBSTANTIATED, self::STATUS_RESOLVED];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function serviceInteraction(): BelongsTo
    {
        return $this->belongsTo(CsServiceInteraction::class, 'service_interaction_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachments(): MorphMany
    {
        return $this->evidence();
    }

    public function isDecided(): bool
    {
        return in_array($this->status, [self::STATUS_SUBSTANTIATED, self::STATUS_UNSUBSTANTIATED], true);
    }
}
