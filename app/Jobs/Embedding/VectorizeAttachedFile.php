<?php

namespace App\Jobs\Embedding;

use App\Enums\AttachedFileStatus;
use App\Jobs\ProcessLedgerForRagJob;
use App\Models\AttachedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2.6: ファイルのOCR結果をベクトル化
 *
 * 各OCR処理完了時に即座に呼ばれる
 * - Tika完了 → FINALIZED_BY_TIKA
 * - OCR完了 → FINALIZED_BY_OCR（Tikaより高品質）
 * - VLM完了 → FINALIZED_BY_VLM（最高品質）
 */
class VectorizeAttachedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $attachedFileId,
        public string $source  // 'tika', 'ocr', 'vlm'
    ) {}

    public function handle(): void
    {
        $file = AttachedFile::find($this->attachedFileId);

        if (! $file) {
            return;
        }

        $logChannel = config('rag.log_channel', 'stack');

        // 既にファイナライズ済みで、より良いソースでない場合はスキップ
        if ($file->status->isFinalized() && ! $file->status->canUpgradeWith($this->source, $file)) {
            Log::channel($logChannel)->info('[Vectorization] Skip, already better quality', [
                'file_id' => $file->id,
                'current' => $file->status->value,
                'new_source' => $this->source,
            ]);

            return;
        }

        // ベクトル化実行
        $this->vectorize($file, $logChannel);
    }

    /**
     * ベクトル化を実行
     */
    private function vectorize(AttachedFile $file, string $logChannel): void
    {
        try {
            // ProcessLedgerForRagJobをトリガー
            // 部分更新のために attached_file_id を渡す
            ProcessLedgerForRagJob::dispatch($file->ledger_id, $file->id);

            // ステータスをソース別に更新
            $newStatus = match ($this->source) {
                'tika' => AttachedFileStatus::FINALIZED_BY_TIKA,
                'ocr' => AttachedFileStatus::FINALIZED_BY_OCR,
                'vlm' => AttachedFileStatus::FINALIZED_BY_VLM,
                default => AttachedFileStatus::FINALIZED,
            };

            $file->update([
                'status' => $newStatus,
                'processing_finalized_at' => now(),
                'finalized_source' => $this->source,
            ]);

            Log::channel($logChannel)->info('[Vectorization] Completed', [
                'file_id' => $file->id,
                'source' => $this->source,
                'status' => $newStatus->value,
            ]);

        } catch (\Exception $e) {
            Log::channel($logChannel)->error('[Vectorization] Failed', [
                'file_id' => $file->id,
                'source' => $this->source,
                'error' => $e->getMessage(),
            ]);

            $file->update(['status' => AttachedFileStatus::PROCESSING_FAILED]);

            throw $e;
        }
    }
}
