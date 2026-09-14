<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's connection between EWMS and one external AI client (their
 * ChatGPT, their Claude) — registered by themselves at /admin/mcp. Each row
 * is effectively that external client's own OAuth identity: the AI mints a
 * fresh redirect_uri per connector instance, so two people each connecting
 * "ChatGPT" naturally end up as two separate rows here, each with their own
 * client_id/secret pair. See McpOAuthController for the flow this backs.
 */
class McpOAuthClient extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mcp_oauth_clients';

    protected $fillable = ['user_id', 'name', 'client_id', 'client_secret_hash', 'redirect_uri', 'last_used_at'];

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

    public function tokens(): HasMany
    {
        // Explicit FK — Eloquent's default derivation from "McpOAuthClient"
        // lands on mcp_o_auth_client_id (it splits before every capital,
        // "OAuth" included), not the actual mcp_oauth_client_id column.
        return $this->hasMany(McpToken::class, 'mcp_oauth_client_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(McpOAuthCode::class, 'mcp_oauth_client_id');
    }

    /** A fresh client_id/secret pair plus the row that owns it — the secret exists only in this one response, same reasoning as McpToken::issue(). */
    public static function issue(User $user, string $name, string $redirectUri): array
    {
        $clientId = 'ewms-'.bin2hex(random_bytes(8));
        $clientSecret = bin2hex(random_bytes(32));

        $client = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'client_id' => $clientId,
            'client_secret_hash' => hash('sha256', $clientSecret),
            'redirect_uri' => $redirectUri,
        ]);

        return [$client, $clientSecret];
    }

    public static function resolveById(string $clientId): ?self
    {
        return static::query()->where('client_id', $clientId)->first();
    }

    public function verifySecret(string $plaintext): bool
    {
        return hash_equals($this->client_secret_hash, hash('sha256', $plaintext));
    }
}
