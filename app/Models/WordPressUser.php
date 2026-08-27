<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressUser extends Model
{
    /** Staff, as opposed to the customer/member accounts a site's WordPress user table may also hold — see WordPressUserClient::fetchAllUsers(). */
    public const STAFF_EMAIL_DOMAIN = 'exotic-online.com';

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

    /** Defense-in-depth only — the sync itself already restricts what gets fetched/stored to this domain (see WordPressUserClient::fetchAllUsers()). */
    public function scopeStaffOnly(Builder $query): Builder
    {
        return $query->where('email', 'like', '%@'.self::STAFF_EMAIL_DOMAIN);
    }
}
