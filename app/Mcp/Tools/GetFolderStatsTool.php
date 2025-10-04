<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Services\AnalyticsService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * フォルダ統計取得MCPツール
 *
 * ユーザーがアクセス可能なフォルダの統計情報を取得します。
 */
class GetFolderStatsTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Get folder statistics for all accessible folders.
        Returns statistics including ledger define count, ledger count, and recent activity.
        
        The 'format' parameter determines the response structure:
        - 'summary' (default): Returns a human-readable summary with Japanese translations
        - 'raw': Returns only the raw statistical data for machine processing
MARKDOWN;

    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    public function handle(Request $request): Response
    {
        try {
            // 認証チェック
            $user = $this->authenticateOrError();
            if ($user instanceof Response) {
                return $user;
            }

            // パラメータ取得
            $format = $request->get('format', 'summary');

            // 統計データを取得
            $stats = $this->analyticsService->getFolderStats($user);

            // フォーマットに応じてレスポンスを返す
            if ($format === 'raw') {
                return Response::json($stats);
            }

            // summary フォーマット
            return Response::json($this->formatStatsSummary($stats));

        } catch (\Exception $e) {
            return Response::error(
                trans('ledger.error.occurred_with_message', ['message' => $e->getMessage()])
            );
        }
    }

    /**
     * 統計データをサマリーフォーマットに変換
     */
    private function formatStatsSummary(array $stats): array
    {
        // サマリーテキストの生成
        $summary = $this->generateSummaryText($stats);

        // 表示用フィールド
        $displayFields = [
            'total_folders' => trans('ledger.statistics.count_items', ['count' => $stats['total_folders']], 'ja'),
            'total_ledger_defines' => trans('ledger.statistics.count_items', ['count' => $stats['total_ledger_defines']], 'ja'),
            'total_ledgers' => trans('ledger.statistics.count_items', ['count' => $stats['total_ledgers']], 'ja'),
            'top_folders' => $this->formatTopFolders($stats['folders']),
        ];

        return [
            '__display_fields__' => $displayFields,
            '__summary__' => $summary,
            'stats' => $stats,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * サマリーテキストを生成
     */
    private function generateSummaryText(array $stats): string
    {
        $summary = trans('ledger.statistics.folder_summary', [
            'folder_count' => $stats['total_folders'],
            'define_count' => $stats['total_ledger_defines'],
            'ledger_count' => $stats['total_ledgers'],
        ], 'ja')."\n\n";

        if (! empty($stats['folders'])) {
            $summary .= trans('ledger.statistics.folder_details', [], 'ja')."\n";
            foreach (array_slice($stats['folders'], 0, 5) as $folder) {
                $summary .= trans('ledger.statistics.folder_stat_line', [
                    'name' => $folder['folder_name'],
                    'ledger_count' => $folder['ledger_count'],
                    'recent' => $folder['recent_activity'],
                ], 'ja')."\n";
            }
        }

        return trim($summary);
    }

    /**
     * トップフォルダをフォーマット
     */
    private function formatTopFolders(array $folders): string
    {
        if (empty($folders)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        // 台帳数でソート
        $sorted = collect($folders)->sortByDesc('ledger_count')->take(5);
        $lines = [];
        foreach ($sorted as $folder) {
            $lines[] = $folder['folder_name'].' ('.
                trans('ledger.statistics.count_items', ['count' => $folder['ledger_count']], 'ja').')';
        }

        return implode(', ', $lines);
    }
}
