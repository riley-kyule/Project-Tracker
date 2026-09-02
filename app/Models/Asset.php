<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_REPAIR = 'in_repair';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_LOST = 'lost';

    public const STATUSES = [
        self::STATUS_IN_STOCK,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_REPAIR,
        self::STATUS_RETIRED,
        self::STATUS_LOST,
    ];

    public const CONDITIONS = ['new', 'good', 'fair', 'poor'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->orderByDesc('assigned_at');
    }

    /** The open assignment, if any — its employee is the current custodian. */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latestOfMany('assigned_at');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_STOCK);
    }
}
