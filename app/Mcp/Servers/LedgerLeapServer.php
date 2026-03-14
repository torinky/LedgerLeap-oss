<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\BootstrapClientSkillsPrompt;
use App\Mcp\Resources\BootstrapClientResource;
use App\Mcp\Tools\ClaimWorkflowTaskTool;
use App\Mcp\Tools\CreateLedgerTool;
use App\Mcp\Tools\ExecuteApprovalTool;
use App\Mcp\Tools\GetActivityLogTool;
use App\Mcp\Tools\GetClientBootstrapManifestTool;
use App\Mcp\Tools\GetFolderStatsTool;
use App\Mcp\Tools\GetLedgerDefinesTool;
use App\Mcp\Tools\GetLedgerDetailTool;
use App\Mcp\Tools\GetLedgerStatsTool;
use App\Mcp\Tools\GetPendingApprovalsTool;
use App\Mcp\Tools\GetRelatedLedgersTool;
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
        
        For search queries like "show me yesterday's reports" or "昨日作成した日報を見せて":
        1. Use SearchLedgers with appropriate date filters (created_from, created_to)
        2. Set format="summary" for better formatted responses
        3. Include creator_id filter when the user refers to "my" or "私の" documents
        
        For statistics and analytics queries:
        1. Use GetLedgerStats for ledger creation statistics by period (today, this_week, this_month, etc.)
        2. Use GetUserActivityStats for user activity analysis and peak hours
        3. Use GetFolderStats for folder-level statistics and recent activity
        4. All stats tools support format="summary" for human-readable responses
        
        Period options available:
        - today, yesterday
        - this_week, last_week
        - this_month, last_month
        - this_quarter, last_quarter
        - this_year, last_year
        - last_7_days, last_30_days, last_90_days
        
        Always provide context-aware, helpful responses in Japanese when interacting with Japanese users.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetClientBootstrapManifestTool::class,
        GetLedgerDefinesTool::class,
        GetLedgerDetailTool::class,
        GetRelatedLedgersTool::class,
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
