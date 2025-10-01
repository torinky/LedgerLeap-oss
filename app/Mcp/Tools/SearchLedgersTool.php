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
        $format = $parameters['format'] ?? 'raw';

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

        if ($format === 'summary') {
            $ledgers = collect($results['ledgers'])->map(function ($ledger) {
                // ステータスを人間可読な文字列に変換
                $statusDisplay = match ($ledger->status->value) {
                    'none' => '下書き',
                    'in_progress' => '処理中',
                    'pending_inspection' => '点検待ち',
                    'pending_approval' => '承認待ち',
                    'approved' => '承認済み',
                    'rejected' => '却下',
                    default => $ledger->status->value,
                };

                // 日付をフォーマット
                $updatedAtFormatted = Carbon::parse($ledger->updated_at)->format('Y年m月d日 H:i');

                // __display_fields__ を追加
                $ledger['__display_fields__'] = [
                    '件名' => $ledger->define->title ?? '不明',
                    'ステータス' => $statusDisplay,
                    '更新日時' => $updatedAtFormatted,
                ];

                return $ledger;
            });

            $summary = "台帳が{$results['total']}件見つかりました。";
            if ($results['total'] > 0) {
                $summary = "あなたが作成した台帳は{$results['total']}件です。";
            }

            return Response::json([
                'ledgers' => $ledgers,
                'total' => $results['total'],
                '__summary__' => $summary,
            ]);
        }

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
            'format' => $schema->string('The format of the response.')->enum(['raw', 'summary'])->default('raw'),
        ];
    }
}
