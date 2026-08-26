<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressCredential extends Model
{
    public const STATUS_UNVERIFIED = 'unverified';

    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    // See WordPressSite for why this is spelled out explicitly.
    protected $table = 'wordpress_credentials';

    protected $fillable = [
        'wordpress_site_id',
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class, 'wordpress_site_id');
    }
}
