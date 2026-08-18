<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddTool;
use App\Mcp\Tools\EchoTool;
use Laravel\Mcp\Server;

/**
 * Ürünün kendi kendini izlemesi (dogfooding), testler ve landing'deki
 * "try the checker" demosu için sabit davranışlı küçük bir MCP server.
 */
class FixtureServer extends Server
{
    protected string $name = 'MCP Monitor Demo';

    protected string $version = '1.0.0';

    protected string $instructions = 'Demo MCP server used as a probe target.';

    protected array $tools = [
        EchoTool::class,
        AddTool::class,
    ];
}
