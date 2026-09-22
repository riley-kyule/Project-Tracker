<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks one "reset every WordPress staff password" run, polled by the
 * frontend the same way Deployment/DeployLatestRelease is. Deliberately
 * carries no secrets — the generated passwords never touch this row (or any
 * other table); see ResetAllWordPressStaffPasswords, which stashes them in
 * cache just long enough for the admin to retrieve them once.
 */
class WordPressPasswordReset extends Model
{
    // Laravel's class->table snake_case inference splits "WordPress" into
    // "word_press" (capital P reads as a new word boundary) — same fix as
    // WordPressUser, spelled out explicitly rather than renaming the table.
    protected $table = 'wordpress_password_resets';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'actor_id',
        'status',
        'total',
        'processed',
        'succeeded',
        'failed',
        'failures',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'failures' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Shared by the job (writes) and controller (reads/clears) so the two never drift apart. */
    public function resultsCacheKey(): string
    {
        return "wordpress-password-reset-results:{$this->id}";
    }
}
