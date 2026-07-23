<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const PRIORITIES = ['critical', 'high', 'medium', 'low'];

    public const CONFIDENTIALITY_NORMAL = 'normal';

    public const CONFIDENTIALITY_RESTRICTED = 'restricted';

    public const CONFIDENTIALITY_CONFIDENTIAL = 'confidential';

    public const CONFIDENTIALITY_LEVELS = [
        self::CONFIDENTIALITY_NORMAL,
        self::CONFIDENTIALITY_RESTRICTED,
        self::CONFIDENTIALITY_CONFIDENTIAL,
    ];

    public const ASSIGNMENT_TYPES = ['assignee', 'collaborator', 'reviewer', 'watcher'];

    protected $fillable = [
        'title',
        'description',
        'department_id',
        'board_id',
        'board_column_id',
        'project_id',
        'position',
        'created_by',
        'primary_assignee_id',
        'priority',
        'start_date',
        'due_at',
        'estimated_minutes',
        'progress_percentage',
        'ceo_priority',
        'confidentiality',
        'work_location',
        'metadata',
        'recurrence_rule_id',
        'previous_recurrence_task_id',
        'approval_status',
        'approver_id',
        'approved_at',
        'approval_note',
    ];

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_at' => 'datetime',
            'ceo_priority' => 'boolean',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'task_country');
    }

    public function websites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'task_website');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_assignee_id');
    }

    /** Multi-person assignment (collaborators/reviewers/watchers), kept in sync with primary_assignee_id by TaskAssigneeSync. */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')->withPivot('assignment_type')->withTimestamps();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_labels');
    }

    /** Department Managers with an explicit grant to view this task despite its confidentiality level. */
    public function confidentialGrants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_confidential_grants')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    public function isConfidential(): bool
    {
        return $this->confidentiality !== self::CONFIDENTIALITY_NORMAL;
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class)->orderBy('position');
    }

    /** All items across every checklist this task has, for progress calculation. */
    public function checklistItems(): HasManyThrough
    {
        return $this->hasManyThrough(ChecklistItem::class, Checklist::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function links(): HasMany
    {
        return $this->hasMany(TaskLink::class)->latest();
    }

    /** Prerequisites this task depends on (rows where this task is the successor). */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'successor_task_id');
    }

    /** Tasks that this task blocks (rows where this task is the predecessor). */
    public function blocks(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_task_id');
    }

    public function unresolvedDependencies(): HasMany
    {
        return $this->dependencies()
            ->whereNull('overridden_at')
            ->whereHas('predecessor', fn ($query) => $query->whereNull('completed_at'));
    }

    public function isBlocked(): bool
    {
        return $this->unresolvedDependencies()->exists();
    }

    /**
     * task_relations is symmetric and normalized (lower task ID stored as
     * task_id), so a given task can appear on either side of a row —
     * these two relations cover both, combined into one list by callers.
     */
    public function relationsAsTask(): HasMany
    {
        return $this->hasMany(TaskRelation::class, 'task_id');
    }

    public function relationsAsRelatedTask(): HasMany
    {
        return $this->hasMany(TaskRelation::class, 'related_task_id');
    }

    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(RecurrenceRule::class);
    }

    public function previousInstance(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'previous_recurrence_task_id');
    }

    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'trackable');
    }

    /** Recomputed from source data rather than incremented, so it can never drift. */
    public function recalculateActualMinutes(): void
    {
        $seconds = $this->timeEntries()
            ->get()
            ->filter(fn (TimeEntry $entry) => $entry->countsTowardTotal())
            ->sum('duration_seconds');

        $this->forceFill(['actual_minutes' => intdiv($seconds, 60)])->save();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
