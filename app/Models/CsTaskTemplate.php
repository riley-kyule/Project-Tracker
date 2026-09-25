<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The HOD-managed item library behind the Customer Service Board (Customer
 * Service Board Requirements Specification v1.0, §5/§6). Assigning one of
 * these to a card snapshots its weight/target onto the resulting
 * {@see CsCardItem} — editing a template here never rewrites an
 * already-built card. Mirrors SeoTaskTemplate.
 */
class CsTaskTemplate extends Model
{
    public const CARD_TYPE_DAILY = 'daily';

    public const CARD_TYPE_WEEKLY = 'weekly';

    public const CLASSIFICATION_MANDATORY = 'mandatory';

    public const CLASSIFICATION_PRODUCTION = 'production';

    public const CLASSIFICATION_SCHEDULED = 'scheduled';

    public const CLASSIFICATION_CONDITIONAL = 'conditional';

    public const CLASSIFICATION_ADDITIONAL = 'additional';

    public const METRIC_NEW_SALES = 'new_sales';

    public const METRIC_RENEWAL = 'renewal';

    /** The weekly "Customer-service quality" item — achievement computed from §8's response-time/resolution/complaint records, see CsServiceQualityService::refreshQualityAchievement(). */
    public const METRIC_SERVICE_QUALITY = 'service_quality';

    public const METRIC_TYPES = [self::METRIC_NEW_SALES, self::METRIC_RENEWAL, self::METRIC_SERVICE_QUALITY];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requires_quantity' => 'boolean',
            'evidence_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCardType(Builder $query, string $cardType): Builder
    {
        return $query->where('card_type', $cardType);
    }
}
