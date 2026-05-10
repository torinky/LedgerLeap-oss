<?php

namespace App\Mcp\Tools;

use App\Http\Resources\Api\V1\LedgerDetailResource;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Services\LedgerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateLedgerTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Update an existing ledger by applying a partial `content_patch`.

        Contract notes:
        - Updates only the column IDs present in `content_patch`
        - Supports an optional `comment` and `dry_run=true` preview
        - `tag_operation` / `tag_values` are currently rejected as unsupported
        - Approved ledgers cannot be updated by this initial MCP contract
    MARKDOWN;

    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

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

        $permissionCheck = $this->checkFolderPermissionOrError($user, $folder, 'WRITE');
        if ($permissionCheck instanceof Response) {
            return $this->permissionError(trans('ledger.access_and_permissions.no_permission'));
        }

        if ($ledger->isLocked()) {
            return Response::error(trans('ledger.mcp.approved_locked'));
        }

        if ($this->hasTagUpdateInput($request->toArray())) {
            return Response::error(trans('ledger.mcp.tag_updates_not_supported'));
        }

        try {
            $data = [
                'content_patch' => $this->parseContentPatch($request->get('content_patch')),
                'comment' => $request->get('comment'),
            ];

            $preview = $this->ledgerService->previewLedgerUpdateForApi($ledger, $data);
            $format = $request->get('format', 'summary');
            $dryRun = filter_var($request->get('dry_run', false), FILTER_VALIDATE_BOOL);

            if ($dryRun) {
                return Response::json($this->buildPreviewResponse($preview, $format));
            }

            $updatedLedger = $this->ledgerService->updateLedgerForApi($user, $ledger, $data);

            return Response::json($this->buildUpdatedResponse($updatedLedger, $preview, $format));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return Response::error($message);
        } catch (\JsonException) {
            return Response::error(trans('ledger.mcp.invalid_content_patch_json'));
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (\Throwable $e) {
            return Response::error(trans('ledger.error.occurred_with_message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ledger_id' => $schema->integer('The ID of the ledger to update.')->required(),
            'content_patch' => $schema->string(
                'A JSON object with column definition IDs as keys. Example: {"0":"会議室B"}.'
            )->required(),
            'comment' => $schema->string('Optional comment describing the reason for the update.'),
            'tag_operation' => $schema->string(
                'Optional tag update operation. Accepted for forward-compatible requests, '
                .'but the initial MCP update contract rejects tag updates.'
            )->enum(['add', 'remove', 'replace']),
            'tag_values' => $schema->array(
                'Optional tag names for tag updates. Accepted for forward-compatible requests, '
                .'but the initial MCP update contract rejects tag updates.'
            )->items($schema->string()),
            'dry_run' => $schema->boolean(
                'When true, preview changed columns and workflow impact without saving.'
            )->default(false),
            'format' => $schema->string('Response format: "summary" (default) or "raw".')
                ->enum(['raw', 'summary'])
                ->default('summary'),
        ];
    }

    /**
     * @return array<string|int, mixed>
     *
     * @throws \JsonException
     */
    private function parseContentPatch(mixed $contentPatch): array
    {
        if (is_array($contentPatch)) {
            return $contentPatch;
        }

        if (! is_string($contentPatch) || trim($contentPatch) === '') {
            throw new \InvalidArgumentException(trans('ledger.mcp.content_patch_required'));
        }

        $decoded = json_decode($contentPatch, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException(trans('ledger.mcp.invalid_content_patch_json'));
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function hasTagUpdateInput(array $parameters): bool
    {
        return array_key_exists('tag_operation', $parameters)
            || array_key_exists('tag_values', $parameters);
    }

    /**
     * @param  array{
     *     ledger: Ledger,
     *     changed_columns: array<int, array{column_id:int, column_name:string, before:mixed, after:mixed}>,
     *     previous_status: string|null,
     *     returns_to_draft_on_save: bool,
     *     comment: mixed,
     *     new_content: array<string|int, mixed>
     * }  $preview
     * @return array<string, mixed>
     */
    private function buildPreviewResponse(array $preview, string $format): array
    {
        $ledger = $preview['ledger'];
        $resource = (new LedgerDetailResource($ledger))->resolve();
        $payload = [
            'dry_run' => true,
            'ledger' => $resource,
            'content_after_patch' => $preview['new_content'],
            'changed_columns' => $preview['changed_columns'],
            'meta' => [
                'previous_status' => $preview['previous_status'],
                'current_status' => $preview['previous_status'],
                'status_changed' => false,
                'returns_to_draft_on_save' => $preview['returns_to_draft_on_save'],
                'comment' => $preview['comment'],
            ],
        ];

        if ($format === 'raw') {
            return $payload;
        }

        $title = $this->extractLedgerTitle($ledger);
        $changeCount = count($preview['changed_columns']);

        return $payload + [
            '__summary__' => trans('ledger.mcp.preview_summary', [
                'title' => $title,
                'count' => $changeCount,
                'status' => $ledger->status?->label() ?? trans('ledger.empty'),
            ]),
            '__display_fields__' => $this->summaryDisplayFields(),
            'summary' => [
                'title' => $title,
                'ledger_type' => $ledger->define?->title,
                'status' => $ledger->status?->label(),
                'change_count' => $changeCount,
            ],
        ];
    }

    /**
     * @param  array{
     *     ledger: Ledger,
     *     changed_columns: array<int, array{column_id:int, column_name:string, before:mixed, after:mixed}>,
     *     previous_status: string|null,
     *     returns_to_draft_on_save: bool
     * }  $preview
     * @return array<string, mixed>
     */
    private function buildUpdatedResponse(Ledger $updatedLedger, array $preview, string $format): array
    {
        $resource = (new LedgerDetailResource($updatedLedger))->resolve();
        $currentStatus = $updatedLedger->status?->value;
        $meta = [
            'previous_status' => $preview['previous_status'],
            'current_status' => $currentStatus,
            'status_changed' => $preview['previous_status'] !== $currentStatus,
            'returned_to_draft' => $preview['returns_to_draft_on_save'] && $currentStatus === 'draft',
            'change_count' => count($preview['changed_columns']),
        ];

        $payload = [
            'dry_run' => false,
            'ledger' => $resource,
            'changed_columns' => $preview['changed_columns'],
            'meta' => $meta,
        ];

        if ($format === 'raw') {
            return $payload;
        }

        $title = $this->extractLedgerTitle($updatedLedger);

        return $payload + [
            '__summary__' => trans('ledger.mcp.updated_summary', [
                'title' => $title,
                'count' => count($preview['changed_columns']),
                'status' => $updatedLedger->status?->label() ?? trans('ledger.empty'),
            ]),
            '__display_fields__' => $this->summaryDisplayFields(),
            'summary' => [
                'title' => $title,
                'ledger_type' => $updatedLedger->define?->title,
                'status' => $updatedLedger->status?->label(),
                'updated_at' => $updatedLedger->updated_at,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function summaryDisplayFields(): array
    {
        return [
            'title' => trans('ledger.field.title'),
            'ledger_type' => trans('ledger.ledger_define'),
            'status' => trans('ledger.field.status'),
            'updated_at' => trans('ledger.field.updated_at'),
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
