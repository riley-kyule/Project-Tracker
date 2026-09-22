<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The HOD-managed item library behind the SEO Board (SEO Board Requirements
 * Specification v1.1, §4.4/§5.1). Assigning one of these to a card snapshots
 * its weight/target onto the resulting {@see SeoCardItem} — editing a
 * template here never rewrites an already-built card.
 */
class SeoTaskTemplate extends Model
{
    public const CARD_TYPE_DAILY = 'daily';

    public const CARD_TYPE_WEEKLY = 'weekly';

    public const CLASSIFICATION_MANDATORY = 'mandatory';

    public const CLASSIFICATION_PRODUCTION = 'production';

    public const CLASSIFICATION_SCHEDULED = 'scheduled';

    public const CLASSIFICATION_CONDITIONAL = 'conditional';

    public const CLASSIFICATION_ADDITIONAL = 'additional';

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
