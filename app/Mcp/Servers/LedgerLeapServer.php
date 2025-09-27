<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateLedgerTool;
use App\Mcp\Tools\GetLedgerDefinesTool;
use App\Mcp\Tools\SearchLedgersTool;
use Laravel\Mcp\Server;

class LedgerLeapServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Ledger Leap Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        This server provides tools to interact with the LedgerLeap application.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetLedgerDefinesTool::class,
        SearchLedgersTool::class,
        CreateLedgerTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
