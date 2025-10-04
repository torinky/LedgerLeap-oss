<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
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
     * 統計データをサマリーフォーマットに変換
     */
    private function formatStatsSummary(array $stats, string $period): array
    {
        // 期間の日本語表示
        $periodDisplay = trans("ledger.period.{$period}", [], 'ja');

        // サマリーテキストの生成
        $summary = $this->generateSummaryText($stats, $periodDisplay);

        // 表示用フィールド
        $displayFields = [
            'period' => $periodDisplay,
            'total_activities' => trans('ledger.statistics.count_items', ['count' => $stats['total_activities']], 'ja'),
            'top_events' => $this->formatTopEvents($stats['by_event']),
            'top_users' => $this->formatTopUsers($stats['by_user']),
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
     * トップイベントをフォーマット
     */
    private function formatTopEvents(array $byEvent): string
    {
        if (empty($byEvent)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        $lines = [];
        foreach (array_slice($byEvent, 0, 5) as $event) {
            $lines[] = $event['event_display'].' ('.
                trans('ledger.statistics.count_items', ['count' => $event['count']], 'ja').')';
        }

        return implode(', ', $lines);
    }

    /**
     * トップユーザーをフォーマット
     */
    private function formatTopUsers(array $byUser): string
    {
        if (empty($byUser)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        $lines = [];
        foreach (array_slice($byUser, 0, 5) as $user) {
            $lines[] = $user['user_name'].' ('.
                trans('ledger.statistics.count_items', ['count' => $user['count']], 'ja').')';
        }

        return implode(', ', $lines);
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
}
