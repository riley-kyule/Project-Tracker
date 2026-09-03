<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    /** How long after posting the author may still edit their own comment. */
    public const EDIT_WINDOW_MINUTES = 10;

    public const NOTE_COMPLETED_WORK = 'completed_work';

    public const NOTE_BLOCKER = 'blocker';

    public const NOTE_NEXT_ACTION = 'next_action';

    public const NOTE_SUPPORT_REQUIRED = 'support_required';

    public const NOTE_TYPES = [
        self::NOTE_COMPLETED_WORK,
        self::NOTE_BLOCKER,
        self::NOTE_NEXT_ACTION,
        self::NOTE_SUPPORT_REQUIRED,
    ];

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'parent_id',
        'user_id',
        'body',
        'is_internal',
        'note_type',
        'structured_fields',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'edited_at' => 'datetime',
            'structured_fields' => 'array',
        ];
    }

    /** True for a structured progress note (see NOTE_TYPES) — false for an ordinary comment. */
    public function isProgressNote(): bool
    {
        return $this->note_type !== null;
    }

    /**
     * The author may edit their own free-text comment for a short window after
     * posting (EDIT_WINDOW_MINUTES). Structured progress notes are not
     * free-text-editable and deleted comments can't be edited.
     */
    public function isEditableBy(User $user): bool
    {
        return $this->user_id === $user->id
            && $this->note_type === null
            && $this->deleted_at === null
            && $this->created_at !== null
            && $this->created_at->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }

    public function scopeOrdinary(Builder $query): Builder
    {
        return $query->whereNull('note_type');
    }

    public function scopeProgressNotes(Builder $query): Builder
    {
        return $query->whereNotNull('note_type');
    }
}
