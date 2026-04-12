<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SearchLedgersTool extends Tool
{
    use AuthenticatedMcpTool;

    private const ATTACHMENT_TEXT_PREVIEW_LIMIT = 500;

    private const ATTACHMENT_LINE_PREVIEW_LIMIT = 10;

    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search for ledgers by keyword, tags, date range, creator, folder, ledger type, or semantic relevance.
        `q` is synonym-aware, so natural-language search terms can be expanded when synonym data is available.

        Important for Japanese / multi-byte keywords:
        - `q`, `tags`, `exclude_q`, and `exclude_tags` accept Japanese and other multi-byte characters
        - `q` can be expanded with synonyms for business terms when synonym data is available
        - tag / folder / ledger definition fragments should be resolved first with lookup tools
          before calling this search tool

        Response format:
        - `summary` (default): display-oriented records with `__display_fields__`, `__summary__`,
          normalized `meta`, and a detail `link` when available
        - summary records also include `attachment_count` and per-attachment `attachment_id` /
          `filename` / `role` / `order` / `source` / `mime_type` / `delivery_mode` /
          `available_formats` / `routes` / `payloads`
        - `delivery_mode` is text-first for the initial envelope; `available_formats`
          advertises additional follow-up formats such as markdown / json / structured / visual
          when available
        - `routes.download` / `routes.inspector` provide tenant-safe download and drawer-open
          links for every resolved attachment
        - `payloads.text` / `payloads.structured` / `payloads.visual` keep a stable mode contract
          so clients can follow the same envelope for each attachment
        - `raw`: normalized `ledgers`, `meta`, and `total` for machine processing

        Key parameters:
        - `q`, `tags`, `exclude_q`, `exclude_tags`
        - `folder_id`, `ledger_define_id`, `creator_id`
        - `folder_id` / `ledger_define_id` accept lookup-first candidate ID arrays resolved by the
          folder / ledger lookup tools
        - `tags` remains exact in the search body; resolve tag fragments with the tag lookup tool first
        - `created_from`, `created_to`
        - `order_by`, including `semantic_score` for meaning-based ranking
        - `mode`, `limit`, `offset`, `include_content`, `content_preview_length`

        Contract notes:
        - `semantic_score` works best when `q` is a natural language sentence or question
        - `meta` includes related users, folders, and ledger definitions for interpreting search results
        - Use `include_content=false` or `mode='count'` when you need a lighter response
MARKDOWN;

    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    public function handle(Request $request): Response
    {
        // 認証チェック
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user; // エラーレスポンスをそのまま返す
        }

        $parameters = $request->toArray();

        // created_from と created_to を結合して created_between を作成
        if (isset($parameters['created_from']) && isset($parameters['created_to'])) {
            $parameters['created_between'] = $parameters['created_from'].','.$parameters['created_to'];
            unset($parameters['created_from']);
            unset($parameters['created_to']);
        }

        try {
            $results = $this->ledgerService->searchLedgersForApi(
                user: $user, // 認証済みユーザーを直接渡す
                params: $parameters,
            );
            \Log::info('[MCP Search Debug] searchLedgersForApi result: '.json_encode($results, JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            return Response::error("Search failed: {$e->getMessage()}");
        }

        $format = $parameters['format'] ?? 'summary';

        // rawフォーマットの場合は加工せずに返す
        if ($format === 'raw') {
            return Response::json($results);
        }

        $ledgers = $results['ledgers'];
        if ($ledgers instanceof Collection) {
            foreach ($ledgers as $ledger) {
                if ($ledger instanceof Model) {
                    $ledger->loadMissing([
                        'attachedFiles' => fn ($query) => $query->orderBy('column_id')->orderBy('id'),
                    ]);
                }
            }
        }

        // summary フォーマットの場合、表示用の加工を行う
        $includeContent = $parameters['include_content'] ?? true;
        $contentPreviewLength = $parameters['content_preview_length'] ?? 200;

        $ledgers = collect($results['ledgers'])
            ->map(function ($ledger) use ($results, $includeContent, $contentPreviewLength) {
                $meta = $results['meta'];
                $attachedFiles = $ledger instanceof Model ? ($ledger->getRelation('attachedFiles') ?? []) : [];
                $ledgerModel = $ledger instanceof Model ? $ledger : null;
                $ledger = (object) $ledger;

                $define = $meta['ledger_defines'][$ledger->ledger_define_id] ?? null;

                $folderPath = ($define && isset($meta['folders'][$define['folder_id']]))
                    ? $meta['folders'][$define['folder_id']]['path']
                    : trans('common.root_folder', [], 'ja');

                $statusValue = is_object($ledger->status) ? $ledger->status->value : $ledger->status;
                $statusDisplay = trans('ledger.workflow.status.'.$statusValue, [], 'ja');

                $updatedAtFormatted = Carbon::parse($ledger->updated_at)->format('Y年m月d日 H:i');

                $displayFields = [
                    'title' => $define['name'] ?? trans('common.unknown', [], 'ja'),
                    'folder' => $folderPath,
                    'creator' => $meta['users'][$ledger->creator_id]['name'] ?? trans('common.unknown', [], 'ja'),
                    'workflow_status' => $statusDisplay,
                    'updated_at' => $updatedAtFormatted,
                ];

                if (isset($ledger->tenant_id) && isset($ledger->id)) {
                    $baseUrl = rtrim(config('ledgerleap.auto_links.base_url'), '/');
                    $displayFields['link'] = "{$baseUrl}/{$ledger->tenant_id}/ledger/{$ledger->id}";
                }

                if (! $includeContent) {
                    $displayFields['content_preview'] = $this->generateContentPreview(
                        $ledger->content ?? [],
                        $define['column_define'] ?? [],
                        $contentPreviewLength
                    );
                }

                $ledger->__display_fields__ = $displayFields;

                $contentAttached = $ledger->content_attached ?? [];
                $ledger->attachments = $this->formatAttachments($contentAttached, $attachedFiles, $ledgerModel);
                $displayFields['attachment_count'] = count($ledger->attachments);
                $displayFields['attachment_summary'] = $this->describeAttachmentCount(
                    $displayFields['attachment_count']
                );
                $ledger->__display_fields__ = $displayFields;

                return $ledger;
            });

        $summary = trans_choice('messages.found_ledgers', $results['total'], ['count' => $results['total']], 'ja');

        return Response::json([
            'ledgers' => $ledgers,
            'total' => $results['total'],
            'meta' => $results['meta'], // meta情報も返す
            'search_trace' => $results['search_trace'] ?? [],
            '__summary__' => $summary,
        ]);
    }

    private function generateContentPreview(array $content, array $columnDefine, int $maxLength = 200): string
    {
        $preview = [];
        $totalLength = 0;

        foreach ($columnDefine as $column) {
            if ($totalLength >= $maxLength) {
                break;
            }

            // ColumnDefineオブジェクトまたは配列に対応
            if ($column instanceof ColumnDefine) {
                $columnId = $column->id;
                $columnName = $column->name;
            } else {
                $columnId = $column['id'] ?? null;
                $columnName = $column['name'] ?? null;
            }

            if ($columnId === null || ! isset($content[$columnId])) {
                continue;
            }

            $value = $content[$columnId];

            // 値が空の場合はスキップ
            if (empty($value)) {
                continue;
            }

            // 値を文字列に変換
            if (is_array($value)) {
                $value = implode(', ', $value);
            } else {
                $value = (string) $value;
            }

            $remainingLength = $maxLength - $totalLength;
            $truncated = mb_substr($value, 0, $remainingLength);
            $preview[] = ($columnName ?? 'フィールド'.$columnId).': '.$truncated;
            $totalLength += mb_strlen($truncated);
        }

        return implode(' / ', $preview);
    }

    /**
     * Format content_attached data into a user-friendly structure.
     *
     * @param  array|object  $contentAttached  The content_attached data from the ledger
     * @param  iterable<int, AttachedFile>  $attachedFiles  Related attached file models
     * @return array Array of attachment information
     */
    private function formatAttachments(
        array|object $contentAttached,
        iterable $attachedFiles = [],
        ?Model $ledger = null
    ): array {
        $normalizedEntries = $this->normalizeAttachmentEntries($contentAttached);
        $lookup = collect($normalizedEntries)
            ->keyBy(fn (array $entry): string => $this->attachmentLookupKey($entry['column_id'], $entry['hash']));
        $orderedEntries = [];

        foreach ($attachedFiles as $attachedFile) {
            if (! $attachedFile instanceof AttachedFile) {
                continue;
            }

            $key = $this->attachmentLookupKey($attachedFile->column_id, $attachedFile->hashedbasename);
            if ($lookup->has($key)) {
                $orderedEntries[] = [
                    'entry' => $lookup->get($key),
                    'attached_file' => $attachedFile,
                    'ledger' => $ledger,
                ];
                $lookup = $lookup->except([$key]);
            }
        }

        foreach ($lookup as $entry) {
            $orderedEntries[] = [
                'entry' => $entry,
                'attached_file' => null,
                'ledger' => $ledger,
            ];
        }

        $total = count($orderedEntries);
        $attachments = [];
        foreach ($orderedEntries as $index => $orderedEntry) {
            $attachments[] = $this->buildAttachmentRecord(
                entry: $orderedEntry['entry'],
                attachedFile: $orderedEntry['attached_file'],
                ledger: $orderedEntry['ledger'],
                order: $index + 1,
                total: $total,
            );
        }

        return $attachments;
    }

    /**
     * content_attached を添付レコードとして列挙しやすい形に正規化する。
     *
     * @return array<int, array{column_id:int|string, hash:string, file_info:array}>
     */
    private function normalizeAttachmentEntries(array|object $contentAttached): array
    {
        $attachments = [];

        foreach ($contentAttached as $columnId => $files) {
            if (empty($files) || ! is_array($files)) {
                continue;
            }

            foreach ($files as $hash => $fileInfo) {
                if (! is_array($fileInfo)) {
                    if (is_object($fileInfo)) {
                        $fileInfo = (array) $fileInfo;
                    } else {
                        continue;
                    }
                }

                $attachments[] = [
                    'column_id' => $columnId,
                    'hash' => (string) $hash,
                    'file_info' => $fileInfo,
                ];
            }
        }

        return $attachments;
    }

    /**
     * 添付 1 件分の表示データを構築する。
     */
    private function buildAttachmentRecord(
        array $entry,
        ?AttachedFile $attachedFile,
        ?Model $ledger,
        int $order,
        int $total
    ): array {
        $fileInfo = $entry['file_info'];
        $resolvedName = $attachedFile?->filename
            ?? $fileInfo['name']
            ?? $fileInfo['filename']
            ?? trans('common.unknown', [], 'ja');

        $mimeType = $this->resolveAttachmentMimeType($fileInfo, $attachedFile);
        $availableFormats = $this->resolveAttachmentAvailableFormats($fileInfo, $attachedFile, $mimeType);
        $deliveryMode = $this->resolveAttachmentDeliveryMode($availableFormats);

        $source = $attachedFile?->finalized_source
            ?? $fileInfo['source']
            ?? $fileInfo['meta']['source']
            ?? 'unknown';

        return [
            'attachment_id' => $attachedFile?->id,
            'filename' => $resolvedName,
            'name' => $resolvedName,
            'role' => $order === 1 ? 'primary' : 'supporting',
            'order' => $order,
            'source' => $source,
            'mime_type' => $mimeType,
            'delivery_mode' => $deliveryMode,
            'available_formats' => $availableFormats,
            'routes' => $this->buildAttachmentRoutes($ledger, $attachedFile),
            'payloads' => $this->buildAttachmentPayloads($fileInfo, $attachedFile, $ledger, $mimeType, $availableFormats),
            'size' => $fileInfo['size'] ?? 0,
            'size_formatted' => $this->formatFileSize($fileInfo['size'] ?? 0),
            'mime' => $mimeType,
            'column_id' => $entry['column_id'],
            'hash' => $entry['hash'],
            'total_attachments' => $total,
        ];
    }

    private function resolveAttachmentMimeType(array $fileInfo, ?AttachedFile $attachedFile): string
    {
        return $attachedFile?->original_mime_type
            ?? $attachedFile?->mime
            ?? $fileInfo['mime_type']
            ?? $fileInfo['mime']
            ?? 'application/octet-stream';
    }

    /**
     * @return array<int, string>
     */
    private function resolveAttachmentAvailableFormats(
        array $fileInfo,
        ?AttachedFile $attachedFile,
        string $mimeType
    ): array {
        $formats = ['text'];

        if ($attachedFile?->hasVlmResult()) {
            $formats[] = 'markdown';
        }

        if ($this->isStructuredMimeType($mimeType)
            || ! empty($attachedFile?->vlm_structured_data)
            || ! empty($fileInfo['pages'])
            || ! empty($fileInfo['text_blocks'])
            || ! empty($fileInfo['key_value_pairs'])) {
            $formats[] = 'structured';
            $formats[] = 'json';
        }

        if ($this->isVisualMimeType($mimeType)) {
            $formats[] = 'visual';
        }

        return array_values(array_unique($formats));
    }

    private function resolveAttachmentDeliveryMode(array $availableFormats): string
    {
        return $availableFormats[0] ?? 'text';
    }

    /**
     * @param  array<int, string>  $availableFormats
     * @return array<string, array<string, mixed>>
     */
    private function buildAttachmentPayloads(
        array $fileInfo,
        ?AttachedFile $attachedFile,
        ?object $ledger,
        string $mimeType,
        array $availableFormats
    ): array {
        return [
            'text' => $this->buildTextPayload($fileInfo, $attachedFile),
            'structured' => $this->buildStructuredPayload(
                $fileInfo,
                $attachedFile,
                in_array('structured', $availableFormats, true)
            ),
            'visual' => $this->buildVisualPayload(
                $ledger,
                $attachedFile,
                $mimeType,
                in_array('visual', $availableFormats, true)
            ),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildAttachmentRoutes(?Model $ledger, ?AttachedFile $attachedFile): array
    {
        $context = $this->resolveAttachmentRouteContext($ledger, $attachedFile);
        $canBuildDownload = $context['tenant_id'] !== null && $context['attachment_id'] !== null;
        $canBuildInspector = $canBuildDownload && $context['ledger_id'] !== null;

        return [
            'download' => [
                'available' => $canBuildDownload,
                'url' => $canBuildDownload
                    ? route('file.download', [
                        'tenant' => $context['tenant_id'],
                        'attachedFile' => $context['attachment_id'],
                        'original' => true,
                    ])
                    : null,
            ],
            'inspector' => [
                'available' => $canBuildInspector,
                'url' => $canBuildInspector
                    ? route('ledger.show', [
                        'tenant' => $context['tenant_id'],
                        'ledgerId' => $context['ledger_id'],
                        'file' => $context['attachment_id'],
                    ])
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTextPayload(array $fileInfo, ?AttachedFile $attachedFile): array
    {
        $rawText = $attachedFile?->vlm_markdown
            ?? $fileInfo['meta']['content']
            ?? $fileInfo['content']
            ?? null;

        if (! is_string($rawText) || trim($rawText) === '') {
            return [
                'available' => true,
                'text' => null,
                'lines' => [],
                'truncated' => false,
            ];
        }

        $normalizedText = trim($rawText);
        $truncated = mb_strlen($normalizedText) > self::ATTACHMENT_TEXT_PREVIEW_LIMIT;
        $previewText = mb_substr($normalizedText, 0, self::ATTACHMENT_TEXT_PREVIEW_LIMIT);
        $previewLines = preg_split('/\R/u', $previewText) ?: [];
        $lineLimited = count($previewLines) > self::ATTACHMENT_LINE_PREVIEW_LIMIT;
        $previewLines = array_slice($previewLines, 0, self::ATTACHMENT_LINE_PREVIEW_LIMIT);

        return [
            'available' => true,
            'text' => $previewText,
            'lines' => array_map(
                static fn (string $line, int $index): array => [
                    'line_number' => $index + 1,
                    'text' => $line,
                ],
                $previewLines,
                array_keys($previewLines)
            ),
            'truncated' => $truncated || $lineLimited,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStructuredPayload(array $fileInfo, ?AttachedFile $attachedFile, bool $available): array
    {
        $structuredData = is_array($attachedFile?->vlm_structured_data) ? $attachedFile->vlm_structured_data : [];

        $pages = $this->normalizeStructuredPayloadSection(
            $structuredData['pages'] ?? $fileInfo['pages'] ?? []
        );
        $textBlocks = $this->normalizeStructuredPayloadSection(
            $structuredData['text_blocks'] ?? $fileInfo['text_blocks'] ?? []
        );
        $keyValuePairs = $this->normalizeStructuredPayloadSection(
            $structuredData['key_value_pairs'] ?? $fileInfo['key_value_pairs'] ?? []
        );

        return [
            'available' => $available,
            'pages' => $pages,
            'text_blocks' => $textBlocks,
            'key_value_pairs' => $keyValuePairs,
            'confidence' => $attachedFile?->vlm_confidence,
            'optional_fields' => [
                'page_index' => $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'page_index'),
                'bbox' => $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'bbox'),
                'source_span' => $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'source_span'),
                'confidence' => $attachedFile?->vlm_confidence !== null
                    || $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'confidence'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVisualPayload(
        ?Model $ledger,
        ?AttachedFile $attachedFile,
        string $mimeType,
        bool $available
    ): array {
        $context = $this->resolveAttachmentRouteContext($ledger, $attachedFile);
        $canBuildVisual = $available
            && $context['tenant_id'] !== null
            && $context['attachment_id'] !== null;

        return [
            'available' => $canBuildVisual,
            'mime_type' => $mimeType,
            'signed_url' => $canBuildVisual
                ? route('file.download', [
                    'tenant' => $context['tenant_id'],
                    'attachedFile' => $context['attachment_id'],
                ])
                : null,
            'base64' => null,
            'expires_at' => null,
            'auth_required' => true,
        ];
    }

    /**
     * @return array{tenant_id:int|string|null, ledger_id:int|string|null, attachment_id:int|string|null}
     */
    private function resolveAttachmentRouteContext(?Model $ledger, ?AttachedFile $attachedFile): array
    {
        return [
            'tenant_id' => $ledger?->tenant_id ?? $attachedFile?->tenant_id,
            'ledger_id' => $ledger?->id ?? $attachedFile?->ledger_id,
            'attachment_id' => $attachedFile?->id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStructuredPayloadSection(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        return array_values(array_map(
            static fn ($item): array => is_array($item) ? $item : (array) $item,
            array_filter($section, static fn ($item): bool => is_array($item) || is_object($item))
        ));
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $sections
     */
    private function sectionContainsField(array $sections, string $field): bool
    {
        foreach ($sections as $section) {
            foreach ($section as $item) {
                if ($this->arrayContainsField($item, $field)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function arrayContainsField(array $payload, string $field): bool
    {
        if (array_key_exists($field, $payload)) {
            return true;
        }

        foreach ($payload as $value) {
            if (is_array($value) && $this->arrayContainsField($value, $field)) {
                return true;
            }
        }

        return false;
    }

    private function isVisualMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf';
    }

    private function isStructuredMimeType(string $mimeType): bool
    {
        return $mimeType === 'application/json';
    }

    private function attachmentLookupKey(int|string $columnId, string $hash): string
    {
        return $columnId.'|'.$hash;
    }

    private function describeAttachmentCount(int $count): string
    {
        return match (true) {
            $count <= 0 => '添付なし',
            $count === 1 => '1件の添付',
            default => $count.'件の添付',
        };
    }

    /**
     * Format file size in human-readable format.
     *
     * @param  int  $bytes  File size in bytes
     * @return string Formatted file size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = floor(log($bytes) / log(1024));
        $exp = min($exp, count($units) - 1);

        $size = $bytes / (1024 ** $exp);

        return round($size, 2).' '.$units[$exp];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string('Full-text search keyword. Supports Japanese and multi-byte characters. Examples: "株式会社A商事", "営業日報", "検索機能". Use quotes for exact match: "株式会社A商事". Space-separated for AND: "商事 提案".'),
            'tags' => $schema->string('Comma-separated exact tag names to filter by (AND condition). Resolve tag fragments with the tag lookup tool first. Example: "重要,新規" will find ledgers with BOTH tags.'),
            'folder_id' => $schema->array(
                'One or more folder candidate IDs resolved from folder-name fragments. Array-preserving lookup-first input is supported.'
            )->items($schema->integer()),
            'ledger_define_id' => $schema->array(
                'One or more ledger definition candidate IDs resolved from title fragments. Array-preserving lookup-first input is supported.'
            )->items($schema->integer()),
            'exclude_q' => $schema->string('Keywords to exclude from the results. Supports Japanese. Example: "見送り" will exclude ledgers containing this word.'),
            'exclude_tags' => $schema->string('Comma-separated tag names to exclude. Supports Japanese. Example: "完了,見送り" will exclude ledgers with these tags.'),
            'mode' => $schema->string('The search mode. "search" (default) returns full ledger data. "count" returns only the total number of matching ledgers (much faster for existence checks or statistics).')
                ->enum(['search', 'count'])
                ->default('search'),
            'limit' => $schema->integer('The maximum number of items to return. Use smaller values (10-20) for quick checks, larger values (50-100) for comprehensive searches.'),
            'offset' => $schema->integer('The number of items to skip for pagination. Use with limit to implement pagination (e.g., offset=20, limit=20 for page 2).'),
            'creator_id' => $schema->integer('The ID of the user who created the ledger. Use this to find all work by a specific person. You can get user IDs from meta.users in previous search results.'),
            'created_from' => $schema->string('The start date for filtering ledgers by creation date (YYYY-MM-DD). Example: "2025-10-01" for records from October 1st onwards.'),
            'created_to' => $schema->string('The end date for filtering ledgers by creation date (YYYY-MM-DD). Example: "2025-10-07" for records up to October 7th. Use with created_from for date range filtering.'),
            'order_by' => $schema->string('The field to sort results by. "composite_score" (default) sorts by overall importance (activity + freshness + workflow status). "activity_score" shows recently active items. "created_at" shows newest first. "updated_at" shows recently modified.')
                ->enum(['composite_score', 'activity_score', 'created_at', 'updated_at', 'semantic_score'])
                ->default('composite_score'),
            'order_direction' => $schema->string('The sort direction. "desc" (default) shows highest/newest first. "asc" shows lowest/oldest first. Useful with composite_score asc to find neglected items.')->enum(['asc', 'desc'])->default('desc'),
            'format' => $schema->string('The format of the response. "summary" (default) includes display-friendly fields like __display_fields__ and __summary__ with translations. "raw" returns only the normalized data without formatting (faster, use for machine processing).')->enum(['raw', 'summary'])->default('summary'),
            'include_content' => $schema->boolean('Whether to include full ledger content in summary format. Set to false for quick browsing of many ledgers (only metadata and preview shown). Default: true.')->default(true),
            'content_preview_length' => $schema->integer('The maximum length of content preview when include_content is false. Default: 200 characters. Increase for longer previews, decrease for quicker overview.')->default(200),
        ];
    }
}
