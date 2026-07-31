<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    public const TEMPLATE_CUSTOMER_SERVICE = 'customer_service';

    public const TEMPLATE_SEO = 'seo';

    public const TEMPLATE_IT = 'it';

    public const TEMPLATE_CONTENT = 'content';

    public const WORKFLOW_TEMPLATES = [
        self::TEMPLATE_CUSTOMER_SERVICE,
        self::TEMPLATE_SEO,
        self::TEMPLATE_IT,
        self::TEMPLATE_CONTENT,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_department_id',
        'manager_id',
        'assistant_manager_id',
        'is_active',
        'workflow_template',
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

    /** Users whose primary department_id points here. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Additional, granted-not-primary members — see the department_members migration. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_members')->withTimestamps();
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
