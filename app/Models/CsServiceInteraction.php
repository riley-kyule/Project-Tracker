<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One customer enquiry, from receipt through first response to resolution —
 * Customer Service Board Requirements Specification v1.0 §8 "Response time"
 * / "Resolution". Backs the weekly "Customer-service quality" item's
 * automatic achievement calculation, see
 * App\Services\Cs\CsServiceQualityService.
 */
class CsServiceInteraction extends Model
{
    public const RESOLUTION_FIRST_CONTACT = 'first_contact_resolution';

    public const RESOLUTION_AFTER_ESCALATION = 'resolved_after_escalation';

    public const RESOLUTION_PENDING_CUSTOMER = 'pending_customer';

    public const RESOLUTION_PENDING_INTERNAL = 'pending_internal_owner';

    public const RESOLUTION_UNRESOLVED = 'unresolved';

    public const RESOLUTION_STATUSES = [
        self::RESOLUTION_FIRST_CONTACT, self::RESOLUTION_AFTER_ESCALATION,
        self::RESOLUTION_PENDING_CUSTOMER, self::RESOLUTION_PENDING_INTERNAL, self::RESOLUTION_UNRESOLVED,
    ];

    /** Counted as "resolved" for the quality achievement rate — the other statuses (pending/unresolved) are not. */
    public const RESOLVED_STATUSES = [self::RESOLUTION_FIRST_CONTACT, self::RESOLUTION_AFTER_ESCALATION];

    public const CHANNELS = ['chat', 'call', 'email', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enquiry_received_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function cardItem(): BelongsTo
    {
        return $this->belongsTo(CsCardItem::class, 'card_item_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(CsComplaint::class, 'service_interaction_id');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachments(): MorphMany
    {
        return $this->evidence();
    }

    public function isWithinStandard(): ?bool
    {
        if ($this->first_response_at === null) {
            return null;
        }

        return $this->enquiry_received_at->diffInMinutes($this->first_response_at) <= $this->response_standard_minutes;
    }

    public function isResolved(): bool
    {
        return in_array($this->resolution_status, self::RESOLVED_STATUSES, true);
    }
}
