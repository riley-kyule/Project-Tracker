<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_NEW = 'new';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_USER = 'waiting_user';

    public const STATUS_WAITING_THIRD_PARTY = 'waiting_third_party';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REOPENED = 'reopened';

    public const OPEN_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_USER,
        self::STATUS_WAITING_THIRD_PARTY,
        self::STATUS_ESCALATED,
        self::STATUS_REOPENED,
    ];

    public const RESOLUTION_METHODS = ['remote', 'office', 'onsite', 'third_party'];

    public const TEAM_IT = 'it';

    public const TEAM_RND = 'rnd';

    public const TEAM_BOTH = 'both';

    public const TEAMS = [self::TEAM_IT, self::TEAM_RND, self::TEAM_BOTH];

    /**
     * Which department(s) service a given team value — 'both' spans both
     * queues. The department itself (not a Spatie permission) is what scopes
     * routing, the same way IT-only concerns like createForOthers() already
     * key off Department::where('slug', 'it') rather than a role name — a
     * technician's queue is which department they're in, not which role they
     * hold.
     */
    public const TEAM_DEPARTMENT_SLUGS = [
        self::TEAM_IT => ['it'],
        // Str::slug('Research & Development') drops the ampersand rather than
        // spelling out "and" — verified directly, not guessed.
        self::TEAM_RND => ['research-development'],
        self::TEAM_BOTH => ['it', 'research-development'],
    ];

    /** Legal lifecycle transitions (WORKFLOWS.md); resolve/reopen have dedicated endpoints. */
    public const TRANSITIONS = [
        self::STATUS_NEW => [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS],
        self::STATUS_ASSIGNED => [self::STATUS_IN_PROGRESS, self::STATUS_WAITING_USER, self::STATUS_WAITING_THIRD_PARTY, self::STATUS_ESCALATED],
        self::STATUS_IN_PROGRESS => [self::STATUS_WAITING_USER, self::STATUS_WAITING_THIRD_PARTY, self::STATUS_ESCALATED],
        self::STATUS_WAITING_USER => [self::STATUS_IN_PROGRESS],
        self::STATUS_WAITING_THIRD_PARTY => [self::STATUS_IN_PROGRESS],
        self::STATUS_ESCALATED => [self::STATUS_IN_PROGRESS],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED],
        self::STATUS_REOPENED => [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS],
    ];

    protected $fillable = [
        'title',
        'description',
        'requester_id',
        'created_by',
        'department_id',
        'team',
        'assigned_to',
        'category_id',
        'subcategory_id',
        'priority',
        'impact',
        'urgency',
        'status',
        'related_system',
        'due_at',
        'resolution_method',
        'resolution_summary',
        'time_spent_minutes',
        'satisfaction_score',
    ];

    protected function casts(): array
    {
        return [
            'first_responded_at' => 'datetime',
            'due_at' => 'datetime',
            'assigned_at' => 'datetime',
            'last_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** Who actually filed the ticket — differs from requester() when IT raises it on someone's behalf. */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function convertedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'converted_task_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->latest('created_at');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** Is $user in a department that services this ticket's team (IT, R&D, or both for a 'both' ticket)? */
    public function servicedBy(User $user): bool
    {
        $slug = $user->department?->slug;

        return $slug !== null && in_array($slug, self::TEAM_DEPARTMENT_SLUGS[$this->team] ?? [self::TEAM_IT], true);
    }
}
