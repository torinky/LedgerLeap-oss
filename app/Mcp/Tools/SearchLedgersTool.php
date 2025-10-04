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
        The 'format' parameter determines the response structure.
        - 'summary' (default): Returns a rich structure with processed fields for display (__display_fields__, __summary__) and normalized data (meta).
        - 'raw': Returns only the normalized data (ledgers, meta, total) for machine processing.
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
            'q' => $schema->string('The search keyword for full-text search.'),
            'tags' => $schema->string('Comma-separated tag names to filter by (AND condition).'),
            'folder_id' => $schema->integer('The folder ID to recursively search within.'),
            'ledger_define_id' => $schema->integer('The ledger definition ID to filter by.'),
            'exclude_q' => $schema->string('Keywords to exclude from the results.'),
            'exclude_tags' => $schema->string('Comma-separated tag names to exclude.'),
            'mode' => $schema->string('The search mode.')->enum(['search', 'count'])->default('search'),
            'limit' => $schema->integer('The maximum number of items to return.'),
            'offset' => $schema->integer('The number of items to skip for pagination.'),
            'creator_id' => $schema->integer('The ID of the user who created the ledger.'),
            'created_from' => $schema->string('The start date for filtering ledgers by creation date (YYYY-MM-DD).'),
            'created_to' => $schema->string('The end date for filtering ledgers by creation date (YYYY-MM-DD).'),
            'format' => $schema->string('The format of the response.')->enum(['raw', 'summary'])->default('summary'),
            'include_content' => $schema->boolean('Whether to include full content in summary format. If false, only a preview is included.')->default(true),
            'content_preview_length' => $schema->integer('The maximum length of content preview when include_content is false.')->default(200),
        ];
    }
}
