<?php

namespace App\Console\Commands\Phase26;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use App\Models\LedgerChunk;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2.6 効果測定コマンド
 *
 * 測定項目:
 * 1. 初回検索可能時間（Tika完了→ベクトル化）
 * 2. ファイルタイプ別のステータス分布
 * 3. ベクトル化完了率
 * 4. 段階的品質向上の状況
 */
class MeasureEffectiveness extends Command
{
    protected $signature = 'phase26:measure
                            {--refresh : Refresh and show real-time updates}
                            {--interval=3 : Refresh interval in seconds}';

    protected $description = 'Measure Phase 2.6 effectiveness (vectorization speed, quality improvement)';

    public function handle(): int
    {
        $tenant = Tenant::where('id', 'phase26demo')->first();

        if (! $tenant) {
            $this->error('❌ Phase26Demo tenant not found. Run: php artisan db:seed --class=Phase26DemoSeeder');

            return 1;
        }

        tenancy()->initialize($tenant);

        if ($this->option('refresh')) {
            $this->monitorRealTime();
        } else {
            $this->showSnapshot();
        }

        return 0;
    }

    private function showSnapshot(): void
    {
        $this->info('=== Phase 2.6 効果測定レポート ===');
        $this->newLine();

        // 1. 全体統計
        $this->showOverallStats();

        // 2. ファイルタイプ別統計
        $this->showFileTypeStats();

        // 3. ベクトル化状況
        $this->showVectorizationStats();

        // 4. 処理時間統計
        $this->showTimingStats();

        // 5. 品質向上状況
        $this->showQualityUpgradeStats();
    }

    private function showOverallStats(): void
    {
        $total = AttachedFile::count();
        $finalized = AttachedFile::whereIn('status', [
            AttachedFileStatus::FINALIZED_BY_TIKA,
            AttachedFileStatus::FINALIZED_BY_OCR,
            AttachedFileStatus::FINALIZED_BY_VLM,
        ])->count();

        $tikaCompleted = AttachedFile::whereNotNull('tika_processed_at')->count();
        $ocrCompleted = AttachedFile::whereNotNull('ocr_processed_at')->count();
        $vlmCompleted = AttachedFile::whereNotNull('vlm_processed_at')->count();

        $this->table(
            ['指標', '件数', '割合'],
            [
                ['総ファイル数', $total, '100%'],
                ['ファイナライズ済み', $finalized, $this->percentage($finalized, $total)],
                ['Tika完了', $tikaCompleted, $this->percentage($tikaCompleted, $total)],
                ['OCR完了', $ocrCompleted, $this->percentage($ocrCompleted, $total)],
                ['VLM完了', $vlmCompleted, $this->percentage($vlmCompleted, $total)],
            ]
        );
        $this->newLine();
    }

    private function showFileTypeStats(): void
    {
        $this->info('📊 ファイルタイプ別ステータス分布');

        $stats = DB::table('attached_files')
            ->select(
                DB::raw("CASE
                    WHEN mime LIKE 'application/vnd.openxmlformats%' THEN 'オフィス'
                    WHEN mime LIKE 'image/%' THEN '画像'
                    WHEN mime = 'application/pdf' THEN 'PDF'
                    ELSE 'その他'
                END as file_type"),
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('file_type', 'status')
            ->orderBy('file_type')
            ->get();

        $grouped = $stats->groupBy('file_type');

        foreach ($grouped as $type => $items) {
            $this->line("  {$type}:");
            foreach ($items as $item) {
                $statusLabel = $this->getStatusLabel($item->status);
                $this->line("    - {$statusLabel}: {$item->count}件");
            }
        }
        $this->newLine();
    }

    private function showVectorizationStats(): void
    {
        $this->info('🔍 ベクトル化状況');

        $totalFiles = AttachedFile::count();
        $totalChunks = LedgerChunk::count();
        $ledgersWithChunks = LedgerChunk::distinct('ledger_id')->count();

        $avgChunksPerFile = $totalFiles > 0 ? round($totalChunks / $totalFiles, 2) : 0;

        $this->table(
            ['指標', '値'],
            [
                ['総チャンク数', number_format($totalChunks)],
                ['ベクトル化済み台帳数', $ledgersWithChunks],
                ['平均チャンク数/ファイル', $avgChunksPerFile],
            ]
        );
        $this->newLine();
    }

    private function showTimingStats(): void
    {
        $this->info('⏱️ 処理時間統計');

        // Tika完了からファイナライズまでの時間
        $files = AttachedFile::whereNotNull('tika_processed_at')
            ->whereNotNull('processing_finalized_at')
            ->select(
                DB::raw('TIMESTAMPDIFF(SECOND, tika_processed_at, processing_finalized_at) as seconds'),
                'finalized_source'
            )
            ->get();

        if ($files->isEmpty()) {
            $this->warn('  まだ処理完了したファイルがありません');
            $this->newLine();

            return;
        }

        $grouped = $files->groupBy('finalized_source');

        foreach ($grouped as $source => $items) {
            $avg = round($items->avg('seconds'), 2);
            $min = $items->min('seconds');
            $max = $items->max('seconds');

            $sourceLabel = match ($source) {
                'tika' => 'Tika',
                'ocr' => 'OCR',
                'vlm' => 'VLM',
                default => $source,
            };

            $this->line("  {$sourceLabel}: 平均{$avg}秒 (最小{$min}秒, 最大{$max}秒)");
        }
        $this->newLine();
    }

    private function showQualityUpgradeStats(): void
    {
        $this->info('📈 品質向上状況（段階的アップグレード）');

        $tikaToOcr = AttachedFile::where('status', AttachedFileStatus::FINALIZED_BY_OCR)
            ->whereNotNull('tika_processed_at')
            ->count();

        $ocrToVlm = AttachedFile::where('status', AttachedFileStatus::FINALIZED_BY_VLM)
            ->whereNotNull('ocr_processed_at')
            ->count();

        $tikaOnly = AttachedFile::where('status', AttachedFileStatus::FINALIZED_BY_TIKA)
            ->whereNull('ocr_processed_at')
            ->whereNull('vlm_processed_at')
            ->count();

        $this->table(
            ['アップグレードパターン', '件数'],
            [
                ['Tikaのみで完了（オフィスファイル想定）', $tikaOnly],
                ['Tika → OCR', $tikaToOcr],
                ['OCR → VLM', $ocrToVlm],
            ]
        );
        $this->newLine();
    }

    private function monitorRealTime(): void
    {
        $interval = (int) $this->option('interval');

        $this->info("=== リアルタイム監視モード（{$interval}秒間隔） ===");
        $this->info('Ctrl+C で終了');
        $this->newLine();

        while (true) {
            system('clear');
            $this->showSnapshot();
            sleep($interval);
        }
    }

    private function percentage(int $count, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }

        return round(($count / $total) * 100, 1).'%';
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending_initial_processing' => '処理待ち',
            'initial_processing' => '初期処理中',
            'parallel_processing' => '並列処理中',
            'finalized_by_tika' => 'Tika完了',
            'finalized_by_ocr' => 'OCR完了',
            'finalized_by_vlm' => 'VLM完了',
            'tika_failed' => 'Tika失敗',
            'ocr_failed' => 'OCR失敗',
            'vlm_failed' => 'VLM失敗',
            default => $status,
        };
    }
}
