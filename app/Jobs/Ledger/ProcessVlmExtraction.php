<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Jobs\Embedding\VectorizeAttachedFile;
use App\Models\AttachedFile;
use App\Services\VlmClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVlmExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public AttachedFile $attachedFile;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(AttachedFile $attachedFile)
    {
        $this->attachedFile = $attachedFile;
        $this->tries = config('vlm.retry.times', 2);
        $this->backoff = config('vlm.retry.backoff', 300);
        $this->timeout = config('vlm.timeout', 600);
        $this->onQueue('vlm-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(VlmClientService $vlmClient): void
    {
        tenancy()->initialize($this->attachedFile->tenant_id);

        Log::info('[VLM] Starting extraction', [
            'file_id' => $this->attachedFile->id,
            'filename' => $this->attachedFile->filename,
            'model' => config('vlm.default_model'),
            'attempt' => $this->attempts(),
        ]);

        // ステータス更新
        $this->attachedFile->update(['status' => AttachedFileStatus::VLM_PROCESSING]);

        try {
            $startTime = microtime(true);

            // VLM APIコール
            $vlmOutput = $vlmClient->extract($this->attachedFile);

            $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // バリデーション
            if (empty($vlmOutput['markdown'])) {
                throw new \RuntimeException('VLM returned empty markdown');
            }

            // ★ Phase5: タイムスタンプ設定（成功時）
            // データベース保存
            $this->attachedFile->update([
                'vlm_markdown' => $vlmOutput['markdown'],
                'vlm_structured_data' => $vlmOutput['structured_data'] ?? null,
                'vlm_model' => $vlmOutput['model'] ?? config('vlm.default_model'),
                'vlm_confidence' => $vlmOutput['confidence'] ?? null,
                'vlm_processing_time_ms' => $processingTimeMs,
                'vlm_processed_at' => now(), // ★ Phase5: 成功時のタイムスタンプ
                // ★ Phase5: ステータスは最終化処理で設定されるため、ここでは更新しない
            ]);

            Log::info('[VLM] Extraction successful', [
                'file_id' => $this->attachedFile->id,
                'processing_time_ms' => $processingTimeMs,
                'confidence' => $vlmOutput['confidence'] ?? null,
                'markdown_length' => strlen($vlmOutput['markdown']),
            ]);

            // ★ Phase2.6: VLM完了後、即座にベクトル化（最高品質で上書き）
            VectorizeAttachedFile::dispatch(
                $this->attachedFile->id,
                'vlm'
            );

        } catch (\Exception $e) {
            Log::error('[VLM] Extraction failed', [
                'file_id' => $this->attachedFile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
            ]);

            // ★ Phase5: 最終試行失敗時に失敗タイムスタンプを設定
            if ($this->attempts() >= $this->tries) {
                $this->attachedFile->update([
                    'status' => AttachedFileStatus::VLM_FAILED,
                    'vlm_failed_at' => now(), // ★ Phase5: 失敗時のタイムスタンプ
                ]);
            }

            throw $e; // リトライ処理のため再スロー
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[VLM] Job failed permanently', [
            'file_id' => $this->attachedFile->id,
            'error' => $exception->getMessage(),
        ]);

        if ($this->attachedFile) {
            // ★ Phase5: 失敗時のタイムスタンプも設定
            $this->attachedFile->update([
                'status' => AttachedFileStatus::VLM_FAILED,
                'vlm_failed_at' => now(),
            ]);
        }
    }
}
