<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteWordPressCredential extends Model
{
    // Laravel's class->table snake_case inference splits "WordPress" into
    // "word_press" (capital P reads as a new word boundary), which wouldn't
    // match the more natural "wordpress" table names used here — spelled out
    // explicitly instead of renaming the tables to match the inferred split.
    protected $table = 'website_wordpress_credentials';

    public const STATUS_UNVERIFIED = 'unverified';

    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'website_id',
        'wp_username',
        'wp_app_password',
        'status',
        'last_verified_at',
        'last_error',
        'last_synced_at',
    ];

    protected $hidden = [
        'wp_app_password',
    ];

    protected function casts(): array
    {
        return [
            // Laravel's built-in encrypt-on-write/decrypt-on-read cast — this
            // is a credential at rest in the database, not a plaintext column.
            'wp_app_password' => 'encrypted',
            'last_verified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
