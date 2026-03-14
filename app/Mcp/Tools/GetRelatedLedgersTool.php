<?php

namespace App\Mcp\Tools;

use App\Mcp\Helpers\TranslationHelper;
use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Ledger;
use App\Services\Ledger\RelatedLedgerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetRelatedLedgersTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Get related ledgers for a specific source ledger.

        Standard workflow:
        1. Use SearchLedgersTool to identify the source ledger.
        2. Use GetLedgerDetailTool if you need to confirm the latest content before tracing related records.
        3. Use GetRelatedLedgersTool to retrieve ledgers related by identifier
           matches, semantic similarity, or both.
        4. Use GetLedgerDetailTool again for any related ledger you want to inspect in detail.

        This tool is useful when a user wants to investigate records related to the one currently being viewed.
    MARKDOWN;

    public function __construct(
        private RelatedLedgerService $relatedLedgerService,
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

        $includeIdentifier = filter_var(
            $request->get('include_identifier', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );
        $includeSemantic = filter_var(
            $request->get('include_semantic', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );
        $includeIdentifier = $includeIdentifier ?? true;
        $includeSemantic = $includeSemantic ?? true;
        $format = $request->get('format', 'summary');
        $limit = max(1, min((int) $request->get('limit', 20), 50));

        if (! $includeIdentifier && ! $includeSemantic) {
            return Response::error(trans('ledger.mcp.related_axis_required'));
        }

        $ledger = Ledger::query()
            ->with(['define', 'define.folder', 'define.folder.ancestors'])
            ->find($ledgerId);

        if (! $ledger) {
            return Response::error(trans('ledger.error.ledger_not_found'));
        }

        $folder = $ledger->define?->folder;
        if (! $folder) {
            return $this->permissionError(trans('ledger.access_and_permissions.no_permission'));
        }

        $permissionCheck = $this->checkFolderPermissionOrError($user, $folder, 'READ');
        if ($permissionCheck instanceof Response) {
            return $this->permissionError(trans('ledger.access_and_permissions.no_permission'));
        }

        $resolved = $this->relatedLedgerService->resolve(
            ledger: $ledger,
            user: $user,
            includeIdentifier: $includeIdentifier,
            includeSemantic: $includeSemantic,
            limit: $limit,
        );

        $response = [
            'source_ledger' => [
                'id' => $ledger->id,
                'title' => $this->extractLedgerTitle($ledger),
                'ledger_type' => $ledger->define?->title,
                'status' => $ledger->status?->value,
                'status_label' => $ledger->status?->label(),
                'folder' => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'path' => $this->folderPath($folder),
                ],
            ],
            'related_ledgers' => array_map(
                fn (array $item) => $this->formatRelatedLedgerItem($item),
                $resolved['merged'],
            ),
            'total_count' => $resolved['total_count'],
            'returned_count' => $resolved['returned_count'],
            'identifier_count' => $resolved['identifier_count'],
            'semantic_count' => $resolved['semantic_count'],
            'filters' => [
                'include_identifier' => $includeIdentifier,
                'include_semantic' => $includeSemantic,
            ],
            'warnings' => $this->buildWarnings($resolved, $includeIdentifier, $includeSemantic),
        ];

        if ($format === 'raw') {
            return Response::json($response);
        }

        return Response::json(TranslationHelper::buildMcpResponse(
            trans('ledger.mcp.related_summary', [
                'title' => $this->extractLedgerTitle($ledger),
                'count' => $resolved['total_count'],
            ]),
            [
                'title' => trans('ledger.field.title'),
                'ledger_type' => trans('ledger.ledger_define'),
                'folder' => trans('ledger.field.folder'),
                'status' => trans('ledger.field.status'),
                'updated_at' => trans('ledger.field.updated_at'),
                'reason_label' => trans('ledger.related.reason_column_label'),
                'score_label' => trans('ledger.related.reason_semantic'),
            ],
            $response
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ledger_id' => $schema->integer('The source ledger ID to trace related ledgers from.')
                ->required(),
            'include_identifier' => $schema->boolean('Whether to include identifier-based related ledgers.')
                ->default(true),
            'include_semantic' => $schema->boolean('Whether to include semantic-similarity related ledgers.')
                ->default(true),
            'limit' => $schema->integer('Maximum number of related ledgers to return. Default: 20, max: 50.')
                ->default(20),
            'format' => $schema->string('Response format: "summary" (default) or "raw".')
                ->enum(['summary', 'raw'])
                ->default('summary'),
        ];
    }

    /**
     * @param  array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}  $item
     * @return array<string, mixed>
     */
    private function formatRelatedLedgerItem(array $item): array
    {
        /** @var Ledger $ledger */
        $ledger = $item['ledger'];
        $folder = $ledger->define?->folder;

        return [
            'id' => $ledger->id,
            'title' => $this->extractLedgerTitle($ledger),
            'ledger_type' => $ledger->define?->title,
            'folder' => [
                'id' => $folder?->id,
                'name' => $folder?->name,
                'path' => $folder ? $this->folderPath($folder) : null,
            ],
            'status' => $ledger->status?->value,
            'status_label' => $ledger->status?->label(),
            'updated_at' => $ledger->updated_at,
            'reason' => $item['reason'],
            'reason_label' => $this->translateReason($item['reason']),
            'score' => $item['score'],
            'score_label' => $item['score'] !== null ? number_format($item['score'] * 100, 1).'%' : null,
            'matched_keys' => $item['matched_keys'],
            'matched_keys_label' => array_map(function (array $matchedKey) {
                return trans('ledger.related.identifier_key_with_source', [
                    'value' => $matchedKey['value'],
                    'source' => trans('ledger.related.identifier_source_'.$matchedKey['source']),
                ]);
            }, $item['matched_keys']),
            'link' => $this->ledgerLink($ledger),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildWarnings(array $resolved, bool $includeIdentifier, bool $includeSemantic): array
    {
        $warnings = [];

        if ($includeIdentifier && ! $resolved['has_auto_number']) {
            $warnings[] = trans('ledger.related.identifier_unavailable_tooltip');
        }

        if ($includeSemantic && ! $resolved['rag_available']) {
            $warnings[] = trans('ledger.related.rag_unavailable_tooltip');
        }

        if ($resolved['last_error'] !== '') {
            $warnings[] = $resolved['last_error'];
        }

        return array_values(array_unique($warnings));
    }

    private function translateReason(string $reason): string
    {
        return trans('ledger.related.reason_'.$reason);
    }

    private function extractLedgerTitle(Ledger $ledger): string
    {
        $fallbackTitle = $ledger->define?->title ?? trans('ledger.field.title');
        $firstColumn = $ledger->define?->column_define[0] ?? null;
        $firstColumnId = is_object($firstColumn) ? ($firstColumn->id ?? null) : ($firstColumn['id'] ?? null);
        $titleValue = $firstColumnId !== null ? ($ledger->content[$firstColumnId] ?? null) : null;

        if (is_string($titleValue) && trim($titleValue) !== '') {
            return trim($titleValue);
        }

        return $fallbackTitle;
    }

    private function folderPath(object $folder): ?string
    {
        if (! method_exists($folder, 'relationLoaded') || ! $folder->relationLoaded('ancestors')) {
            return $folder->name ?? null;
        }

        return '/'.$folder->ancestors->pluck('name')->push($folder->name)->implode('/');
    }

    private function ledgerLink(Ledger $ledger): ?string
    {
        if (! isset($ledger->tenant_id, $ledger->id)) {
            return null;
        }

        $baseUrl = rtrim(config('ledgerleap.auto_links.base_url'), '/');

        return "{$baseUrl}/{$ledger->tenant_id}/ledger/{$ledger->id}";
    }
}
