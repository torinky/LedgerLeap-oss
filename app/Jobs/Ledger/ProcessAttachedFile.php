<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use App\Models\Ledger;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vaites\ApacheTika\Client;

class ProcessAttachedFile implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        Log::info('ProcessAttachedFile job started for file: ' . $this->attachedFile->id);

        // 1. オリジナルファイルの退避 (ProcessAttachedFile の責任とする)
        $originalDir = 'public/Ledger/Attachments/Originals/';
        $currentFilePath = str_replace('public/', '', $this->attachedFile->path);

        // original_file_path が設定されていない場合のみ、ファイルをOriginalsに移動
        if (empty($this->attachedFile->original_file_path)) {
            $newOriginalPath = $originalDir . basename($currentFilePath);
            try {
                if (!Storage::disk('public')->exists($currentFilePath)) {
                    Log::error('Original file not found for moving in ProcessAttachedFile: ' . $currentFilePath);
                    $this->attachedFile->update(['status' => AttachedFileStatus::TIKA_FAILED->value]);
                    return;
                }

                Storage::disk('public')->move($currentFilePath, str_replace('public/', '', $newOriginalPath));
                $updateData = [
                    'path' => str_replace('public/', '', $newOriginalPath), // path も更新
                    'original_file_path' => str_replace('public/', '', $newOriginalPath),
                    'original_mime_type' => $this->attachedFile->mime,
                ];
                $this->attachedFile->update($updateData);
                $this->attachedFile->refresh(); // モデルをリロードして最新の状態を反映
                Log::info('Original file moved to: ' . $newOriginalPath);
            } catch (\Exception $e) {
                Log::error('Failed to move original file in ProcessAttachedFile: ' . $e->getMessage());
                $this->attachedFile->update(['status' => AttachedFileStatus::TIKA_FAILED->value]);
                return;
            }
        } else {
            // original_file_path が設定されている場合、ファイルは既にOriginalsにあるか、
            // OCR処理後のファイルがAttachmentsにあるはずなので、移動は不要。
            // ここでは何もしない。
            Log::info('File already processed or moved, skipping initial move in ProcessAttachedFile for file: ' . $this->attachedFile->id);
        }

        // 2. status を INITIAL_PROCESSING に更新
        $this->attachedFile->update(['status' => AttachedFileStatus::INITIAL_PROCESSING->value]);

        $ledger = Ledger::where('id', $this->attachedFile->ledger_id)->firstOrFail();
        $contentAttached = $ledger->content_attached;
        $result = $contentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] ?? ['meta' => ['content' => '']];

        $filePath = storage_path('app/public/' . str_replace('public/', '', $this->attachedFile->path));

        try {
            $tikaClient = Client::make('tika', 9998);
            $tikaClient->setTimeout(120);

            // Tikaでテキスト抽出を試行
            $extractedText = trim($tikaClient->getText($filePath));
            $extractedMeta = $tikaClient->getMetadata($filePath);

            if (!empty($extractedText)) {
                // テキスト抽出成功時
                $result['meta']['content'] = $extractedText;
                $this->attachedFile->contain_content = true;
                $this->attachedFile->status = AttachedFileStatus::COMPLETED->value;

                // メタデータも更新
                if (!empty($extractedMeta->mime)) {
                    $this->attachedFile->mime = $extractedMeta->mime;
                }
                Log::info('Tika text extraction successful for file: ' . $this->attachedFile->id);
            } else {
                // テキスト抽出失敗時
                Log::info('Tika text extraction failed for file: ' . $this->attachedFile->id . '. Checking MIME type for OCR.');
                $mimeType = $this->attachedFile->mime;
                Log::info('MIME Type for OCR check: ' . $mimeType);
                Log::info('Is PDF: ' . (str_starts_with($mimeType, 'application/pdf') ? 'true' : 'false'));
                Log::info('Is Image: ' . (str_starts_with($mimeType, 'image/') ? 'true' : 'false'));

                if (str_starts_with($mimeType, 'application/pdf') || str_starts_with($mimeType, 'image/')) {
                    // OCR対象の場合 (PDF/画像): PENDING_OCR に更新し、OcrAndOptimizeFile ジョブをディスパッチ
                    $this->attachedFile->status = AttachedFileStatus::PENDING_OCR->value;
                    $this->attachedFile->save(); // ステータス変更を保存
                    Log::info('AttachedFile path before dispatching OcrAndOptimizeFile: ' . $this->attachedFile->path);
                    OcrAndOptimizeFile::dispatch($this->attachedFile)->delay(now()->addSeconds(5));
                    Log::info('Dispatched OcrAndOptimizeFile for file: ' . $this->attachedFile->id);
                } else {
                    // OCR対象外の場合 (ZIP, etc.): これ以上処理できないため、COMPLETED に更新して処理を正常終了
                    $this->attachedFile->status = AttachedFileStatus::COMPLETED->value;
                    Log::info('File is not OCR-eligible, marking as completed: ' . $this->attachedFile->id);
                }
            }

        } catch (Exception $e) {
            // Tikaサービスエラー時
            $this->attachedFile->status = AttachedFileStatus::TIKA_FAILED->value;
            Log::error('Tika service error for file ' . $this->attachedFile->id . ': ' . $e->getMessage());
        }

        // content_attached の更新
        $contentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] = $result;
        $ledger->content_attached = $contentAttached;
        $ledger->save();

        $this->attachedFile->save();
        Log::info('ProcessAttachedFile job finished for file: ' . $this->attachedFile->id . ', status: ' . $this->attachedFile->status);
    }
}
