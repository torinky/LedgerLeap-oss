<?php

namespace App\Mcp\Tools;

use App\Http\Resources\LedgerResource;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateLedgerTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a new ledger.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, LedgerService $ledgerService): Response
    {
        // As a provisional measure, the creator is the first user of the tenant.
        $creator = User::first();
        if (!$creator) {
            return Response::error('No users found to assign as creator.');
        }

        $ledger = $ledgerService->createLedger(
            $request->arguments('ledger_define_id'),
            $request->arguments('folder_id'),
            json_decode($request->arguments('content'), true),
            $request->arguments('tags', []),
            $creator
        );

        $resource = new LedgerResource($ledger);

        return Response::text($resource->toJson(JSON_PRETTY_PRINT));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ledger_define_id' => $schema->integer('The ID of the ledger definition.')->required(),
            'folder_id' => $schema->integer('The ID of the folder to create the ledger in.')->required(),
            'content' => $schema->string('The JSON string content of the ledger, with column IDs as keys.')->required(),
            'tags' => $schema->array('An array of tag names.')->items($schema->string()),
        ];
    }
}
