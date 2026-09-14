<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpOAuthCode extends Model
{
    public const UPDATED_AT = null;

    // Eloquent's default snake_case derivation from "McpOAuthCode" lands on
    // mcp_o_auth_codes (it splits before every capital, "OAuth" included) —
    // pin it explicitly to match the migration.
    protected $table = 'mcp_oauth_codes';

    protected $fillable = ['user_id', 'mcp_oauth_client_id', 'code_hash', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(McpOAuthClient::class, 'mcp_oauth_client_id');
    }

    /** A fresh opaque code plus the row that owns it — 5 minutes, single-use, exactly like a normal OAuth authorization code. */
    public static function issue(User $user, McpOAuthClient $client, string $redirectUri, ?string $codeChallenge, ?string $codeChallengeMethod): array
    {
        $plaintext = bin2hex(random_bytes(32));

        $record = static::create([
            'user_id' => $user->id,
            'mcp_oauth_client_id' => $client->id,
            'code_hash' => hash('sha256', $plaintext),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'expires_at' => now()->addMinutes(5),
        ]);

        return [$record, $plaintext];
    }

    /** Valid means: exists, unexpired, and not already redeemed — resolving it does not itself mark it used, callers must do that atomically once the exchange actually succeeds. */
    public static function resolveValid(string $plaintext): ?self
    {
        return static::query()
            ->where('code_hash', hash('sha256', $plaintext))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
