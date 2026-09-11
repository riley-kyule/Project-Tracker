<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpToken extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'name', 'token_hash', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A fresh 40-char random token plus the row that owns it — the token itself is returned nowhere else. */
    public static function issue(User $user, string $name): array
    {
        $plaintext = bin2hex(random_bytes(20));

        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plaintext),
            'created_at' => now(),
        ]);

        return [$token, $plaintext];
    }

    public static function resolve(string $plaintext): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $plaintext))->first();
    }
}
