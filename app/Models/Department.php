<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_department_id',
        'manager_id',
        'assistant_manager_id',
        'is_active',
        'daily_summary_time',
        'daily_summary_last_sent_on',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_summary_last_sent_on' => 'date',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function assistantManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_manager_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leads(int $userId): bool
    {
        return $this->manager_id === $userId || $this->assistant_manager_id === $userId;
    }

    /**
     * Self + every descendant id, walked level-by-level rather than assuming
     * one level of nesting — keeps working if the hierarchy ever grows past
     * today's Marketing -> SEO/Social Media/Content depth.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = static::query()->whereIn('parent_department_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
