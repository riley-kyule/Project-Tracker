<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['planned', 'active', 'on_hold', 'completed', 'cancelled'];

    public const HEALTH_STATUSES = ['on_track', 'at_risk', 'off_track'];

    protected $fillable = [
        'name',
        'description',
        'department_id',
        'owner_id',
        'status',
        'health_status',
        'priority',
        'start_date',
        'deadline',
        'progress_percentage',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'project_country');
    }

    public function websites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'project_website');
    }

    public function isAtRisk(): bool
    {
        return $this->health_status !== 'on_track'
            || ($this->deadline !== null && $this->deadline->isPast() && $this->status !== 'completed');
    }
}
