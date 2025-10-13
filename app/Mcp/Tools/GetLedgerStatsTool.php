<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Services\AnalyticsService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * 台帳統計取得MCPツール
 *
 * 期間別の台帳作成統計を取得し、台帳定義別、ステータス別、作成者別の集計を提供します。
 */
class GetLedgerStatsTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Get ledger statistics for a specified period.
        Returns statistics grouped by ledger define, status, and creator.
        
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
            $period = $request->get('period', 'this_week');
            $format = $request->get('format', 'summary');

            // 期間のパース
            [$from, $to] = $this->parsePeriod($period);

            // 統計データを取得
            $stats = $this->analyticsService->getLedgerStatsByPeriod($user, $from, $to);

            // フォーマットに応じてレスポンスを返す
            if ($format === 'raw') {
                return Response::json($stats);
            }

            // summary フォーマット
            return Response::json($this->formatStatsSummary($stats, $period));

        } catch (\Exception $e) {
            return Response::error(
                trans('ledger.error.occurred_with_message', ['message' => $e->getMessage()])
            );
        }
    }

    /**
     * 期間文字列をCarbonインスタンスの配列に変換
     */
    private function parsePeriod(string $period): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'last_quarter' => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            'last_7_days' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'last_30_days' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            'last_90_days' => [now()->subDays(90)->startOfDay(), now()->endOfDay()],
            default => [now()->startOfWeek(), now()->endOfWeek()],
        };
    }

    /**
     * 表示用フィールド
     */
    private function formatStatsSummary(array $stats, string $period): array
    {
        // 期間の日本語表示
        $periodDisplay = $this->getPeriodDisplay($period);

        // サマリーテキストの生成
        $summary = $this->generateSummaryText($stats, $periodDisplay);

        // 表示用フィールド
        $displayFields = [
            'period' => $periodDisplay,
            'total_created' => trans('ledger.statistics.count_items', ['count' => $stats['total_created']], 'ja'),
            'top_ledger_defines' => $this->formatTopDefines($stats['by_define']),
            'status_breakdown' => $this->formatStatusBreakdown($stats['by_status']),
            'top_creators' => $this->formatTopCreators($stats['by_creator']),
        ];

        return [
            '__display_fields__' => $displayFields,
            '__summary__' => $summary,
            'stats' => $stats,
            'meta' => [
                'period_key' => $period,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * 期間の日本語表示を取得
     */
    private function getPeriodDisplay(string $period): string
    {
        return trans("ledger.period.{$period}", [], 'ja');
    }

    /**
     * サマリーテキストを生成
     */
    private function generateSummaryText(array $stats, string $periodDisplay): string
    {
        $summary = trans('ledger.statistics.ledgers_created_in_period', [
            'period' => $periodDisplay,
            'count' => $stats['total_created'],
        ], 'ja')."\n\n";

        if (! empty($stats['by_define'])) {
            $topDefine = $stats['by_define'][0];
            $summary .= trans('ledger.statistics.most_created_type', [
                'type' => $topDefine['ledger_define_name'],
                'count' => $topDefine['count'],
            ], 'ja')."\n";
        }

        if (! empty($stats['by_status'])) {
            $summary .= "\n".trans('ledger.statistics.status_breakdown', [], 'ja')."\n";
            foreach ($stats['by_status'] as $status) {
                $summary .= trans('ledger.statistics.creator_stats', [
                    'name' => $status['status_display'],
                    'count' => $status['count'],
                ], 'ja')."\n";
            }
        }

        if (! empty($stats['by_creator'])) {
            $summary .= "\n".trans('ledger.statistics.top_creators', [], 'ja')."\n";
            foreach (array_slice($stats['by_creator'], 0, 3) as $creator) {
                $summary .= trans('ledger.statistics.creator_stats', [
                    'name' => $creator['user_name'],
                    'count' => $creator['count'],
                ], 'ja')."\n";
            }
        }

        return trim($summary);
    }

    /**
     * トップ台帳定義をフォーマット
     */
    private function formatTopDefines(array $byDefine): string
    {
        if (empty($byDefine)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        $lines = [];
        foreach (array_slice($byDefine, 0, 5) as $define) {
            $lines[] = $define['ledger_define_name'].' ('.
                trans('ledger.statistics.count_items', ['count' => $define['count']], 'ja').')';
        }

        return implode(', ', $lines);
    }

    /**
     * ステータス内訳をフォーマット
     */
    private function formatStatusBreakdown(array $byStatus): string
    {
        if (empty($byStatus)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        $lines = [];
        foreach ($byStatus as $status) {
            $lines[] = $status['status_display'].': '.
                trans('ledger.statistics.count_items', ['count' => $status['count']], 'ja');
        }

        return implode(', ', $lines);
    }

    /**
     * トップ作成者をフォーマット
     */
    private function formatTopCreators(array $byCreator): string
    {
        if (empty($byCreator)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        $lines = [];
        foreach (array_slice($byCreator, 0, 5) as $creator) {
            $lines[] = $creator['user_name'].' ('.
                trans('ledger.statistics.count_items', ['count' => $creator['count']], 'ja').')';
        }

        return implode(', ', $lines);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->description('Period for statistics: today, yesterday, this_week, last_week, this_month, last_month, this_quarter, last_quarter, this_year, last_year, last_7_days, last_30_days, last_90_days')
                ->default('this_week'),
            'format' => $schema->string()
                ->description('Response format: summary (human-readable) or raw (machine-processing)')
                ->enum(['summary', 'raw'])
                ->default('summary'),
        ];
    }
}
