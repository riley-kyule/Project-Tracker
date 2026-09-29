<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One of the five fixed daily continuity checks — Customer Service Board
 * Requirements Specification v1.0 §5.2.
 */
class CsContinuityCheck extends Model
{
    public const TYPE_REGISTRATION_LOGIN = 'registration_login';

    public const TYPE_PAYMENTS_ACTIVATION = 'payments_activation';

    public const TYPE_LISTINGS_CONTACT_ACCESS = 'listings_contact_access';

    public const TYPE_COMPLAINTS_SERVICE_DESK = 'complaints_service_desk';

    public const TYPE_ISSUE_FOLLOW_THROUGH = 'issue_follow_through';

    public const TYPES = [
        self::TYPE_REGISTRATION_LOGIN, self::TYPE_PAYMENTS_ACTIVATION, self::TYPE_LISTINGS_CONTACT_ACCESS,
        self::TYPE_COMPLAINTS_SERVICE_DESK, self::TYPE_ISSUE_FOLLOW_THROUGH,
    ];

    public const STATUS_OK = 'ok';

    public const STATUS_ISSUE_FOUND = 'issue_found';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['check_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function platformAssignment(): BelongsTo
    {
        return $this->belongsTo(CsPlatformAssignment::class, 'platform_assignment_id');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachments(): MorphMany
    {
        return $this->evidence();
    }
}
