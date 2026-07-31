<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Immutable once created — see the report_snapshots migration trigger. Never call update()/delete() on this model. */
class ReportSnapshot extends Model
{
    public const TYPE_CEO_DAILY = 'ceo_daily';

    public const TYPE_DEPARTMENT_DAILY = 'department_daily';

    public const TYPE_WEEKLY_PERSONAL = 'weekly_personal';

    public const STATUS_GENERATED = 'generated';

    protected $fillable = [
        'report_date',
        'report_type',
        'department_id',
        'user_id',
        'generated_at',
        'payload',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'generated_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ReportDelivery::class);
    }
}
