<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RecurrenceRule extends Model
{
    use HasFactory;

    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom', 'after_completion'];

    protected $fillable = [
        'template_task_id',
        'frequency',
        'interval_value',
        'schedule_config',
        'next_run_at',
        'last_generated_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'schedule_config' => 'array',
            'next_run_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'template_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generatedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'recurrence_rule_id');
    }

    /**
     * Event-driven "after completion" rules have no scheduled next_run_at;
     * every other frequency advances on a fixed cadence from $from.
     */
    public function calculateNextRun(Carbon $from): ?Carbon
    {
        return match ($this->frequency) {
            'daily' => $from->copy()->addDays($this->interval_value),
            'weekly' => $from->copy()->addWeeks($this->interval_value),
            'monthly' => $from->copy()->addMonthsNoOverflow($this->interval_value),
            'quarterly' => $from->copy()->addMonthsNoOverflow($this->interval_value * 3),
            'yearly' => $from->copy()->addYears($this->interval_value),
            'custom' => $from->copy()->addDays($this->schedule_config['interval_days'] ?? $this->interval_value),
            default => null,
        };
    }
}
