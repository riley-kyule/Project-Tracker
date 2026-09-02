<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An effective-dated bundle of Kenyan statutory rates (PAYE bands, personal &
 * insurance relief, NSSF tiers, SHIF, Affordable Housing Levy, NITA). The
 * calculator only ever reads `payload` — no rate is hardcoded anywhere.
 */
class StatutoryRateSet extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'payload' => 'array',
        ];
    }

    /** The rate set in force on a given date (latest effective_from that has started). */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString()))
            ->orderByDesc('effective_from');
    }

    public static function inForceOn(Carbon $date): ?self
    {
        return static::query()->forDate($date)->first();
    }
}
