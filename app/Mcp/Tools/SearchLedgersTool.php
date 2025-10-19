<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\JsonSchema\JsonSchema;
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
        Search for ledgers based on various criteria. 
        
        **Important for Japanese/Multi-byte Keywords:**
        - The 'q', 'tags', 'exclude_q', and 'exclude_tags' parameters support Japanese and other multi-byte characters
        - When using these parameters, ensure they are properly passed as-is (the MCP protocol handles encoding automatically)
        - Examples of valid Japanese keywords: "株式会社", "営業日報", "重要案件"
        
        **Response Format:**
        The 'format' parameter determines the response structure:
        - 'summary' (default): Returns a rich structure with processed fields for display (__display_fields__, __summary__) and normalized data (meta)
        - 'raw': Returns only the normalized data (ledgers, meta, total) for machine processing
        
        **Search Parameters:**
        - 'q': Full-text search keyword (supports Japanese)
        - 'tags': Comma-separated tag names (AND condition, supports Japanese)
        - 'folder_id': Search within specific folder (recursive)
        - 'ledger_define_id': Filter by ledger type
        - 'exclude_q': Exclude results containing these keywords
        - 'exclude_tags': Exclude results with these tags
        - 'creator_id': Filter by creator user ID
        - 'created_from' / 'created_to': Date range filter (YYYY-MM-DD)
        - 'mode': 'search' (default) returns full data, 'count' returns only the number of matching ledgers (faster)
        - 'limit' / 'offset': Pagination
        - 'include_content': Set to false to get metadata only (useful for quick browsing)
        - 'content_preview_length': Characters to preview from long text fields (default: 200)

        **Strategic Usage (戦略的利用法):**
        This tool is not just for single searches; it can be combined with other tools to answer more complex questions.

        - **Leveraging Metadata (メタデータの活用):**
          When a search is successful, the `meta` field in the response is automatically populated with complete information about related entities:
          - meta.users: Full user information for creators and modifiers (including id, name)
          - meta.folders: Folder information with paths
          - meta.ledger_defines: Ledger definition details
          
          **Best Practice for Identifying Responsible Persons:**
          To find who is in charge of something, search for the relevant ledger first, then check meta.users using the creator_id.
          Example: "Who is in charge of Company A?" → search_ledgers(q='Company A') → Check ledger.creator_id in meta.users

        - **Iterative Information Discovery Workflow (段階的な情報特定ワークフロー):**
          If an initial, broad keyword search (e.g., `q="Company A"`) yields no results, follow these steps:
          1. Use another tool like `get_activity_log_tool` to find related activities.
          2. From the activity log, identify a **clue** that can uniquely identify the target ledger (e.g., a unique phrase from the content, a specific tag).
          3. Use that **clue** as the `q` parameter in a new search with this tool.
          4. This targeted search will allow you to retrieve both the ledger data and the responsible user's information from the `meta` field in a single call.

        **Common Search Patterns (よく使う検索パターン):**
        
        1. Find all records for a specific company:
           search_ledgers(q='株式会社A商事', limit=50)
        
        2. Get this week's activity records:
           search_ledgers(created_from='2025-10-01', created_to='2025-10-07')
        
        3. Find important pending items:
           search_ledgers(tags='重要', exclude_tags='完了,見送り')
        
        4. Check a user's activity history:
           search_ledgers(creator_id=3, limit=20)
        
        5. Search within a specific folder:
           search_ledgers(folder_id=18, q='トラブル')
        
        6. Quick count without loading data:
           search_ledgers(mode='count', tags='重要')
        
        7. Get metadata only for quick browsing:
           search_ledgers(q='商談', include_content=false, limit=100)

        **Japanese Keyword Handling (日本語キーワードの扱い):**
        - Exact match: q='"株式会社A商事"' (use quotes)
        - Partial match: q='A商事' (no quotes)
        - Multiple keywords (AND): q='商事 提案' (space-separated)
        - Note: Mroonga uses morphological analysis, so searches are word-based

        **Handling "Important" Items (「重要」な案件の扱い):**
        The term "important" (重要) can be interpreted in two ways:
        1.  **By Tag:** Some ledgers might have a "重要" (Important) tag. Use `tags='重要'` to find these. This is a direct, explicit search.
        2.  **By Score:** The system calculates a `composite_score` for each ledger based on activity, freshness, and status. To find items that are algorithmically determined to be important, sort by this score using `order_by='composite_score'`. This is useful for finding items that need attention, even if they are not explicitly tagged.
        **Recommendation:** For a comprehensive search for "important" items, consider both approaches. Start with a score-based search (`order_by='composite_score'`) and supplement with a tag-based search if needed.

        **Sorting (ソート機能):**
        - 'order_by': Field to sort by (default: composite_score)
          - 'composite_score': Overall importance combining activity, freshness, and workflow status
          - 'activity_score': Recent activity frequency (useful for "What's hot?" queries)
          - 'created_at': Creation date (useful for "Show recent entries")
          - 'semantic_score': Semantic relevance to search query (requires 'q' parameter). Finds records based on meaning, not just keywords.
        - 'order_direction': Sort direction ('asc' or 'desc', default: 'desc')
        
        **Sorting Examples:**
        - "Show me the most important ledgers" → order_by='composite_score' (default)
        - "What are people working on recently?" → order_by='activity_score'
        - "Show oldest pending items" → order_by='created_at', order_direction='asc'
        - "What needs attention?" → order_by='composite_score' (high score = important)

        **Performance Tips (パフォーマンスのヒント):**
        - Broad searches without filters may be slow with large datasets
        - Use 'ledger_define_id' or 'folder_id' to narrow down the search scope
        - Use date ranges (created_from/created_to) to limit results
        - Use mode='count' when you only need to check if matching records exist

        **Workflow Example (ワークフロー例):**
        1. User asks: "Who is in charge of Project X?"
        2. `search_ledgers_tool(q='Project X')` returns 0 results.
        3. `get_activity_log_tool()` reveals an activity: "Submitted the final report for Project X".
        4. `search_ledgers_tool(q='"Submitted the final report for Project X"')` is executed.
        5. The response now contains the target ledger and the creator's (the person in charge) information in `meta.users`, allowing for a direct answer.
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

        $ledgers = collect($results['ledgers'])->map(function ($ledger) use ($results, $includeContent, $contentPreviewLength) {
            $meta = $results['meta'];
            $ledger = (object) $ledger; // 配列をオブジェクトに変換

            // 台帳定義とフォルダ情報を取得
            $define = $meta['ledger_defines'][$ledger->ledger_define_id] ?? null;

            // フォルダパスの取得
            $folderPath = ($define && isset($meta['folders'][$define['folder_id']]))
                ? $meta['folders'][$define['folder_id']]['path']
                : trans('common.root_folder', [], 'ja');

            // ステータスの翻訳
            $statusValue = is_object($ledger->status) ? $ledger->status->value : $ledger->status;
            $statusDisplay = trans('ledger.workflow.status.'.$statusValue, [], 'ja');

            // 日付のフォーマット
            $updatedAtFormatted = Carbon::parse($ledger->updated_at)->format('Y年m月d日 H:i');

            // __display_fields__ を構築（キーは英語固定）
            $displayFields = [
                'title' => $define['name'] ?? trans('common.unknown', [], 'ja'),
                'folder' => $folderPath,
                'creator' => $meta['users'][$ledger->creator_id]['name'] ?? trans('common.unknown', [], 'ja'),
                'workflow_status' => $statusDisplay,
                'updated_at' => $updatedAtFormatted,
            ];

            // contentの処理
            if (! $includeContent) {
                // プレビューを生成
                $displayFields['content_preview'] = $this->generateContentPreview(
                    $ledger->content ?? [],
                    $define['column_define'] ?? [],
                    $contentPreviewLength
                );
            }

            // __display_fields__をセット
            $ledger->__display_fields__ = $displayFields;

            // 添付ファイル情報の追加
            if (! empty($ledger->content_attached)) {
                // content_attachedをJSON経由で配列に変換（AsColumnArrayJsonがobjectを返すため）
                // これにより数値キーが正しく保持される
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

        $size = $bytes / pow(1024, $exp);

        return round($size, 2).' '.$units[$exp];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string('Full-text search keyword. Supports Japanese and multi-byte characters. Examples: "株式会社A商事", "営業日報", "検索機能". Use quotes for exact match: "株式会社A商事". Space-separated for AND: "商事 提案".'),
            'tags' => $schema->string('Comma-separated tag names to filter by (AND condition). Supports Japanese. Example: "重要,新規" will find ledgers with BOTH tags.'),
            'folder_id' => $schema->integer('The folder ID to recursively search within. Useful for limiting search scope to a specific department or project folder.'),
            'ledger_define_id' => $schema->integer('The ledger definition ID to filter by. Use this to search only within a specific type of ledger (e.g., only sales reports).'),
            'exclude_q' => $schema->string('Keywords to exclude from the results. Supports Japanese. Example: "見送り" will exclude ledgers containing this word.'),
            'exclude_tags' => $schema->string('Comma-separated tag names to exclude. Supports Japanese. Example: "完了,見送り" will exclude ledgers with these tags.'),
            'mode' => $schema->string('The search mode. "search" (default) returns full ledger data. "count" returns only the total number of matching ledgers (much faster for existence checks or statistics).')->enum(['search', 'count'])->default('search'),
            'limit' => $schema->integer('The maximum number of items to return. Use smaller values (10-20) for quick checks, larger values (50-100) for comprehensive searches.'),
            'offset' => $schema->integer('The number of items to skip for pagination. Use with limit to implement pagination (e.g., offset=20, limit=20 for page 2).'),
            'creator_id' => $schema->integer('The ID of the user who created the ledger. Use this to find all work by a specific person. You can get user IDs from meta.users in previous search results.'),
            'created_from' => $schema->string('The start date for filtering ledgers by creation date (YYYY-MM-DD). Example: "2025-10-01" for records from October 1st onwards.'),
            'created_to' => $schema->string('The end date for filtering ledgers by creation date (YYYY-MM-DD). Example: "2025-10-07" for records up to October 7th. Use with created_from for date range filtering.'),
            'order_by' => $schema->string('The field to sort results by. "composite_score" (default) sorts by overall importance (activity + freshness + workflow status). "activity_score" shows recently active items. "created_at" shows newest first. "updated_at" shows recently modified.')->enum(['composite_score', 'activity_score', 'created_at', 'updated_at'])->default('composite_score'),
            'order_direction' => $schema->string('The sort direction. "desc" (default) shows highest/newest first. "asc" shows lowest/oldest first. Useful with composite_score asc to find neglected items.')->enum(['asc', 'desc'])->default('desc'),
            'format' => $schema->string('The format of the response. "summary" (default) includes display-friendly fields like __display_fields__ and __summary__ with translations. "raw" returns only the normalized data without formatting (faster, use for machine processing).')->enum(['raw', 'summary'])->default('summary'),
            'include_content' => $schema->boolean('Whether to include full ledger content in summary format. Set to false for quick browsing of many ledgers (only metadata and preview shown). Default: true.')->default(true),
            'content_preview_length' => $schema->integer('The maximum length of content preview when include_content is false. Default: 200 characters. Increase for longer previews, decrease for quicker overview.')->default(200),
        ];
    }
}
