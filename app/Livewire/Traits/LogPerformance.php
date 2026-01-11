<?php

namespace App\Livewire\Traits;

trait LogPerformance
{
    /**
     * パフォーマンスログを記録
     *
     * @param string $metric メトリクス名 (config/ledgerleap.php参照)
     * @param float $duration 処理時間 (ms)
     * @param array $metadata 追加のメタデータ
     */
    public function logPerformance(string $metric, float $duration, array $metadata = []): void
    {
        // パフォーマンス測定が無効な場合は何もしない
        if (! config('ledgerleap.performance.enabled', false)) {
            return;
        }

        // メトリクス種別ごとの有効/無効チェック
        if (! config("ledgerleap.performance.metrics.{$metric}", true)) {
            return;
        }

        $context = method_exists($this, 'getPerformanceContext') ? $this->getPerformanceContext() : [];

        $logData = array_merge([
            'metric' => $metric,
            'duration_ms' => round($duration, 2),
            'component' => class_basename($this),
        ], $context, $metadata);

        $logDestination = config('ledgerleap.performance.log_destination', 'both');

        // Laravel標準ログへの記録
        if (in_array($logDestination, ['log', 'both'])) {
            \Illuminate\Support\Facades\Log::info("[Performance] {$metric}", $logData);
        }

        // JSON統計ファイルへの記録
        if (in_array($logDestination, ['json', 'both'])) {
            $statsFile = storage_path('logs/performance_stats.json');
            $stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
            $stats[] = array_merge($logData, ['timestamp' => now()->toISOString()]);
            file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        }
    }

    /**
     * コンポーネント固有のコンテキスト情報を取得
     * 必要に応じて各コンポーネントでオーバーライドする
     */
    protected function getPerformanceContext(): array
    {
        return [];
    }
}
