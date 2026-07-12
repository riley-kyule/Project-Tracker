<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    protected $fillable = [
        'predecessor_task_id',
        'successor_task_id',
        'dependency_type',
        'overridden_at',
        'overridden_by',
        'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'overridden_at' => 'datetime',
        ];
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_task_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_task_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    public function isOverridden(): bool
    {
        return $this->overridden_at !== null;
    }
}
