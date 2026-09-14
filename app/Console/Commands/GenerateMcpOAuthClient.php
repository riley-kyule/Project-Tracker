<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateMcpOAuthClient extends Command
{
    protected $signature = 'mcp:oauth-client';

    protected $description = 'Print a fresh MCP_OAUTH_CLIENT_ID / MCP_OAUTH_CLIENT_SECRET pair to put in .env — generated locally, never stored anywhere';

    public function handle(): int
    {
        $clientId = 'ewms-'.bin2hex(random_bytes(8));
        $clientSecret = bin2hex(random_bytes(32));

        $this->info('Add these to .env (or your deployment platform\'s secrets), then restart the app:');
        $this->newLine();
        $this->line("MCP_OAUTH_CLIENT_ID={$clientId}");
        $this->line("MCP_OAUTH_CLIENT_SECRET={$clientSecret}");
        $this->newLine();
        $this->line('MCP_OAUTH_REDIRECT_URI must exactly match the callback URL the connecting client (e.g. ChatGPT) shows you on its own setup screen.');

        return self::SUCCESS;
    }
}
