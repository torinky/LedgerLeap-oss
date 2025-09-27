<?php

namespace App\Mcp\Tools;

use App\Services\LedgerService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SearchLedgersTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search for ledgers based on various criteria.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, LedgerService $ledgerService): Response
    {
        $result = $ledgerService->searchLedgersForApi(
            $request->arguments('q'),
            $request->arguments('tags'),
            $request->arguments('folder_id'),
            $request->arguments('ledger_define_id'),
            $request->arguments('exclude_q'),
            $request->arguments('exclude_tags'),
            $request->arguments('mode', 'search'),
            $request->arguments('limit', 10),
            $request->arguments('offset', 0)
        );

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string('The search keyword for full-text search.'),
            'tags' => $schema->string('Comma-separated tag names to filter by (AND condition).'),
            'folder_id' => $schema->integer('The folder ID to recursively search within.'),
            'ledger_define_id' => $schema->integer('The ledger definition ID to filter by.'),
            'exclude_q' => $schema->string('Keywords to exclude from the results.'),
            'exclude_tags' => $schema->string('Comma-separated tag names to exclude.'),
            'mode' => $schema->string('The search mode.')->enum(['search', 'count'])->default('search'),
            'limit' => $schema->integer('The maximum number of items to return.'),
            'offset' => $schema->integer('The number of items to skip for pagination.'),
        ];
    }
}
