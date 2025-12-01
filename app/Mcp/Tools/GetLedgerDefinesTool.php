<?php

namespace App\Mcp\Tools;

use App\Http\Resources\LedgerDefineResource;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\LedgerDefine;
use App\Repositories\WritableFolderRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetLedgerDefinesTool extends Tool
{
    use AuthenticatedMcpTool;

    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get a list of all ledger definitions.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, WritableFolderRepository $folderRepository): Response
    {
        // 認証チェック
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user; // エラーレスポンスをそのまま返す
        }

        // ユーザーがアクセス可能なフォルダに属する台帳定義のみを取得
        $readableFolderIds = $folderRepository->getReadableFolderIds($user);

        $ledgerDefines = LedgerDefine::whereHas('folder', function ($query) use ($readableFolderIds) {
            $query->whereIn('id', $readableFolderIds);
        })->get();

        $resource = LedgerDefineResource::collection($ledgerDefines);

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
            'include_trashed' => $schema->boolean()
                ->description('Include trashed ledger defines in the response')
                ->default(false),
            'folder_id' => $schema->integer()
                ->description('Optional folder id to filter ledger defines')
                ->nullable(),
        ];
    }
}
