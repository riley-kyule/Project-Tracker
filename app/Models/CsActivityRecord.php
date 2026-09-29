<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One contact attempt on one of the four §5.1 workstreams — new customer,
 * renewal, reactivation, or payment support — whether or not it ended in a
 * sale. Customer Service Board Requirements Specification v1.0 §5.1: "EWMS
 * shall distinguish attempted contact, delivered contact, customer response,
 * qualified interest, registration, cleared payment and completed
 * activation" — the STAGE_* constants are exactly that funnel, in order.
 */
class CsActivityRecord extends Model
{
    public const WORKSTREAM_NEW_CUSTOMER = 'new_customer';

    public const WORKSTREAM_RENEWAL = 'renewal';

    public const WORKSTREAM_REACTIVATION = 'reactivation';

    public const WORKSTREAM_PAYMENT_SUPPORT = 'payment_support';

    public const WORKSTREAMS = [
        self::WORKSTREAM_NEW_CUSTOMER, self::WORKSTREAM_RENEWAL, self::WORKSTREAM_REACTIVATION, self::WORKSTREAM_PAYMENT_SUPPORT,
    ];

    public const STAGE_ATTEMPTED_CONTACT = 'attempted_contact';

    public const STAGE_DELIVERED_CONTACT = 'delivered_contact';

    public const STAGE_CUSTOMER_RESPONSE = 'customer_response';

    public const STAGE_QUALIFIED_INTEREST = 'qualified_interest';

    public const STAGE_REGISTRATION = 'registration';

    public const STAGE_CLEARED_PAYMENT = 'cleared_payment';

    public const STAGE_COMPLETED_ACTIVATION = 'completed_activation';

    /** In funnel order — a later stage implies every earlier one already happened. */
    public const STAGES = [
        self::STAGE_ATTEMPTED_CONTACT, self::STAGE_DELIVERED_CONTACT, self::STAGE_CUSTOMER_RESPONSE,
        self::STAGE_QUALIFIED_INTEREST, self::STAGE_REGISTRATION, self::STAGE_CLEARED_PAYMENT, self::STAGE_COMPLETED_ACTIVATION,
    ];

    public const CHANNELS = ['chat', 'call', 'email', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contact_time' => 'datetime',
            'expiry_date' => 'date',
            'restored_service' => 'boolean',
            'amount' => 'decimal:2',
            'activation_confirmed' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function salesRecord(): BelongsTo
    {
        return $this->belongsTo(CsSalesRecord::class, 'sales_record_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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
