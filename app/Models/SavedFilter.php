<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedFilter extends Model
{
    public const SCOPE_REPORTS_TASKS = 'reports.tasks';

    /** One scope string per board (e.g. "board.42") rather than a single shared scope, since board filters (search/assignee/priority) are per-board, not global like the reports list. */
    public static function boardScope(int $boardId): string
    {
        return "board.{$boardId}";
    }

    protected $fillable = [
        'user_id',
        'scope',
        'name',
        'filters',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
