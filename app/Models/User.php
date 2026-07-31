<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'department_id',
        'manager_id',
        'job_title',
        'status',
        'timezone',
        'notification_preferences',
        'epe_subscriber_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'last_login_at' => 'datetime',
            'sessions_invalidated_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Additional departments this user has been granted visibility into, beyond their primary department(). */
    public function departmentMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_members')->withTimestamps();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function assignedOpenTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'primary_assignee_id')
            ->whereNull('completed_at')
            ->whereNull('archived_at');
    }

    public function assignedOverdueTasks(): HasMany
    {
        return $this->assignedOpenTasks()->where('due_at', '<', now());
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Absent key means enabled — the column has no seeded default, so "never
     * configured" must not silence anything. A deactivated/suspended account
     * never wants notifications, regardless of its stored preferences.
     */
    public function wantsNotification(string $type): bool
    {
        return $this->isActive() && ($this->notification_preferences[$type] ?? true) !== false;
    }

    /**
     * Role/permission holders (CEO, Administrator, Marketing) see it regardless
     * of department; everyone else needs to belong (primary or granted, via
     * departmentMemberships()) to Marketing or one of its sub-departments.
     */
    public function canViewMarketingStatistics(): bool
    {
        if ($this->can('view marketing statistics')) {
            return true;
        }

        $marketing = Department::query()->where('slug', 'marketing')->first();

        if ($marketing === null) {
            return false;
        }

        $memberDepartmentIds = [
            ...($this->department_id !== null ? [$this->department_id] : []),
            ...$this->departmentMemberships()->pluck('departments.id'),
        ];

        return collect($memberDepartmentIds)->contains(fn ($id) => in_array($id, $marketing->descendantIds(), true));
    }

    public function websiteAssignments(): HasMany
    {
        return $this->hasMany(WebsiteAssignment::class);
    }

    public function assignedWebsites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'website_assignments')->withPivot('id', 'team')->withTimestamps();
    }
}
