<?php

namespace App\Console\Commands\Ledger;

use App\Enums\AttachedFileStatus;
use App\Jobs\ProcessLedgerForRagJob;
use App\Models\AttachedFile;
use App\Models\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizeAttachedFileProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:finalize-processing
                            {--timeout=300 : Timeout in seconds for processing (default: 300)}
                            {--limit=50 : Maximum number of files to process in one run (default: 50)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finalize attached file processing by selecting the best content from VLM/OCR/Tika results';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $timeout = (int) $this->option('timeout');
        $limit = (int) $this->option('limit');

        $this->info('Starting finalization process...');
        $this->info("Timeout: {$timeout}s, Limit: {$limit} files");

        // 最終化待ちファイルを検索
        $readyFiles = $this->findReadyForFinalizationFiles($timeout, $limit);

        if ($readyFiles->isEmpty()) {
            $this->info('No files ready for finalization.');

            return self::SUCCESS;
        }

        $this->info("Found {$readyFiles->count()} files ready for finalization.");

        $successCount = 0;
        $failureCount = 0;
        $processedLedgers = [];

        foreach ($readyFiles as $file) {
            try {
                $this->finalizeFile($file);
                $successCount++;

                // 同じ台帳の重複処理を避けるため、台帳IDを記録
                if (! in_array($file->ledger_id, $processedLedgers)) {
                    $processedLedgers[] = $file->ledger_id;
                }

                $this->info("✓ Finalized file ID: {$file->id}");
            } catch (\Exception $e) {
                $failureCount++;
                $this->error("✗ Failed to finalize file ID: {$file->id} - {$e->getMessage()}");
                Log::error('Finalization failed for attached file', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // 台帳ごとにRAGジョブをディスパッチ
        $this->dispatchRagJobs($processedLedgers);

        $elapsed = round((microtime(true) - $startTime) * 1000, 2);

        $this->info('');
        $this->info('Finalization process completed.');
        $this->info("Success: {$successCount}, Failures: {$failureCount}");
        $this->info('RAG jobs dispatched for '.count($processedLedgers).' ledgers.');
        $this->info("Total time: {$elapsed}ms");

        return self::SUCCESS;
    }

    /**
     * Find files ready for finalization.
     */
    private function findReadyForFinalizationFiles(int $timeout, int $limit)
    {
        $timeoutAt = now()->subSeconds($timeout);

        return AttachedFile::query()
            ->whereNotNull('tika_processed_at')
            ->whereNull('processing_finalized_at')
            ->where(function ($query) use ($timeoutAt) {
                // VLMとOCRの両方が完了または失敗、あるいはタイムアウト
                $query->where(function ($q) {
                    // 両方完了/失敗
                    $q->where(function ($subQ) {
                        $subQ->whereNotNull('vlm_processed_at')
                            ->orWhereNotNull('vlm_failed_at');
                    })->where(function ($subQ) {
                        $subQ->whereNotNull('ocr_processed_at')
                            ->orWhereNotNull('ocr_failed_at');
                    });
                })->orWhere(function ($q) use ($timeoutAt) {
                    // タイムアウト判定
                    $q->where('tika_processed_at', '<', $timeoutAt);
                });
            })
            ->with(['ledger', 'ledger.define'])
            ->orderBy('tika_processed_at')
            ->limit($limit)
            ->get();
    }

    private function finalizeFile(AttachedFile $file): void
    {
        DB::transaction(function () use ($file) {
            // 最新の状態を取得
            $file->refresh();

            // 既に最終化済みの場合はスキップ
            if ($file->processing_finalized_at) {
                return;
            }

            // 最適な結果を選択
            $bestContent = $this->selectBestContent($file);

            // content_attachedを更新
            $this->updateContentAttached($file, $bestContent);

            // ★ Phase5: 適切なステータスを判定
            $finalStatus = $this->determineFinalStatus($file, $bestContent);

            // 最終化マークを設定
            $file->update([
                'processing_finalized_at' => now(),
                'finalized_source' => $bestContent['source'],
                'contain_content' => ! empty($bestContent['text']),
                'status' => $finalStatus, // ★ Phase5: ステータス更新を追加
            ]);

            Log::info('File finalized', [
                'file_id' => $file->id,
                'source' => $bestContent['source'],
                'status' => $finalStatus->value,
                'text_length' => mb_strlen($bestContent['text']),
            ]);
        });
    }

    /**
     * Determine the final status based on processing results.
     */
    private function determineFinalStatus(AttachedFile $file, array $bestContent): AttachedFileStatus
    {
        // 成功した処理がある場合はCOMPLETED
        if (! empty($bestContent['text'])) {
            return AttachedFileStatus::COMPLETED;
        }

        // テキストが空の場合、失敗理由を判定
        // VLMとOCRの両方が失敗している場合
        if ($file->vlm_failed_at && $file->ocr_failed_at) {
            return AttachedFileStatus::PROCESSING_FAILED;
        }

        // VLMのみ失敗
        if ($file->vlm_failed_at) {
            return AttachedFileStatus::VLM_FAILED;
        }

        // OCRのみ失敗
        if ($file->ocr_failed_at) {
            return AttachedFileStatus::OCR_FAILED;
        }

        // Tikaも失敗している場合
        if (empty($this->extractTikaTextFromContentAttached($file))) {
            return AttachedFileStatus::TIKA_FAILED;
        }

        // デフォルトはCOMPLETED（空のコンテンツでも処理は完了）
        return AttachedFileStatus::COMPLETED;
    }

    private function selectBestContent(AttachedFile $file): array
    {
        // Priority: VLM > OCR > Tika

        // 1. VLM結果を優先
        if ($file->vlm_processed_at && ! empty($file->vlm_markdown)) {
            return [
                'source' => 'vlm',
                'text' => $file->vlm_markdown,
            ];
        }

        // 2. OCR結果を厳密に確認
        if ($file->ocr_processed_at) {
            $originalExt = pathinfo($file->hashedbasename, PATHINFO_EXTENSION);
            $isImageFile = str_starts_with($file->original_mime_type ?? '', 'image/');

            // 画像ファイルの場合のみ .pdf キーをチェック
            if ($isImageFile && $originalExt !== 'pdf') {
                $pdfHashedbasename = pathinfo($file->hashedbasename, PATHINFO_FILENAME).'.pdf';
                $ocrText = $file->ledger?->content_attached[$file->column_id][$pdfHashedbasename]['meta']['content'] ?? null;

                if (! empty($ocrText)) {
                    return [
                        'source' => 'ocr',
                        'text' => $ocrText,
                    ];
                }
            }

            // PDFファイルの場合は元のキーをチェック
            // OCRは最適化のみなので、Tika再処理で更新されたテキストを使用
            if (! $isImageFile || $originalExt === 'pdf') {
                $ocrText = $file->ledger?->content_attached[$file->column_id][$file->hashedbasename]['meta']['content'] ?? null;

                if (! empty($ocrText)) {
                    // PDFのOCRは最適化のみなので、実質的にはTikaの結果
                    // ただし、ocr_processed_atが設定されている場合はOCR処理を経由している
                    return [
                        'source' => 'tika', // 実質的にはTikaの結果
                        'text' => $ocrText,
                    ];
                }
            }
        }

        // 3. Tika結果をフォールバック
        $tikaText = $this->extractTikaTextFromContentAttached($file);

        return [
            'source' => 'tika',
            'text' => $tikaText ?? '',
        ];
    }

    /**
     * Extract OCR text from ledger's content_attached.
     */
    private function extractOcrTextFromContentAttached(AttachedFile $file): ?string
    {
        if (! $file->ledger) {
            return null;
        }

        $contentAttached = $file->ledger->content_attached ?? [];
        $columnId = $file->column_id;
        $hashedbasename = $file->hashedbasename;

        // OCR処理後はPDFに変換されるため、拡張子が.pdfになっている可能性がある
        $pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME).'.pdf';

        // 両方試す
        $text = $contentAttached[$columnId][$pdfHashedbasename]['meta']['content'] ?? null;
        if (empty($text)) {
            $text = $contentAttached[$columnId][$hashedbasename]['meta']['content'] ?? null;
        }

        return $text;
    }

    /**
     * Extract Tika text from ledger's content_attached.
     */
    private function extractTikaTextFromContentAttached(AttachedFile $file): ?string
    {
        if (! $file->ledger) {
            return null;
        }

        $contentAttached = $file->ledger->content_attached ?? [];
        $columnId = $file->column_id;
        $hashedbasename = $file->hashedbasename;

        return $contentAttached[$columnId][$hashedbasename]['meta']['content'] ?? null;
    }

    /**
     * Update ledger's content_attached with the best content.
     */
    private function updateContentAttached(AttachedFile $file, array $bestContent): void
    {
        if (! $file->ledger_id) {
            return;
        }

        // 最新のLedgerを悲観的ロック付きで再取得し、バッチ内の他のファイルによる上書きを防ぐ
        $ledger = Ledger::where('id', $file->ledger_id)->lockForUpdate()->first();
        if (! $ledger) {
            return;
        }
        $contentAttached = $ledger->content_attached ?? [];
        $columnId = $file->column_id;
        $hashedbasename = $file->hashedbasename;

        // OCR処理後のPDF名も考慮
        $actualHashedbasename = $hashedbasename;
        if ($bestContent['source'] === 'ocr') {
            $pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME).'.pdf';
            if (isset($contentAttached[$columnId][$pdfHashedbasename])) {
                $actualHashedbasename = $pdfHashedbasename;
            }
        }

        // 配列構造を確保
        if (! isset($contentAttached[$columnId])) {
            $contentAttached[$columnId] = [];
        }
        if (! isset($contentAttached[$columnId][$actualHashedbasename])) {
            $contentAttached[$columnId][$actualHashedbasename] = [];
        }
        if (! isset($contentAttached[$columnId][$actualHashedbasename]['meta'])) {
            $contentAttached[$columnId][$actualHashedbasename]['meta'] = [];
        }

        // 最適なテキストで更新
        $contentAttached[$columnId][$actualHashedbasename]['meta']['content'] = $bestContent['text'];
        $contentAttached[$columnId][$actualHashedbasename]['meta']['source'] = $bestContent['source'];

        // 保存（イベント発火を抑制）
        $ledger->content_attached = $contentAttached;
        Ledger::withoutEvents(fn () => Ledger::withoutTimestamps(fn () => $ledger->save()));

        Log::info('Updated content_attached for file', [
            'ledger_id' => $ledger->id,
            'file_id' => $file->id,
            'source' => $bestContent['source'],
            'hashedbasename' => $actualHashedbasename,
        ]);
    }

    /**
     * Dispatch RAG jobs for processed ledgers.
     */
    private function dispatchRagJobs(array $ledgerIds): void
    {
        if (empty($ledgerIds)) {
            return;
        }

        foreach ($ledgerIds as $ledgerId) {
            // 遅延ディスパッチ（5秒後）で重複を避ける
            ProcessLedgerForRagJob::dispatch($ledgerId)
                ->delay(now()->addSeconds(5))
                ->onQueue('default');

            Log::info('Dispatched RAG job for ledger', [
                'ledger_id' => $ledgerId,
            ]);
        }

        $this->info('Dispatched RAG jobs for '.count($ledgerIds).' ledgers.');
    }
}
