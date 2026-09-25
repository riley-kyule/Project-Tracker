<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The approved exchange-rate source behind cs_sales_records' currency
 * conversion — Customer Service Board Requirements Specification v1.0 §4
 * "Currency". Manually maintained; see the migration's note on why no live
 * rate feed is wired up yet.
 */
class CsExchangeRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate_to_reporting_currency' => 'decimal:6',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
