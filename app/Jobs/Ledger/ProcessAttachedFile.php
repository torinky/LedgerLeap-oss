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
use Illuminate\Support\Facades\DB; // ★ DBファサードをインポート
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vaites\ApacheTika\Client;
use App\Helpers\AttachedFilePathHelper;

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
        $originalHashedBasename = basename($this->attachedFile->path);
        $newOriginalPath = AttachedFilePathHelper::getOriginalAttachmentPath($this->attachedFile->ledger_define_id, $originalHashedBasename);
        $currentFilePath = AttachedFilePathHelper::getAttachmentPath($this->attachedFile->ledger_define_id, $originalHashedBasename);

        // original_file_path が設定されていない場合のみ、ファイルをOriginalsに移動
        if (empty($this->attachedFile->original_file_path)) {
            try {
                if (!Storage::disk('public')->exists($currentFilePath)) {
                    Log::error('Original file not found for moving in ProcessAttachedFile: ' . $currentFilePath);
                    $this->attachedFile->update(['status' => AttachedFileStatus::TIKA_FAILED->value]);
                    return;
                }

                Storage::disk('public')->move($currentFilePath, $newOriginalPath);
                $updateData = [
                    'path' => $newOriginalPath, // path も更新
                    'original_file_path' => $newOriginalPath,
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

        // ★ トランザクションとペシミスティックロックで競合状態を防ぐ
        DB::transaction(function () {
            $ledger = Ledger::where('id', $this->attachedFile->ledger_id)->lockForUpdate()->firstOrFail();
            $ledger->refresh(); // Ensure the latest data is loaded from the database
            Log::info('ProcessAttachedFile: Inside transaction. Ledger ID: ' . $ledger->id);
            Log::info('ProcessAttachedFile: Initial ledger->content_attached (from DB): ' . json_encode($ledger->content_attached));

            // 1. Get the current content_attached from the ledger. This will be a numerically indexed array.
            $existingContentAttached = $ledger->content_attached ?? [];

            // 2. Create a temporary working array, ensuring all column IDs are present and initialized.
            //    This array will be associative, using column_id as keys.
            $workingContentAttached = [];
            $columnDefines = $ledger->define->column_define;
            foreach ($columnDefines as $columnDefine) {
                $columnId = $columnDefine->id;
                // Map the numerically indexed existing content back to column_id keys
                $workingContentAttached[$columnId] = $existingContentAttached[$columnId] ?? [];
            }
            Log::info('ProcessAttachedFile: Working content_attached (associative array by column_id): ' . json_encode($workingContentAttached));

            // 3. Update the specific column_id's content.
            $currentColumnContent = $workingContentAttached[$this->attachedFile->column_id] ?? [];
            $result = $currentColumnContent[$this->attachedFile->hashedbasename] ?? ['meta' => ['content' => '']];
            Log::info('ProcessAttachedFile: Current result for file (before Tika processing): ' . json_encode($result));
            Log::info('ProcessAttachedFile: Current result for file (before Tika processing): ' . json_encode($result));

            $filePath = Storage::disk('public')->path($this->attachedFile->path);

            try {
                $tikaClient = Client::make('tika', 9998);
                $tikaClient->setTimeout(300);

                // Tikaでテキスト抽出を試行
                $extractedText = trim($tikaClient->getText($filePath));
                $extractedMeta = $tikaClient->getMetadata($filePath);

                // Tikaから取得したメタデータを$result['meta']にマージ
                // $extractedMetaはオブジェクトなので、配列に変換してマージする
                if (is_object($extractedMeta)) {
                    foreach ($extractedMeta as $key => $value) {
                        // contentは既に処理済みなので上書きしない
                        if ($key !== 'content') {
                            $result['meta'][$key] = $value;
                        }
                    }
                }

                if (!empty($extractedText)) {
                    // テキスト抽出成功時
                    $result['meta']['content'] = $extractedText;
                    $this->attachedFile->contain_content = true;
                    $this->attachedFile->status = AttachedFileStatus::COMPLETED->value;
                    $this->attachedFile->optimized = $this->attachedFile->optimized; // optimized の値を保持

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
                        Log::info('AttachedFile path before dispatching OcrAndOptimizeFile: ' . $this->attachedFile->path);
                        OcrAndOptimizeFile::dispatch($this->attachedFile)->delay(now()->addSeconds(5));
                        Log::info('Dispatched OcrAndOptimizeFile for file: ' . $this->attachedFile->id);
                    } else {
                        // OCR対象外の場合 (ZIP, etc.): これ以上処理できないため、COMPLETED に更新して処理を正常終了
                        $this->attachedFile->status = AttachedFileStatus::COMPLETED->value;
                        $this->attachedFile->optimized = $this->attachedFile->optimized; // optimized の値を保持
                        Log::info('File is not OCR-eligible, marking as completed: ' . $this->attachedFile->id);
                    }
                }

            } catch (Exception $e) {
                Log::error('Tika service error for file ' . $this->attachedFile->id . ': ' . $e->getMessage() . '\nStack trace: ' . $e->getTraceAsString());
                $mimeType = $this->attachedFile->mime;
                if (str_starts_with($mimeType, 'application/pdf') || str_starts_with($mimeType, 'image/')) {
                    $this->attachedFile->status = AttachedFileStatus::PENDING_OCR->value;
                    OcrAndOptimizeFile::dispatch($this->attachedFile)->delay(now()->addSeconds(5));
                    Log::info('Dispatched OcrAndOptimizeFile after Tika error for file: ' . $this->attachedFile->id);
                } else {
                    $this->attachedFile->status = AttachedFileStatus::TIKA_FAILED->value;
                    Log::info('File is not OCR-eligible after Tika error, marking as tika_failed: ' . $this->attachedFile->id);
                }
            }

            // content_attached の更新
            Log::info('ProcessAttachedFile: Before storing result. hashedbasename: ' . $this->attachedFile->hashedbasename . ', result: ' . json_encode($result));
            $workingContentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] = $result;
            Log::info('ProcessAttachedFile: Updated workingContentAttached for current file: ' . json_encode($workingContentAttached));

            // Mroongaの要件に合わせて、数値インデックスの配列に戻す
            $finalContentAttached = array_values($workingContentAttached);
            Log::info('ProcessAttachedFile: Final content_attached before save (numerically indexed): ' . json_encode($finalContentAttached));

            $ledger->content_attached = $finalContentAttached;
            Log::info('ProcessAttachedFile: Ledger->content_attached before save: ' . json_encode($ledger->content_attached));
            $ledger->save();

        }); // ★ トランザクション終了

        $this->attachedFile->save();
        Log::info('ProcessAttachedFile: Final attachedFile state: ' . json_encode($this->attachedFile->toArray()));
        Log::info('ProcessAttachedFile job finished for file: ' . $this->attachedFile->id . ', status: ' . $this->attachedFile->status);
    }
}
