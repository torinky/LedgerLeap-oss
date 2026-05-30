<?php

namespace App\Mcp\Tools;

use App\Http\Resources\LedgerDefineResource;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Mcp\Traits\TruncatableResponse;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Repositories\WritableFolderRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetLedgerDefinesTool extends Tool
{
    use AuthenticatedMcpTool;
    use TruncatableResponse;

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
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        $readableFolderIds = $folderRepository->getReadableFolderIds($user);
        $q = trim((string) $request->get('q', ''));
        $folderId = $request->get('folder_id');
        $limit = min((int) $request->get('limit', 20), 100);
        $offset = max((int) $request->get('offset', 0), 0);
        $includeOptions = (bool) $request->get('include_options', false);

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

        $totalCount = $query->count();

        $ledgerDefines = $query
            ->orderBy('title')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $data = LedgerDefineResource::collection($ledgerDefines)->toArray(new HttpRequest);

        if (! $includeOptions) {
            foreach ($data as $defKey => $def) {
                $columns = $def['columns'] ?? null;
                if ($columns instanceof Collection) {
                    $data[$defKey]['columns'] = $columns->map(function ($col) {
                        if (is_array($col)) {
                            unset($col['options']);
                        }

                        return $col;
                    })->all();
                } elseif (is_array($columns)) {
                    foreach (array_keys($columns) as $colKey) {
                        if (isset($data[$defKey]['columns'][$colKey]['options'])) {
                            unset($data[$defKey]['columns'][$colKey]['options']);
                        }
                    }
                }
            }
        }

        $responseData = $this->truncateIfNeeded([
            'ledger_defines' => $data,
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return Response::json($responseData);
    }

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
            'limit' => $schema->integer('Maximum number of ledger definitions to return. Default: 20, Max: 100.')
                ->default(20),
            'offset' => $schema->integer('Number of ledger definitions to skip for pagination. Default: 0.')
                ->default(0),
            'include_options' => $schema->boolean('Whether to include column select options in the response. Default: false (compact output). Set to true when you need to see all select choices.')
                ->default(false),
        ];
    }
}
