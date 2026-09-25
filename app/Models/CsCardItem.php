<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single weighted, evidenced line item on a {@see CsDailyCard} or
 * {@see CsWeeklyCard} (polymorphic "cardable"). Scoring rules per Customer
 * Service Board Requirements Specification v1.0 §7/§8 — see
 * App\Services\Cs\CsScoringService, the only place that is allowed to set
 * completion_factor/earned_points. Mirrors SeoCardItem, plus metric_type for
 * the two weekly commercial line items.
 */
class CsCardItem extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_BLOCKED = 'blocked';

    public const EMPLOYEE_STATUSES = [
        self::STATUS_NOT_STARTED, self::STATUS_IN_PROGRESS, self::STATUS_SUBMITTED, self::STATUS_BLOCKED,
    ];

    /** Full marks — correct, complete, evidenced and on time. */
    public const DECISION_APPROVED = 'approved';

    /** Complete but late, no accepted reason — 80% per §7. */
    public const DECISION_APPROVED_LATE = 'approved_late';

    /** Minor correction required — 75% per §7. */
    public const DECISION_MINOR_CORRECTION = 'minor_correction';

    /** Major rework required — 50% per §7. */
    public const DECISION_MAJOR_REWORK = 'major_rework';

    /** Rejected, unsupported or not completed — 0% per §7. */
    public const DECISION_REJECTED = 'rejected';

    /** Valid external blocker, HOD chose to exempt the item entirely (full credit, excluded from what would've been required). */
    public const DECISION_EXEMPTED = 'exempted';

    /** Valid external blocker, HOD chose to exclude it from the denominator (no penalty, no points) — §7. */
    public const DECISION_EXCLUDED = 'excluded';

    /** Valid external blocker, HOD chose to carry the item forward to a future card — §7. */
    public const DECISION_CARRIED_FORWARD = 'carried_forward';

    public const DECISIONS = [
        self::DECISION_APPROVED, self::DECISION_APPROVED_LATE, self::DECISION_MINOR_CORRECTION,
        self::DECISION_MAJOR_REWORK, self::DECISION_REJECTED, self::DECISION_EXEMPTED,
        self::DECISION_EXCLUDED, self::DECISION_CARRIED_FORWARD,
    ];

    /** Decisions that leave the item entirely out of the card's point denominator, normalising the rest back to 100. */
    public const DECISIONS_EXCLUDED_FROM_DENOMINATOR = [self::DECISION_EXEMPTED, self::DECISION_EXCLUDED, self::DECISION_CARRIED_FORWARD];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'target_quantity' => 'decimal:2',
            'available_target_quantity' => 'decimal:2',
            'achieved_quantity' => 'decimal:2',
            'evidence_required' => 'boolean',
            'submitted_at' => 'datetime',
            'escalated_at' => 'datetime',
            'completion_factor' => 'decimal:2',
            'earned_points' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function cardable(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CsTaskTemplate::class, 'template_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Alias for evidence() — AttachmentController's generic attach()/parentOf() flow expects every attachable to expose attachments(). */
    public function attachments(): MorphMany
    {
        return $this->evidence();
    }

    public function isDecided(): bool
    {
        return $this->hod_decision !== null;
    }

    /** True for the two commercial weekly items and the service-quality weekly item — all three compute their own achievement (see CsScoringService::decide()) rather than taking a manually typed quantity. */
    public function isAutoCalculatedMetric(): bool
    {
        return $this->metric_type !== null;
    }

    public function hasValidBlockerDecision(): bool
    {
        return in_array($this->hod_decision, [self::DECISION_EXEMPTED, self::DECISION_EXCLUDED, self::DECISION_CARRIED_FORWARD], true);
    }
}
