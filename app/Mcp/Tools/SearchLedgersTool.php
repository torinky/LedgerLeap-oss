<?php

namespace App\Mcp\Tools;

use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use App\Services\LedgerService;
use Illuminate\JsonSchema\JsonSchema;
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
    public function handle(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return Response::error('Unauthorized', 401);
        }

        $parameters = $request->toArray();

        $results = $this->ledgerService->searchLedgersForApi(
            user: $user,
            q: $parameters['q'] ?? null,
            tags: isset($parameters['tags']) ? explode(',', $parameters['tags']) : null,
            folderId: $parameters['folder_id'] ?? null,
            ledgerDefineId: $parameters['ledger_define_id'] ?? null,
            excludeQ: $parameters['exclude_q'] ?? null,
            excludeTags: isset($parameters['exclude_tags']) ? explode(',', $parameters['exclude_tags']) : null,
            mode: $parameters['mode'] ?? 'search',
            limit: $parameters['limit'] ?? null,
            offset: $parameters['offset'] ?? null,
        );

        return Response::json($results);
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
