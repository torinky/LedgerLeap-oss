<?php

namespace App\Livewire\Traits;

use App\Services\PerformanceLogBuffer;
use Illuminate\Support\Facades\Log;

trait LogPerformance
{
    /**
     * パフォーマンスログを記録
     *
     * @param  string  $metric  メトリクス名 (config/ledgerleap.php参照)
     * @param  float  $duration  処理時間 (ms)
     * @param  array  $metadata  追加のメタデータ
     */
    public function logPerformance(string $metric, float $duration, array $metadata = []): void
    {
        // パフォーマンス測定が無効な場合は何もしない
        if (! config('ledgerleap.performance.enabled', false)) {
            return;
        }

        // メトリクス種別ごとの有効/無効チェック
        if (! $this->isPerformanceMetricEnabled($metric)) {
            return;
        }

        $monitoringMetadata = $this->getPerformanceMonitoringMetadata($metric, $duration);

        $context = method_exists($this, 'getPerformanceContext') ? $this->getPerformanceContext() : [];

        $logData = array_merge([
            'metric' => $metric,
            'duration_ms' => round($duration, 2),
            'component' => class_basename($this),
        ], $context, $metadata);

        if (! empty($monitoringMetadata)) {
            $logData = array_merge($logData, $monitoringMetadata);
        }

        $logDestination = config('ledgerleap.performance.log_destination', 'both');

        // Laravel標準ログへの記録
        if (in_array($logDestination, ['log', 'both'])) {
            Log::info("[Performance] {$metric}", $logData);
        }

        // JSON統計ファイルへの記録（バッファリングして一括書き込み）
        if (in_array($logDestination, ['json', 'both'])) {
            PerformanceLogBuffer::push(array_merge($logData, ['timestamp' => now()->toISOString()]));
        }

        $this->warnIfPerformanceThresholdExceeded($metric, $duration, $logData);
    }

    protected function isPerformanceMetricEnabled(string $metric): bool
    {
        $monitoring = config('ledgerleap.performance.monitoring', []);
        $alwaysOnMetrics = data_get($monitoring, 'always_on_metrics', []);

        if (in_array($metric, $alwaysOnMetrics, true)) {
            return true;
        }

        return (bool) config("ledgerleap.performance.metrics.{$metric}", true);
    }

    protected function getPerformanceMonitoringMetadata(string $metric, float $duration): array
    {
        $monitoring = config('ledgerleap.performance.monitoring', []);
        $alwaysOnMetrics = data_get($monitoring, 'always_on_metrics', []);
        $investigationMetrics = data_get($monitoring, 'investigation_metrics', []);
        $threshold = data_get($monitoring, "thresholds_ms.{$metric}");

        $tier = 'ad_hoc';
        if (in_array($metric, $alwaysOnMetrics, true)) {
            $tier = 'always_on';
        } elseif (in_array($metric, $investigationMetrics, true)) {
            $tier = 'investigation';
        }

        $metadata = [
            'monitoring_tier' => $tier,
        ];

        if ($threshold !== null) {
            $metadata['threshold_ms'] = round((float) $threshold, 2);
            $metadata['threshold_exceeded'] = $duration > (float) $threshold;
        }

        return $metadata;
    }

    protected function warnIfPerformanceThresholdExceeded(string $metric, float $duration, array $logData): void
    {
        $monitoring = config('ledgerleap.performance.monitoring', []);
        $threshold = data_get($monitoring, "thresholds_ms.{$metric}");

        if ($threshold === null || $duration <= (float) $threshold) {
            return;
        }

        $channel = data_get($monitoring, 'threshold_alert_channel', 'performance');
        $warningData = array_merge($logData, [
            'threshold_ms' => round((float) $threshold, 2),
            'exceeded_by_ms' => round($duration - (float) $threshold, 2),
        ]);

        if (is_string($channel) && $channel !== '' && config("logging.channels.{$channel}")) {
            Log::channel($channel)->warning(
                "[Performance] {$metric} threshold exceeded",
                $warningData
            );

            return;
        }

        Log::warning("[Performance] {$metric} threshold exceeded", $warningData);
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
