<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
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

            // データベース保存
            $this->attachedFile->update([
                'vlm_markdown' => $vlmOutput['markdown'],
                'vlm_structured_data' => $vlmOutput['structured_data'] ?? null,
                'vlm_model' => $vlmOutput['model'] ?? config('vlm.default_model'),
                'vlm_confidence' => $vlmOutput['confidence'] ?? null, // confidenceはVLMコンテナの実装に依存
                'vlm_processing_time_ms' => $processingTimeMs,
                'vlm_processed_at' => now(),
                'status' => AttachedFileStatus::COMPLETED,
            ]);

            Log::info('[VLM] Extraction successful', [
                'file_id' => $this->attachedFile->id,
                'processing_time_ms' => $processingTimeMs,
                'confidence' => $vlmOutput['confidence'] ?? null,
                'markdown_length' => strlen($vlmOutput['markdown']),
            ]);

            if (config('rag.chunking.auto_update_chunks', true)) {
                \App\Jobs\ProcessLedgerForRagJob::dispatch($this->attachedFile->ledger)
                    ->delay(now()->addSeconds(5));
            }

        } catch (\Exception $e) {
            Log::error('[VLM] Extraction failed', [
                'file_id' => $this->attachedFile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
            ]);

            // 最終試行失敗時のみステータス更新
            if ($this->attempts() >= $this->tries) {
                $this->attachedFile->update(['status' => AttachedFileStatus::VLM_FAILED]);
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
            $this->attachedFile->update(['status' => AttachedFileStatus::VLM_FAILED]);
        }
    }
}
