# MCP connector

EWMS exposes a read-only [Model Context Protocol](https://modelcontextprotocol.io)
server so the CEO (or anyone else later granted `mcp.manage` through
/admin/permissions) can connect an AI — Claude, ChatGPT, or any other MCP
client — and ask it to pull company numbers and summarize them.

## What it can see

Every tool is aggregates and counts only — never a per-employee row. There is
no tool that returns an individual's salary, a single payslip, or a named
person's personal leave record. See `App\Services\Mcp\McpToolRegistry` for the
exact list; broadly: task/ticket counts, department performance, HR
headcount, leave request counts, company-wide payroll totals for one period,
and GA4/Search Console traffic totals.

A token is scoped to its owner's own EWMS permissions, re-checked on every
call — if a permission is later revoked through /admin/permissions, every
token that user holds loses access to the matching tool immediately, with no
separate token-level configuration needed.

## Issuing a token

CEO/Administrator (or another role granted `mcp.manage`) → **MCP Connector**
in the sidebar → name the token → **Generate token**. The plaintext is shown
once, in an on-screen banner, and never stored — only its SHA-256 hash is
(`App\Models\McpToken`). Losing it means revoking and issuing a new one.

## Connecting a client

- **Endpoint**: `https://<your-domain>/api/mcp`
- **Auth**: the token as a Bearer token (`Authorization: Bearer <token>`) —
  most connector UIs call this field "API key."
- **Protocol**: JSON-RPC 2.0 over a single POST endpoint (`initialize`,
  `tools/list`, `tools/call`) — the Streamable HTTP transport, stateless (no
  `Mcp-Session-Id` bookkeeping, which the spec makes optional for a server
  with no per-session state to track).

### Clients that require OAuth (e.g. ChatGPT)

Some connector UIs — ChatGPT's, notably — only offer "No Auth" or full OAuth
for a remote MCP server, with no plain API-key field. For those, EWMS runs a
minimal OAuth 2.0 authorization-code layer (`App\Http\Controllers\Mcp\McpOAuthController`)
in front of the same token system above: approving the flow (via a normal
EWMS login) just issues a regular `McpToken` behind the scenes, so it shows
up and can be revoked at **MCP Connector** exactly like a manually-generated
one.

Each person registers their **own** client — there's no single shared one.
This matters because the AI itself mints a fresh, unique redirect URL per
connector instance, so two people each connecting "ChatGPT" need two
different registrations.

**Setup, self-service, at MCP Connector → Connect via OAuth:**

1. In ChatGPT (or Claude, or any OAuth-only MCP client), start adding EWMS as
   a connector. It'll show you a redirect/callback URL — something like
   `https://chatgpt.com/connector/oauth/<id>`.
2. Back in EWMS, give the connector a name (e.g. "ChatGPT") and paste that
   exact URL into **Redirect URL**, then **Register**. You're shown a Client
   ID and Client Secret once — copy both now.
3. Paste into the client's connector form:

   | Field | Value |
   |---|---|
   | Auth URL | shown on the page (`https://<your-domain>/oauth/authorize`) |
   | Token URL | shown on the page (`https://<your-domain>/api/oauth/token`) |
   | Client ID | from step 2 |
   | Client Secret | from step 2 |
   | Scope | `mcp` (optional — not enforced) |

Revoking a registered connector (trash icon next to it) also revokes any
access token it issued — the same "disconnect this app" behavior a real
OAuth provider gives you. The interactive `/oauth/authorize` step requires
the approving user to already be logged into EWMS and hold `mcp.manage`;
PKCE is supported and used automatically if the client sends a
`code_challenge`.

## Adding a tool

Add an entry to the array in `McpToolRegistry::tools()`: a name, description,
the EWMS permission that gates it, a JSON Schema `inputSchema`, and a
handler closure returning a plain array (encoded as the tool result's text
content). Throw `McpToolException` for anything the caller should see as a
tool-level error rather than a crash — it's caught and turned into an
`isError` content block, never a 500.
