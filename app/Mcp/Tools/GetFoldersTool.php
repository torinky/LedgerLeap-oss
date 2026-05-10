<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Folder;
use App\Repositories\WritableFolderRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetFoldersTool extends Tool
{
    use AuthenticatedMcpTool;

    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get accessible folders for lookup-first search flows.

        Use this tool when you need to:
        - find a folder ID from a partial folder name fragment
        - avoid browsing large folder lists by narrowing candidates first
        - confirm which folders the current user can access
MARKDOWN;

    public function handle(Request $request, WritableFolderRepository $folderRepository): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        $q = trim((string) $request->get('q', ''));
        $readableFolderIds = $folderRepository->getReadableFolderIds($user);

        $folders = Folder::query()
            ->with('ancestors')
            ->whereIn('id', $readableFolderIds)
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.$q.'%'))
            ->orderBy('title')
            ->get()
            ->map(function (Folder $folder): array {
                $ancestorTitles = $folder->relationLoaded('ancestors')
                    ? $folder->ancestors->pluck('title')->all()
                    : [];

                return [
                    'id' => $folder->id,
                    'title' => $folder->title,
                    'path' => '/'.implode('/', array_merge($ancestorTitles, [$folder->title])),
                    'parent_id' => $folder->parent_id,
                ];
            })
            ->values()
            ->all();

        return Response::json([
            'q' => $q,
            'count' => count($folders),
            'folders' => $folders,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string('Partial folder name to search before resolving folder_id')->nullable(),
        ];
    }
}
