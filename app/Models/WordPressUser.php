<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressUser extends Model
{
    // Laravel's class->table snake_case inference splits "WordPress" into
    // "word_press" (capital P reads as a new word boundary), which wouldn't
    // match the more natural "wordpress_users" table name — spelled out
    // explicitly instead of renaming the table to match the inferred split.
    protected $table = 'wordpress_users';

    protected $fillable = [
        'wordpress_site_id',
        'wp_user_id',
        'username',
        'email',
        'display_name',
        'roles',
        'wp_registered_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'wp_registered_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class, 'wordpress_site_id');
    }

    /** Staff, as opposed to customer/other accounts a WordPress site's user table may also hold. */
    public function scopeStaffOnly(Builder $query): Builder
    {
        return $query->where('email', 'like', '%@exotic-online.com');
    }
}
