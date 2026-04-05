<?php

namespace App\Mcp\Tools;

use App\Http\Resources\LedgerDefineResource;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Repositories\WritableFolderRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        Get accessible ledger definitions for lookup-first search flows.

        Use this tool when you need to:
        - find a ledger definition ID from a partial title fragment
        - limit lookup to a specific folder subtree before calling SearchLedgersTool
        - confirm which ledger definitions are available to the current user
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
        $q = trim((string) $request->get('q', ''));
        $folderId = $request->get('folder_id');

        $query = LedgerDefine::query()
            ->whereHas('folder', function ($builder) use ($readableFolderIds) {
                $builder->whereIn('id', $readableFolderIds);
            });

        if (! empty($folderId)) {
            $lookupFolderIds = Folder::descendantsAndSelf((int) $folderId)
                ->pluck('id')
                ->intersect($readableFolderIds)
                ->values();

            $query->whereIn('folder_id', $lookupFolderIds);
        }

        if ($q !== '') {
            $query->where('title', 'like', '%'.$q.'%');
        }

        $ledgerDefines = $query->orderBy('title')->get();

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
            'q' => $schema->string()
                ->description('Partial ledger definition title to look up before searching ledgers')
                ->nullable(),
            'include_trashed' => $schema->boolean()
                ->description('Include trashed ledger defines in the response')
                ->default(false),
            'folder_id' => $schema->integer()
                ->description('Optional folder id to filter ledger defines')
                ->nullable(),
        ];
    }
}
