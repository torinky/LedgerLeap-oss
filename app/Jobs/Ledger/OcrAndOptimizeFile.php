<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Process\Exceptions\ProcessFailedException;

// ★ use を変更
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Stancl\Tenancy\Contracts\TenantAware;

// ★ use を変更
//use Symfony\Component\Process\Process;
use App\Helpers\AttachedFilePathHelper;

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
        // Log::info('OcrAndOptimizeFile job started for file: ' . $this->attachedFile->id);

        // 1. status を OCR_PROCESSING に更新
        $this->attachedFile->update(['status' => AttachedFileStatus::OCR_PROCESSING->value]);

        // Determine the input file path for OCR
        $inputFilePathForOcr = $this->attachedFile->original_file_path ?: $this->attachedFile->path;

        // Log::info('Input file path for OCR: ' . $inputFilePathForOcr);
        if (!Storage::disk('public')->exists($inputFilePathForOcr)) {
            Log::error('Original file does not exist at path for OCR: ' . $inputFilePathForOcr);
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
                Log::error('Failed to move original file: ' . $e->getMessage());
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
        $outputFileName = pathinfo($this->attachedFile->hashedbasename, PATHINFO_FILENAME) . '.pdf';
        $outputStoragePath = AttachedFilePathHelper::getAttachmentPath($this->attachedFile->ledger_define_id, $outputFileName);

        // Log::info('Calculated outputStoragePath: ' . $outputStoragePath);

        // Log::info('AttachedFile path before Storage::path($outputStoragePath): ' . $this->attachedFile->path);
        // Log::info('outputStoragePath before Storage::path($outputStoragePath): ' . $outputStoragePath);

        // Get the physical path for the OCR\'d PDF output
        $outputPhysicalPath = Storage::disk('public')->path($outputStoragePath);

        // コンテナ内のパスに変換
//        $containerOriginalFilePath = '/var/www/html/storage/app/public/' . str_replace('public/', '', $inputFilePathForOcr);
//        $containerOutputFilePath = '/var/www/html/storage/app/public/' . str_replace('public/', '', $outputStoragePath);
        $containerOriginalFilePath = '/var/www/html/storage/app/public/' . str_replace(Storage::disk('public')->path(''), '', $originalFilePath);
        $containerOutputFilePath = '/var/www/html/storage/app/public/' . str_replace(Storage::disk('public')->path(''), '', $outputPhysicalPath);

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
            $containerOriginalFilePath,
            $containerOutputFilePath,
        ];


        try {
            // ★ Process ファサードを使ってコマンドを実行
            $result = Process::timeout(3600)->run($command);

            // ★ 失敗した場合に例外をスローさせる
            $result->throw();

            // 4. レコード情報の更新
//            $this->attachedFile->path = $outputStoragePath;
//            $this->attachedFile->filename = $outputFileName; // ファイル名を.pdfに更新
//            $this->attachedFile->mime = 'application/pdf'; // mime_typeではなくmimeを使用
//            $this->attachedFile->optimized = true; // optimized を true に設定

            // Log::info('Checking existence before Storage::size(): ' . Storage::disk('public')->exists($this->attachedFile->path));
            // try {
            //     $fileContent = Storage::disk('public')->get($this->attachedFile->path);
            //     Log::info('File content length: ' . strlen($fileContent));
            // } catch (\Exception $e) {
            //     Log::error('Failed to get file content: ' . $e->getMessage());
            // }

//            $this->attachedFile->size = Storage::disk('public')->size($this->attachedFile->path);
//            $this->attachedFile->save();
            // Log::info('OCR and optimization successful for file: ' . $this->attachedFile->id);
            // --- 成功時の処理 ---
            $this->attachedFile->update([
                'path' => $outputStoragePath,
                'filename' => $outputFileName,
                'mime' => 'application/pdf',
                'optimized' => true,
                'size' => Storage::disk('public')->size($outputStoragePath),
                'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING->value, // Tika再処理のためのステータス
            ]);

            // 5. Tikaによる再処理
//            $this->attachedFile->update(['status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING->value]);
            ProcessAttachedFile::dispatch($this->attachedFile);
            // Log::info('Dispatched ProcessAttachedFile for re-processing: ' . $this->attachedFile->id);

        } catch (ProcessFailedException $e) {
            // --- 失敗時の処理 ---
            Log::error('OCR and optimization failed for file ' . $this->attachedFile->id, [
                'error' => $e->getMessage(),
                'output' => $e->result->output(),
                'errorOutput' => $e->result->errorOutput(),
            ]);
            // 失敗時: status を OCR_FAILED に更新
            $this->attachedFile->update(['status' => AttachedFileStatus::OCR_FAILED->value]);
        }
    }
}
