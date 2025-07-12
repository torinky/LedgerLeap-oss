<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

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
        Log::info('OcrAndOptimizeFile job started for file: ' . $this->attachedFile->id);

        // 1. status を OCR_PROCESSING に更新
        $this->attachedFile->update(['status' => AttachedFileStatus::OCR_PROCESSING->value]);

        $originalFilePath = Storage::path($this->attachedFile->path);
        $originalMimeType = $this->attachedFile->mime; // mime_typeではなくmimeを使用

        // オリジナルファイルが既に退避されているか確認
        if (empty($this->attachedFile->original_file_path)) {
            // 2. オリジナルファイルの退避
            $originalDir = 'public/Ledger/Attachments/Originals/';
            $newOriginalPath = $originalDir . basename($this->attachedFile->path);

            try {
                Storage::move($this->attachedFile->path, $newOriginalPath);
                $this->attachedFile->update([
                    'original_file_path' => $newOriginalPath,
                    'original_mime_type' => $originalMimeType,
                ]);
                Log::info('Original file moved to: ' . $newOriginalPath);
            } catch (\Exception $e) {
                Log::error('Failed to move original file: ' . $e->getMessage());
                $this->attachedFile->update(['status' => AttachedFileStatus::OCR_FAILED->value]);
                return;
            }
        } else {
            // 既に退避済みであれば、そのパスを使用
            $originalFilePath = Storage::path($this->attachedFile->original_file_path);
            $originalMimeType = $this->attachedFile->original_mime_type;
            Log::info('Original file already exists at: ' . $this->attachedFile->original_file_path);
        }

        $outputFilePath = Storage::path($this->attachedFile->path); // 元のパスに上書き

        // コンテナ内のパスに変換
        // Laravel Sail環境では、プロジェクトルートが/var/www/htmlにマウントされている
        $containerOriginalFilePath = str_replace(base_path(), '/var/www/html', $originalFilePath);
        $containerOutputFilePath = str_replace(base_path(), '/var/www/html', $outputFilePath);

        $outputFileName = pathinfo($this->attachedFile->filename, PATHINFO_FILENAME) . '.pdf';

        // ocrmypdf コマンドの構築
        // Process クラスに配列で引数を渡す形式に変更
        $command = [
            'docker',
            'exec',
            'ledgerleap-ocrmypdf-1',
            '/app/.venv/bin/ocrmypdf',
            '-l',
            'jpn',
            '--image-dpi',
            '300',
            $containerOriginalFilePath,
            $containerOutputFilePath,
        ];

        $process = new Process($command);
        $process->setTimeout(3600); // 1時間

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }

            // 4. レコード情報の更新
            $this->attachedFile->update([
                'filename' => $outputFileName, // ファイル名を.pdfに更新
                'mime' => 'application/pdf', // mime_typeではなくmimeを使用
                'size' => Storage::size($this->attachedFile->path),
            ]);
            Log::info('OCR and optimization successful for file: ' . $this->attachedFile->id);

            // 5. Tikaによる再処理
            $this->attachedFile->update(['status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING->value]);
            ProcessAttachedFile::dispatch($this->attachedFile);
            Log::info('Dispatched ProcessAttachedFile for re-processing: ' . $this->attachedFile->id);

        } catch (\Exception $e) {
            Log::error('OCR and optimization failed for file ' . $this->attachedFile->id . ': ' . $e->getMessage());
            // 失敗時: status を OCR_FAILED に更新
            $this->attachedFile->update(['status' => AttachedFileStatus::OCR_FAILED->value]);
        }
    }
}
