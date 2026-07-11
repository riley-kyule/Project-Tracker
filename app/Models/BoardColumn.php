<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'board_id',
        'name',
        'slug',
        'position',
        'semantic_status',
        'is_completion_column',
        'is_archive_column',
        'wip_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_completion_column' => 'boolean',
            'is_archive_column' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }
}
