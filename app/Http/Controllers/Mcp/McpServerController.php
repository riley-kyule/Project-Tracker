<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Mcp\McpToolException;
use App\Services\Mcp\McpToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;

/**
 * A single JSON-RPC 2.0 endpoint implementing the Model Context Protocol's
 * "tools" surface (initialize / tools/list / tools/call) — what Claude or
 * ChatGPT talks to once someone adds this as a connector. Stateless by
 * design: no Mcp-Session-Id bookkeeping, which the spec makes optional for
 * a server with no per-request state to track — every call here is
 * self-contained (most are read-only queries; a couple, per
 * McpToolRegistry, create a task or ticket).
 */
class McpServerController extends Controller
{
    public function __construct(private McpToolRegistry $tools) {}

    public function handle(Request $request): JsonResponse
    {
        $body = (array) $request->json()->all();
        $id = $body['id'] ?? null;
        $method = $body['method'] ?? null;

        // A JSON-RPC notification (no "id") gets no response body at all —
        // notifications/initialized is the one a client sends after initialize.
        if ($id === null && is_string($method) && str_starts_with($method, 'notifications/')) {
            return response()->json(null, 202);
        }

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => '2025-06-18',
                'serverInfo' => ['name' => 'ewms', 'version' => '1.0.0'],
                'capabilities' => ['tools' => new stdClass],
            ]),
            'tools/list' => $this->result($id, ['tools' => $this->tools->definitions()]),
            'tools/call' => $this->callTool($request, $id, (array) ($body['params'] ?? [])),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    private function callTool(Request $request, mixed $id, array $params): JsonResponse
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = (array) ($params['arguments'] ?? []);

        try {
            $data = $this->tools->call($request->user(), $name, $arguments);
            $this->audit($request, $name, $arguments, 'ok');

            return $this->result($id, ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT)]]]);
        } catch (McpToolException $e) {
            $this->audit($request, $name, $arguments, 'error', $e->getMessage());

            return $this->result($id, ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true]);
        }
    }

    /**
     * Every tool call — reads included — lands in the audit log against the
     * token, so there's a record of what an AI assistant looked at, not only
     * what it changed. Arguments are names, dates and short text; string
     * values are clipped so a long task description can't bloat the log.
     */
    private function audit(Request $request, string $tool, array $arguments, string $outcome, ?string $error = null): void
    {
        $clipped = array_map(fn ($value) => is_string($value) ? Str::limit($value, 200) : $value, $arguments);

        AuditLogger::log($request->attributes->get('mcp_token'), 'mcp_tool_called', [], array_filter([
            'tool' => $tool,
            'arguments' => $clipped ?: null,
            'outcome' => $outcome,
            'error' => $error,
        ], fn ($value) => $value !== null));
    }

    private function result(mixed $id, array $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function error(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }
}
