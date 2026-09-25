<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An HOD's sampled review of a call or chat — Customer Service Board
 * Requirements Specification v1.0 §8 "Contact quality": accuracy,
 * professionalism, policy compliance, correct advice. Documented only; a
 * sample is deliberately not exhaustive, so it doesn't feed the automatic
 * weekly quality calculation the way response time/resolution/complaints do
 * — the HOD applies findings through the ordinary item decision instead.
 */
class CsContactQualityReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accuracy_ok' => 'boolean',
            'professionalism_ok' => 'boolean',
            'policy_compliance_ok' => 'boolean',
            'correct_advice_ok' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function serviceInteraction(): BelongsTo
    {
        return $this->belongsTo(CsServiceInteraction::class, 'service_interaction_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function passed(): bool
    {
        return $this->accuracy_ok && $this->professionalism_ok && $this->policy_compliance_ok && $this->correct_advice_ok;
    }
}
