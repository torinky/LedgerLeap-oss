<?php

namespace App\Mcp\Traits;

/**
 * MCP統計ツール用共通フォーマットトレイト
 *
 * 統計データの期間パースや表示用フォーマット機能を提供します。
 */
trait HasStatsFormatting
{
    /**
     * 期間文字列をCarbonインスタンスの配列に変換
     */
    protected function parsePeriod(string $period): array
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
     * 期間の日本語表示を取得
     */
    protected function getPeriodDisplay(string $period): string
    {
        return trans("ledger.period.{$period}", [], 'ja');
    }

    /**
     * リスト形式の統計データをフォーマット
     *
     * 例: "Item1 (10件), Item2 (5件)"
     */
    protected function formatTopList(array $items, string $nameKey, int $limit = 5): string
    {
        if (empty($items)) {
            return trans('ledger.statistics.no_data', [], 'ja');
        }

        $lines = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $lines[] = $item[$nameKey].' ('.
                trans('ledger.statistics.count_items', ['count' => $item['count']], 'ja').')';
        }

        return implode(', ', $lines);
    }
}
