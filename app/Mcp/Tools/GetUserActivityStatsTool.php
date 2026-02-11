<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Mcp\Traits\HasStatsFormatting;
use App\Services\AnalyticsService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * ユーザー活動統計取得MCPツール
 *
 * 期間別のユーザー活動統計を取得し、イベント種類別、ユーザー別、時間帯別の集計を提供します。
 */
class GetUserActivityStatsTool extends Tool
{
    use AuthenticatedMcpTool;
    use HasStatsFormatting;

    protected string $description = <<<'MARKDOWN'
        Get user activity statistics for a specified period.
        Returns statistics grouped by event type, user, and hour of day.
        
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
            $stats = $this->analyticsService->getUserActivityStats($user, $from, $to);

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
     * 統計データをサマリーフォーマットに変換
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
            'total_activities' => trans('ledger.statistics.count_items', ['count' => $stats['total_activities']], 'ja'),
            'top_events' => $this->formatTopList($stats['by_event'], 'event_display'),
            'top_users' => $this->formatTopList($stats['by_user'], 'user_name'),
            'peak_hours' => $this->formatPeakHours($stats['by_hour']),
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
     * サマリーテキストを生成
     */
    private function generateSummaryText(array $stats, string $periodDisplay): string
    {
        $summary = trans('ledger.statistics.activities_in_period', [
            'period' => $periodDisplay,
            'count' => $stats['total_activities'],
        ], 'ja')."\n\n";

        if (! empty($stats['by_event'])) {
            $summary .= trans('ledger.statistics.event_breakdown', [], 'ja')."\n";
            foreach (array_slice($stats['by_event'], 0, 5) as $event) {
                $summary .= trans('ledger.statistics.creator_stats', [
                    'name' => $event['event_display'],
                    'count' => $event['count'],
                ], 'ja')."\n";
            }
        }

        if (! empty($stats['by_user'])) {
            $summary .= "\n".trans('ledger.statistics.top_active_users', [], 'ja')."\n";
            foreach (array_slice($stats['by_user'], 0, 3) as $user) {
                $summary .= trans('ledger.statistics.creator_stats', [
                    'name' => $user['user_name'],
                    'count' => $user['count'],
                ], 'ja')."\n";
            }
        }

        if (! empty($stats['by_hour'])) {
            $peakHour = collect($stats['by_hour'])->sortByDesc('count')->first();
            if ($peakHour) {
                $summary .= "\n".trans('ledger.statistics.peak_hour', [
                    'hour' => $peakHour['hour'],
                    'count' => $peakHour['count'],
                ], 'ja');
            }
        }

        return trim($summary);
    }



    /**
     * ピーク時間帯をフォーマット
     */
    private function formatPeakHours(array $byHour): string
    {
        if (empty($byHour)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        $sorted = collect($byHour)->sortByDesc('count')->take(3);
        $lines = [];
        foreach ($sorted as $hour) {
            $lines[] = $hour['hour'].trans('ledger.statistics.hour_suffix', [], 'ja').' ('.
                trans('ledger.statistics.count_items', ['count' => $hour['count']], 'ja').')';
        }

        return implode(', ', $lines);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
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
