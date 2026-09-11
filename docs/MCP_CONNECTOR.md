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

## Adding a tool

Add an entry to the array in `McpToolRegistry::tools()`: a name, description,
the EWMS permission that gates it, a JSON Schema `inputSchema`, and a
handler closure returning a plain array (encoded as the tool result's text
content). Throw `McpToolException` for anything the caller should see as a
tool-level error rather than a crash — it's caught and turned into an
`isError` content block, never a 500.
