<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Models\AttachedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
// use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;

class OcrAndOptimizeFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected AttachedFile $attachedFile;

    /**
     * Create a new job instance.
     */
    public function __construct(AttachedFile $attachedFile)
    {
        $this->attachedFile = $attachedFile;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        tenancy()->initialize($this->attachedFile->tenant_id);

        // Log::info('OcrAndOptimizeFile job started for file: ' . $this->attachedFile->id);

        // 1. status を OCR_PROCESSING に更新
        $this->attachedFile->update(['status' => AttachedFileStatus::OCR_PROCESSING->value]);

        // Determine the input file path for OCR
        $inputFilePathForOcr = $this->attachedFile->original_file_path ?: $this->attachedFile->path;

        // Log::info('Input file path for OCR: ' . $inputFilePathForOcr);
        if (! Storage::disk('public')->exists($inputFilePathForOcr)) {
            Log::error('Original file does not exist at path for OCR: '.$inputFilePathForOcr);
            $this->attachedFile->update(['status' => AttachedFileStatus::OCR_FAILED->value]);

            return;
        }

        $originalFilePath = Storage::disk('public')->path($inputFilePathForOcr);
        $originalMimeType = $this->attachedFile->mime; // mime_typeではなくmimeを使用

        // The original file should have been moved by ProcessAttachedFile.
        // This block is a fallback/safety check.
        if (empty($this->attachedFile->original_file_path)) {
            // 2. オリジナルファイルの退避
            $originalHashedBasename = basename($this->attachedFile->path);
            $newOriginalPath = AttachedFilePathHelper::getOriginalAttachmentPath($this->attachedFile->ledger_define_id, $originalHashedBasename);

            try {
                Storage::disk('public')->move($this->attachedFile->path, $newOriginalPath);
                // if (Storage::disk('public')->exists($newOriginalPath)) {
                //     Log::info('File successfully moved and exists at new path: ' . $newOriginalPath);
                // } else {
                //     Log::error('File moved but not found at new path: ' . $newOriginalPath);
                // }
                $this->attachedFile->update([
                    'original_file_path' => $newOriginalPath,
                    'original_mime_type' => $originalMimeType,
                    'path' => $newOriginalPath, // Update path to point to the original location
                ]);
                $this->attachedFile->refresh(); // モデルをリロードして最新の状態を反映
                // Log::info('Original file moved to: ' . $newOriginalPath);
                // Log::info('AttachedFile original_file_path after update: ' . $this->attachedFile->original_file_path);
                $originalFilePath = Storage::disk('public')->path($newOriginalPath); // Update physical path
            } catch (\Exception $e) {
                Log::error('Failed to move original file: '.$e->getMessage());
                $this->attachedFile->update(['status' => AttachedFileStatus::OCR_FAILED->value]);

                return;
            }
        } else {
            // 既に退避済みであれば、そのパスを使用
            $originalFilePath = Storage::disk('public')->path($this->attachedFile->original_file_path);
            $originalMimeType = $this->attachedFile->original_mime_type;
            // Log::info('Original file already exists at: ' . $this->attachedFile->original_file_path);
        }

        // Log::info('AttachedFile path before output path calculation: ' . $this->attachedFile->path);
        // Log::info('Dirname of attachedFile path: ' . dirname($this->attachedFile->path));

        // Determine the new path for the OCR\'d PDF within the storage system
        $outputFileName = pathinfo($this->attachedFile->hashedbasename, PATHINFO_FILENAME).'.pdf';
        $outputStoragePath = AttachedFilePathHelper::getAttachmentPath($this->attachedFile->ledger_define_id, $outputFileName);

        // Log::info('Calculated outputStoragePath: ' . $outputStoragePath);

        // Log::info('AttachedFile path before Storage::path($outputStoragePath): ' . $this->attachedFile->path);
        // Log::info('outputStoragePath before Storage::path($outputStoragePath): ' . $outputStoragePath);

        // Get the physical path for the OCR\'d PDF output
        $outputPhysicalPath = Storage::disk('public')->path($outputStoragePath);

        // コンテナ内のパスに変換
        //        $containerOriginalFilePath = '/var/www/html/storage/app/public/' . str_replace('public/', '', $inputFilePathForOcr);
        //        $containerOutputFilePath = '/var/www/html/storage/app/public/' . str_replace('public/', '', $outputStoragePath);
        $containerOriginalFilePath = '/var/www/html/storage/app/public/'.str_replace(Storage::disk('public')->path(''), '', $originalFilePath);
        $containerOutputFilePath = '/var/www/html/storage/app/public/'.str_replace(Storage::disk('public')->path(''), '', $outputPhysicalPath);

        // ocrmypdf コマンドの構築
        $command = [
            'docker',
            'exec',
            'ledgerleap-ocrmypdf-1',
            '/app/.venv/bin/ocrmypdf',
            '-l',
            'jpn',
            '--image-dpi',
            '300',
            '--skip-text',
            $containerOriginalFilePath,
            $containerOutputFilePath,
        ];

        try {
            // ★ Process ファサードを使ってコマンドを実行
            $result = Process::timeout(3600)->run($command);

            // ★ 失敗した場合に例外をスローさせる
            $result->throw();

            // 4. レコード情報の更新
            // --- 成功時の処理 ---
            $this->attachedFile->update([
                'path' => $outputStoragePath,
                'filename' => $outputFileName,
                'mime' => 'application/pdf',
                'optimized' => true,
                'size' => Storage::disk('public')->size($outputStoragePath),
                'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING->value, // Tika再処理のためのステータス
                'ocr_processed_at' => now(), // ★ Phase5: OCR成功時のタイムスタンプ
            ]);

            Log::info('[OCR] Processing successful', [
                'file_id' => $this->attachedFile->id,
                'output_file' => $outputFileName,
            ]);

            // ★ Phase2.6: OCR完了後、即座にベクトル化（Tikaより高品質で上書き）
            \App\Jobs\Embedding\VectorizeAttachedFile::dispatch(
                $this->attachedFile->id,
                'ocr'
            );

            // 5. Tikaによる再処理
            ProcessAttachedFile::dispatch($this->attachedFile);
            Log::info('[OCR] Dispatched Tika re-processing for file: '.$this->attachedFile->id);

        } catch (ProcessFailedException $e) {
            // --- 失敗時の処理 ---
            Log::error('[OCR] Processing failed', [
                'file_id' => $this->attachedFile->id,
                'error' => $e->getMessage(),
                'output' => $e->result->output(),
                'errorOutput' => $e->result->errorOutput(),
            ]);

            // ★ Phase5: OCR失敗時のタイムスタンプを設定
            $this->attachedFile->update([
                'status' => AttachedFileStatus::OCR_FAILED->value,
                'ocr_failed_at' => now(), // ★ Phase5: OCR失敗時のタイムスタンプ
            ]);

            // ★ Phase5: VLMフォールバック削除（並列処理なので不要）
            // OCR失敗はOCR失敗として記録し、最終化処理がVLM結果を選択する
        }
    }

    // ★ Phase5: shouldProcessWithVlmメソッドは不要（並列処理で削除）
}
