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
