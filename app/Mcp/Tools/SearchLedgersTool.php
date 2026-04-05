<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SearchLedgersTool extends Tool
{
    use AuthenticatedMcpTool;

    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search for ledgers by keyword, tags, date range, creator, folder, ledger type, or semantic relevance.
        `q` is synonym-aware, so natural-language search terms can be expanded when synonym data is available.

        Important for Japanese / multi-byte keywords:
        - `q`, `tags`, `exclude_q`, and `exclude_tags` accept Japanese and other multi-byte characters
        - `q` can be expanded with synonyms for business terms when synonym data is available
        - tag / folder / ledger definition fragments should be resolved first with lookup tools before calling this search tool

        Response format:
        - `summary` (default): display-oriented records with `__display_fields__`, `__summary__`, normalized `meta`, and a detail `link` when available
        - `raw`: normalized `ledgers`, `meta`, and `total` for machine processing

        Key parameters:
        - `q`, `tags`, `exclude_q`, `exclude_tags`
        - `folder_id`, `ledger_define_id`, `creator_id`
        - `folder_id` / `ledger_define_id` accept lookup-first candidate ID arrays resolved by the folder / ledger lookup tools
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

        // summary フォーマットの場合、表示用の加工を行う
        $includeContent = $parameters['include_content'] ?? true;
        $contentPreviewLength = $parameters['content_preview_length'] ?? 200;

        $ledgers = collect($results['ledgers'])
            ->map(function ($ledger) use ($results, $includeContent, $contentPreviewLength) {
                $meta = $results['meta'];
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

                if (! empty($ledger->content_attached)) {
                    $contentAttached = json_decode(json_encode($ledger->content_attached), true);
                    $ledger->attachments = $this->formatAttachments($contentAttached);
                }

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
            if ($column instanceof \App\Models\ColumnDefine) {
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
     * @param  array  $contentAttached  The content_attached array from the ledger
     * @return array Array of attachment information
     */
    private function formatAttachments(array $contentAttached): array
    {
        $attachments = [];

        foreach ($contentAttached as $columnId => $files) {
            // 空の配列はスキップ
            if (empty($files) || ! is_array($files)) {
                continue;
            }

            // filesを配列に変換（オブジェクトの場合に対応）
            if (is_object($files)) {
                $files = (array) $files;
            }

            foreach ($files as $hash => $fileInfo) {
                // fileInfoが配列であることを確認
                if (! is_array($fileInfo)) {
                    if (is_object($fileInfo)) {
                        $fileInfo = (array) $fileInfo;
                    } else {
                        continue;
                    }
                }

                $attachments[] = [
                    'name' => $fileInfo['name'] ?? trans('common.unknown', [], 'ja'),
                    'size' => $fileInfo['size'] ?? 0,
                    'size_formatted' => $this->formatFileSize($fileInfo['size'] ?? 0),
                    'mime' => $fileInfo['mime'] ?? 'application/octet-stream',
                    'column_id' => $columnId,
                    'hash' => $hash,
                ];
            }
        }

        return $attachments;
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
