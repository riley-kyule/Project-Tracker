<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const PRIORITIES = ['critical', 'high', 'medium', 'low'];

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
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_at' => 'datetime',
            'ceo_priority' => 'boolean',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'metadata' => 'array',
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

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_labels');
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
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
}
