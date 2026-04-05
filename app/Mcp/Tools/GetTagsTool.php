<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Tag;
use App\Repositories\WritableFolderRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetTagsTool extends Tool
{
    use AuthenticatedMcpTool;

    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get accessible tags for lookup-first search flows.

        Use this tool when you need to:
        - find a tag name from a partial fragment before calling SearchLedgersTool
        - avoid browsing large tag lists by narrowing candidates first
        - confirm which tags the current user can access
    MARKDOWN;

    public function handle(Request $request, WritableFolderRepository $folderRepository): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        $q = trim((string) $request->get('q', ''));
        $readableFolderIds = $folderRepository->getReadableFolderIds($user);

        $tags = Tag::query()
            ->with(['folder:id,title,parent_id', 'ledgerDefine:id,title,folder_id'])
            ->whereIn('folder_id', $readableFolderIds)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->get()
            ->map(function (Tag $tag): array {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'folder_id' => $tag->folder_id,
                    'ledger_define_id' => $tag->ledger_define_id,
                ];
            })
            ->values()
            ->all();

        return Response::json([
            'q' => $q,
            'count' => count($tags),
            'tags' => $tags,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string('Partial tag name to search before resolving exact tag names')->nullable(),
        ];
    }
}
