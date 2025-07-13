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

        Log::info('AttachedFile path at start of job: ' . $this->attachedFile->path);
        if (!Storage::disk('public')->exists($this->attachedFile->path)) {
            Log::error('Original file does not exist at path: ' . $this->attachedFile->path);
            $this->attachedFile->update(['status' => AttachedFileStatus::OCR_FAILED->value]);
            return;
        }

        $originalFilePath = Storage::disk('public')->path($this->attachedFile->path);
        $originalMimeType = $this->attachedFile->mime; // mime_typeではなくmimeを使用

        // オリジナルファイルが既に退避されているか確認
        if (empty($this->attachedFile->original_file_path)) {
            // 2. オリジナルファイルの退避
            $originalDir = 'public/Ledger/Attachments/Originals/';
            $newOriginalPath = $originalDir . basename($this->attachedFile->path);

            try {
                Storage::disk('public')->move($this->attachedFile->path, $newOriginalPath);
                if (Storage::disk('public')->exists($newOriginalPath)) {
                    Log::info('File successfully moved and exists at new path: ' . $newOriginalPath);
                } else {
                    Log::error('File moved but not found at new path: ' . $newOriginalPath);
                }
                $this->attachedFile->update([
                    'original_file_path' => str_replace('public/', '', $newOriginalPath),
                    'original_mime_type' => $originalMimeType,
                ]);
                $this->attachedFile->refresh(); // モデルをリロードして最新の状態を反映
                Log::info('Original file moved to: ' . $newOriginalPath);
                // Debug log: Check attachedFile state after update
                Log::info('AttachedFile original_file_path after update: ' . $this->attachedFile->original_file_path);
            } catch (\Exception $e) {
                Log::error('Failed to move original file: ' . $e->getMessage());
                $this->attachedFile->update(['status' => AttachedFileStatus::OCR_FAILED->value]);
                return;
            }
        } else {
            // 既に退避済みであれば、そのパスを使用
            $originalFilePath = Storage::disk('public')->path($this->attachedFile->original_file_path);
            $originalMimeType = $this->attachedFile->original_mime_type;
            Log::info('Original file already exists at: ' . $this->attachedFile->original_file_path);
        }

        Log::info('AttachedFile path before output path calculation: ' . $this->attachedFile->path);
        Log::info('Dirname of attachedFile path: ' . dirname($this->attachedFile->path));

        // Determine the new path for the OCR'd PDF within the storage system
        // This path will be used to save the OCR'd PDF
        $outputFileName = pathinfo($this->attachedFile->hashedbasename, PATHINFO_FILENAME) . '.pdf';
        // The OCR'd PDF should go back to the main Attachments directory, not Originals
        $outputStoragePath = 'Ledger/Attachments/' . $outputFileName;

        Log::info('Calculated outputStoragePath: ' . $outputStoragePath);

        // Debug log: Check attachedFile path and outputStoragePath before Storage::path call
        Log::info('AttachedFile path before Storage::path($outputStoragePath): ' . $this->attachedFile->path);
        Log::info('outputStoragePath before Storage::path($outputStoragePath): ' . $outputStoragePath);

        // Get the physical path for the OCR'd PDF output
        $outputPhysicalPath = Storage::disk('public')->path($outputStoragePath);

        // コンテナ内のパスに変換
        // ocrmypdfコンテナはプロジェクトルートを/var/www/htmlにマウントしているため、
        // storage/app/public/からの相対パスを結合する
        $containerOriginalFilePath = '/var/www/html/storage/app/public/' . $this->attachedFile->original_file_path;
        $containerOutputFilePath = '/var/www/html/storage/app/public/' . str_replace('public/', '', $outputStoragePath);

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
            // Update the model's path attribute before calculating size
            $this->attachedFile->path = $outputStoragePath;
            $this->attachedFile->filename = $outputFileName; // ファイル名を.pdfに更新
            $this->attachedFile->mime = 'application/pdf'; // mime_typeではなくmimeを使用
            $this->attachedFile->optimized = true; // optimized を true に設定

            // Debugging Storage::size() issue
            Log::info('Checking existence before Storage::size(): ' . Storage::disk('public')->exists($this->attachedFile->path));
            try {
                $fileContent = Storage::disk('public')->get($this->attachedFile->path);
                Log::info('File content length: ' . strlen($fileContent));
            } catch (\Exception $e) {
                Log::error('Failed to get file content: ' . $e->getMessage());
            }

            $this->attachedFile->size = Storage::disk('public')->size($this->attachedFile->path);
            $this->attachedFile->save();
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
