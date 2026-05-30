<?php

namespace App\Mcp\Tools;

use App\Http\Resources\LedgerResource;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Folder;
use App\Services\LedgerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateLedgerTool extends Tool
{
    use AuthenticatedMcpTool;

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
        // 認証チェック
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user; // エラーレスポンスをそのまま返す
        }

        // フォルダの存在チェック
        $folderId = $request->get('folder_id');
        $folder = Folder::find($folderId);
        if (! $folder) {
            return Response::error("Folder not found: {$folderId}");
        }

        // フォルダに対する書き込み権限チェック
        $permissionCheck = $this->checkFolderPermissionOrError($user, $folder, 'WRITE');
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        try {
            $ledger = $ledgerService->createLedger([
                'ledger_define_id' => $request->get('ledger_define_id'),
                'content' => json_decode($request->get('content'), true),
                'tags' => $request->get('tags', []),
            ]);

            $resource = new LedgerResource($ledger);

            return Response::text($resource->toJson(JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error("Failed to create ledger: {$e->getMessage()}");
        }
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
