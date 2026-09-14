<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP connector OAuth
    |--------------------------------------------------------------------------
    |
    | A minimal OAuth 2.0 authorization-code layer in front of the existing
    | McpToken bearer-token system — some MCP clients (ChatGPT's connector
    | UI, notably) only support "no auth" or full OAuth for a remote MCP
    | server, with no plain API-key option. Approving the flow reuses the
    | caller's normal EWMS login; the token it hands back IS a normal
    | McpToken under the hood (see McpOAuthController), so revoking it from
    | /admin/mcp works exactly the same as any manually-generated token.
    |
    | Single fixed client on purpose — this exists for one specific
    | connector (whichever AI client needs OAuth rather than a bare token),
    | not a general-purpose multi-client authorization server. Generate
    | client_id/secret once with `php artisan mcp:oauth-client` and paste
    | them into that client's connector setup screen alongside
    | redirect_uri, which must match exactly what it shows you there.
    |
    */

    'client_id' => env('MCP_OAUTH_CLIENT_ID'),
    'client_secret' => env('MCP_OAUTH_CLIENT_SECRET'),
    'redirect_uri' => env('MCP_OAUTH_REDIRECT_URI'),
];
