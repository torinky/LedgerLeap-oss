<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\BootstrapClientSkillsPrompt;
use App\Mcp\Resources\BootstrapClientResource;
use App\Mcp\Tools\ClaimWorkflowTaskTool;
use App\Mcp\Tools\CreateLedgerTool;
use App\Mcp\Tools\ExecuteApprovalTool;
use App\Mcp\Tools\GetActivityLogTool;
use App\Mcp\Tools\GetClientBootstrapManifestTool;
use App\Mcp\Tools\GetFoldersTool;
use App\Mcp\Tools\GetFolderStatsTool;
use App\Mcp\Tools\GetLedgerDefinesTool;
use App\Mcp\Tools\GetLedgerDetailTool;
use App\Mcp\Tools\GetLedgerStatsTool;
use App\Mcp\Tools\GetPendingApprovalsTool;
use App\Mcp\Tools\GetSearchTermsTool;
use App\Mcp\Tools\GetRelatedLedgersTool;
use App\Mcp\Tools\GetTagsTool;
use App\Mcp\Tools\GetUserActivityStatsTool;
use App\Mcp\Tools\GetWorkflowHistoryTool;
use App\Mcp\Tools\SearchLedgersTool;
use App\Mcp\Tools\UpdateLedgerTool;
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
        You are an assistant for the LedgerLeap ledger management system.

        When using tools that return responses with `__summary__`,
        include that summary at the beginning of your response.

        When displaying lists of objects that contain `__display_fields__`,
        present the information in a user-friendly format:
        - Use the Japanese field names from `__display_fields__`
        - Present data in bullet points or tables for readability
        - Focus on the most relevant information for the user's query

        Prefer compact list→detail responses and avoid repeating raw field keys when
        `__display_fields__` already provides user-facing labels.

        Always provide context-aware, helpful responses in Japanese when interacting with Japanese users.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetClientBootstrapManifestTool::class,
        GetFoldersTool::class,
        GetLedgerDefinesTool::class,
        GetLedgerDetailTool::class,
        GetRelatedLedgersTool::class,
        GetSearchTermsTool::class,
        GetTagsTool::class,
        SearchLedgersTool::class,
        CreateLedgerTool::class,
        UpdateLedgerTool::class,
        GetPendingApprovalsTool::class,
        ExecuteApprovalTool::class,
        GetWorkflowHistoryTool::class,
        ClaimWorkflowTaskTool::class,
        GetActivityLogTool::class,
        GetLedgerStatsTool::class,
        GetUserActivityStatsTool::class,
        GetFolderStatsTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        BootstrapClientResource::class,
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        BootstrapClientSkillsPrompt::class,
    ];
}
