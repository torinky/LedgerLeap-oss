<?php

namespace App\Mcp\Tools;

use App\Http\Resources\Api\V1\LedgerDetailResource;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Services\LedgerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetLedgerDetailTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Get the latest detail for a single ledger before updating it.

        Standard workflow:
        1. Use SearchLedgersTool to identify the target ledger.
        2. Use GetLedgerDetailTool to confirm the latest content, workflow state, and editability.
        3. Use GetLedgerDefinesTool to confirm column IDs before building a content patch.
        4. Use UpdateLedgerTool with dry_run=true when you need a change preview.
        5. Use UpdateLedgerTool to apply the final patch.

        Use this after search results identify a target record and before building a content patch.
        The response includes the latest content, column definitions, workflow state, and editability.
    MARKDOWN;

    public function __construct(
        protected LedgerService $ledgerService,
    ) {
    }

    public function handle(Request $request): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        $ledgerId = (int) $request->get('ledger_id');
        if (! $ledgerId) {
            return Response::error(trans('ledger.error.ledger_id_required'));
        }

        $ledger = Ledger::query()->find($ledgerId);
        if (! $ledger) {
            return Response::error(trans('ledger.error.ledger_not_found'));
        }

        $ledger = $this->ledgerService->getLedgerForApi($ledger);
        $folder = $ledger->define?->folder;

        if (! $folder) {
            return $this->permissionError(trans('ledger.access_and_permissions.no_permission'));
        }

        $permissionCheck = $this->checkFolderPermissionOrError($user, $folder, 'READ');
        if ($permissionCheck instanceof Response) {
            return $this->permissionError(trans('ledger.access_and_permissions.no_permission'));
        }

        $resource = (new LedgerDetailResource($ledger))->resolve();
        $format = $request->get('format', 'summary');

        if ($format === 'raw') {
            return Response::json([
                'ledger' => $resource,
            ]);
        }

        return Response::json([
            'ledger' => $resource,
            '__summary__' => trans('ledger.mcp.detail_summary', [
                'title' => $this->extractLedgerTitle($ledger),
                'status' => $ledger->status?->label() ?? trans('ledger.empty'),
            ]),
            '__display_fields__' => [
                'title' => trans('ledger.field.title'),
                'ledger_type' => trans('ledger.ledger_define'),
                'folder' => trans('ledger.field.folder'),
                'status' => trans('ledger.field.status'),
                'updated_at' => trans('ledger.field.updated_at'),
            ],
            'summary' => [
                'title' => $this->extractLedgerTitle($ledger),
                'ledger_type' => $ledger->define?->title,
                'folder' => $resource['folder']['path'] ?? $resource['folder']['name'] ?? null,
                'status' => $ledger->status?->label(),
                'updated_at' => $ledger->updated_at,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ledger_id' => $schema->integer('The ID of the ledger to inspect before updating.')->required(),
            'format' => $schema->string('Response format: "summary" (default) or "raw".')
                ->enum(['raw', 'summary'])
                ->default('summary'),
        ];
    }

    private function extractLedgerTitle(Ledger $ledger): string
    {
        $fallbackTitle = $ledger->define?->title ?? trans('ledger.field.title');
        $firstColumnId = $this->extractFirstColumnId($ledger);
        $titleValue = $firstColumnId !== null ? ($ledger->content[$firstColumnId] ?? null) : null;

        if (is_string($titleValue)) {
            $title = trim($titleValue);
            if ($title !== '') {
                return $title;
            }
        }

        return $fallbackTitle;
    }

    private function extractFirstColumnId(Ledger $ledger): ?int
    {
        $firstColumn = $ledger->define->column_define[0] ?? null;

        if (is_object($firstColumn) && isset($firstColumn->id)) {
            return (int) $firstColumn->id;
        }

        if (is_array($firstColumn) && isset($firstColumn['id'])) {
            return (int) $firstColumn['id'];
        }

        return null;
    }
}
