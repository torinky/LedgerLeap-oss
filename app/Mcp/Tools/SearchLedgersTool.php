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

    /**
     * Handle the tool request.
     */
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

        // summary フォーマットの場合、表示用の加工を行う
        if (($parameters['format'] ?? 'summary') === 'summary') {
            $ledgers = collect($results['ledgers'])->map(function ($ledger) use ($results) {
                $meta = $results['meta'];
                $ledger = (object) $ledger; // 配列をオブジェクトに変換

                // ステータス
                $statusDisplay = __('ledger.workflow.status.'.($ledger->status->value ?? $ledger->status), [], 'ja');

                // 日付
                $updatedAtFormatted = Carbon::parse($ledger->updated_at)->format('Y年m月d日 H:i');

                // フォルダパス
                $define = $meta['ledger_defines'][$ledger->ledger_define_id] ?? null;
                $folderPath = ($define && isset($meta['folders'][$define['folder_id']]))
                    ? $meta['folders'][$define['folder_id']]['path']
                    : __('common.root_folder', [], 'ja');

                // __display_fields__ を追加
                $ledger->__display_fields__ = [
                    __('ledger.field.title', [], 'ja') => $define['name'] ?? __('common.unknown', [], 'ja'),
                    __('ledger.field.folder', [], 'ja') => $folderPath,
                    __('ledger.field.creator', [], 'ja') => $meta['users'][$ledger->creator_id]['name'] ?? __('common.unknown', [], 'ja'),
                    __('ledger.field.status', [], 'ja') => $statusDisplay,
                    __('ledger.field.updated_at', [], 'ja') => $updatedAtFormatted,
                ];

                return $ledger;
            });

            $summary = trans_choice('messages.found_ledgers', $results['total'], [], 'ja');

            return Response::json([
                'ledgers' => $ledgers,
                'total' => $results['total'],
                'meta' => $results['meta'], // meta情報も返す
                '__summary__' => $summary,
            ]);
        }

        // rawフォーマットの場合
        return Response::json($results);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
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
        ];
    }
}
