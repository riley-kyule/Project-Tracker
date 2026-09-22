<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single weighted, evidenced line item on a {@see SeoDailyCard} or
 * {@see SeoWeeklyCard} (polymorphic "cardable"). Scoring rules per SEO Board
 * Requirements Specification v1.1 §6/§7 — see App\Services\Seo\SeoScoringService,
 * the only place that is allowed to set completion_factor/earned_points.
 */
class SeoCardItem extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_BLOCKED = 'blocked';

    public const EMPLOYEE_STATUSES = [
        self::STATUS_NOT_STARTED, self::STATUS_IN_PROGRESS, self::STATUS_SUBMITTED, self::STATUS_BLOCKED,
    ];

    /** Full marks — on time, correct, approved as-is. */
    public const DECISION_APPROVED = 'approved';

    /** Approved but late, no accepted reason — 80% per §6. */
    public const DECISION_APPROVED_LATE = 'approved_late';

    /** Completed, minor correction required — 75% per §6. */
    public const DECISION_MINOR_CORRECTION = 'minor_correction';

    /** Major rework required — 50% per §6. */
    public const DECISION_MAJOR_REWORK = 'major_rework';

    /** Rejected, unsupported, or not completed — 0% per §6. */
    public const DECISION_REJECTED = 'rejected';

    /** Valid blocker, HOD chose to exempt the item entirely (full credit, excluded from what would've been required). */
    public const DECISION_EXEMPTED = 'exempted';

    /** Valid blocker, HOD chose to exclude it from the denominator (no penalty, no points) — §7/§10. */
    public const DECISION_EXCLUDED = 'excluded';

    /** Valid blocker, HOD chose to carry the item forward to a future card — §10. */
    public const DECISION_CARRIED_FORWARD = 'carried_forward';

    public const DECISIONS = [
        self::DECISION_APPROVED, self::DECISION_APPROVED_LATE, self::DECISION_MINOR_CORRECTION,
        self::DECISION_MAJOR_REWORK, self::DECISION_REJECTED, self::DECISION_EXEMPTED,
        self::DECISION_EXCLUDED, self::DECISION_CARRIED_FORWARD,
    ];

    /** Decisions that leave the item entirely out of the card's point denominator, normalising the rest back to 100 — §10. */
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
        return $this->belongsTo(SeoTaskTemplate::class, 'template_id');
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

    public function hasValidBlockerDecision(): bool
    {
        return in_array($this->hod_decision, [self::DECISION_EXEMPTED, self::DECISION_EXCLUDED, self::DECISION_CARRIED_FORWARD], true);
    }
}
