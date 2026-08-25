<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressUser extends Model
{
    // See WebsiteWordPressCredential for why this is spelled out explicitly.
    protected $table = 'wordpress_users';

    protected $fillable = [
        'website_id',
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

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /** Staff, as opposed to customer/other accounts a WordPress site's user table may also hold. */
    public function scopeStaffOnly(Builder $query): Builder
    {
        return $query->where('email', 'like', '%@exotic-online.com');
    }
}
