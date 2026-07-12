<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    protected $fillable = ['parent_id', 'name', 'default_priority', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TicketCategory::class, 'parent_id');
    }
}
