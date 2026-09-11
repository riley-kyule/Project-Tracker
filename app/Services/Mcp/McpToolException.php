<?php

namespace App\Services\Mcp;

use RuntimeException;

/** Caught by the MCP controller and turned into a tool-result error content block, never a 500. */
class McpToolException extends RuntimeException {}
