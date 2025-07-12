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

        // 1. status を INITIAL_PROCESSING に更新
        $this->attachedFile->update(['status' => AttachedFileStatus::INITIAL_PROCESSING->value]);

        $ledger = Ledger::where('id', $this->attachedFile->ledger_id)->firstOrFail();
        $contentAttached = $ledger->content_attached;
        $result = $contentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] ?? (object)['meta' => ['content' => '']];

        $filePath = storage_path('app/' . $this->attachedFile->path);

        try {
            $tikaClient = Client::make('tika', 9998);
            $tikaClient->setTimeout(120);

            // Tikaでテキスト抽出を試行
            $extractedText = trim($tikaClient->getText($filePath));
            $extractedMeta = $tikaClient->getMetadata($filePath);

            if (!empty($extractedText)) {
                // テキスト抽出成功時
                $result->meta->content = $extractedText;
                $this->attachedFile->contain_content = true;
                $this->attachedFile->status = AttachedFileStatus::COMPLETED->value;
                Log::info('Tika text extraction successful for file: ' . $this->attachedFile->id);
            } else {
                // テキスト抽出失敗時
                Log::info('Tika text extraction failed for file: ' . $this->attachedFile->id . '. Checking MIME type for OCR.');
                $mimeType = $this->attachedFile->mime_type;

                if (str_starts_with($mimeType, 'application/pdf') || str_starts_with($mimeType, 'image/')) {
                    // OCR対象の場合 (PDF/画像): PENDING_OCR に更新し、OcrAndOptimizeFile ジョブをディスパッチ
                    $this->attachedFile->status = AttachedFileStatus::PENDING_OCR->value;
                    OcrAndOptimizeFile::dispatch($this->attachedFile);
                    Log::info('Dispatched OcrAndOptimizeFile for file: ' . $this->attachedFile->id);
                } else {
                    // OCR対象外の場合 (ZIP, etc.): これ以上処理できないため、COMPLETED に更新して処理を正常終了
                    $this->attachedFile->status = AttachedFileStatus::COMPLETED->value;
                    Log::info('File is not OCR-eligible, marking as completed: ' . $this->attachedFile->id);
                }
            }

            // メタデータも更新
            if (!empty($extractedMeta->mime)) {
                $this->attachedFile->mime = $extractedMeta->mime;
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
        Log::info('ProcessAttachedFile job finished for file: ' . $this->attachedFile->id . ', status: ' . $this->attachedFile->status->value);
    }
}
