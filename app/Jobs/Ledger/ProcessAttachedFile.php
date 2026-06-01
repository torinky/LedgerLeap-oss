<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Embedding\VectorizeAttachedFile;
use App\Models\AttachedFile;
use App\Models\Ledger;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
// ★ DBファサードをインポート
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vaites\ApacheTika\Client;

// ★ 追加

/**
 * Tika によるテキスト抽出と、VLM/OCR ジョブのディスパッチを担当するジョブ。
 *
 * ファイルアップロード直後に実行される最初のジョブ。
 * - Tika で汎用テキスト抽出を行う
 * - ファイルタイプに応じて VLM（画像/PDF）や OCR（画像/PDF）を並列ディスパッチする
 * - OCR 完了後は自身を再実行し、Tika 再抽出を行う
 *
 * @see \App\Jobs\Ledger\ProcessVlmExtraction
 * @see \App\Jobs\Ledger\OcrAndOptimizeFile
 * @see \App\Console\Commands\Ledger\FinalizeAttachedFileProcessing
 */
class ProcessAttachedFile implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public AttachedFile $attachedFile;

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
        // ここでテナントを初期化
        tenancy()->initialize($this->attachedFile->tenant_id);

        Log::info('ProcessAttachedFile: ID: '.$this->attachedFile->id.', Status: '.$this->attachedFile->status->value);

        // ★ 既にVLM処理が完了している場合はスキップ（リトライ時の重複処理防止）
        if ($this->attachedFile->vlm_processed_at !== null) {
            Log::info('ProcessAttachedFile: File already processed by VLM, skipping: '.$this->attachedFile->id);
            if ($this->attachedFile->status !== AttachedFileStatus::COMPLETED) {
                $this->attachedFile->update(['status' => AttachedFileStatus::COMPLETED]);
            }

            return;
        }
        // Log::info('ProcessAttachedFile job started for file: ' . $this->attachedFile->id);

        // 1. オリジナルファイルの退避 (ProcessAttachedFile の責任とする)
        $originalHashedBasename = basename($this->attachedFile->path);
        $newOriginalPath = AttachedFilePathHelper::getOriginalAttachmentPath($this->attachedFile->ledger_define_id, $originalHashedBasename);
        $currentFilePath = AttachedFilePathHelper::getAttachmentPath($this->attachedFile->ledger_define_id, $originalHashedBasename);

        // original_file_path が設定されていない場合のみ、ファイルをOriginalsに移動
        if (empty($this->attachedFile->original_file_path)) {
            try {
                if (! Storage::disk('public')->exists($currentFilePath)) {
                    Log::error('Original file not found for moving in ProcessAttachedFile: '.$currentFilePath);
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
                // Log::info('Original file moved to: ' . $newOriginalPath);
            } catch (
                Exception $e) {
                    Log::error('Failed to move original file in ProcessAttachedFile: '.$e->getMessage());
                    $this->attachedFile->update(['status' => AttachedFileStatus::TIKA_FAILED->value]);

                    return;
                }
        } else {
            // original_file_path が設定されている場合、ファイルは既にOriginalsにあるか、
            // OCR処理後のファイルがAttachmentsにあるはずなので、移動は不要。
            // ここでは何もしない。
            Log::info('File already processed or moved, skipping initial move in ProcessAttachedFile for file: '.$this->attachedFile->id);
        }

        // 2. status を INITIAL_PROCESSING に更新（最終化済みの場合は上書きしない）
        $this->attachedFile->refresh();
        if (! $this->attachedFile->processing_finalized_at) {
            $this->attachedFile->update(['status' => AttachedFileStatus::INITIAL_PROCESSING->value]);
        }

        // ★ トランザクションとペシミスティックロックで競合状態を防ぐ
        DB::transaction(function () {
            $ledger = Ledger::where('id', $this->attachedFile->ledger_id)->lockForUpdate()->firstOrFail();
            $ledger->refresh(); // Ensure the latest data is loaded from the database
            Log::info('ProcessAttachedFile: Inside transaction. Ledger ID: '.$ledger->id);
            //            Log::info('ProcessAttachedFile: Initial ledger->content_attached (from DB): ' . json_encode($ledger->content_attached));

            // 1. Get the current content_attached from the ledger. This will be a numerically indexed array.
            $existingContentAttached = $ledger->content_attached ?? [];

            // 2. Create a temporary working array, ensuring all column IDs are present and initialized.
            //    This array will be associative, using column_id as keys.
            $workingContentAttached = [];
            $columnDefines = $ledger->define->column_define;
            $maxColumnId = $columnDefines->max('id');

            // columnIdで存在しないカラムも踏まえて準備しておく
            for ($i = 0; $maxColumnId > $i; $i++) {
                $workingContentAttached[$i] = [];
            }

            foreach ($columnDefines as $columnDefine) {
                $columnId = $columnDefine->id;
                // Map the numerically indexed existing content back to column_id keys
                // 既存のコンテンツが配列であることを保証
                $contentForColumn = $existingContentAttached[$columnId] ?? [];
                if (! is_array($contentForColumn)) {
                    $contentForColumn = [];
                }
                $workingContentAttached[$columnId] = $contentForColumn;
            }
            //             Log::info('ProcessAttachedFile: Working content_attached (associative array by column_id): ' . json_encode($workingContentAttached));

            // 3. Update the specific column_id's content.
            $currentColumnContent = $workingContentAttached[$this->attachedFile->column_id] ?? [];
            // 既存のコンテンツが配列であることを保証
            if (! is_array($currentColumnContent)) {
                $currentColumnContent = [];
            }
            $result = $currentColumnContent[$this->attachedFile->hashedbasename] ?? ['meta' => ['content' => '']];
            Log::info('ProcessAttachedFile: Current result for file (before Tika processing): '.json_encode($result));

            $filePath = Storage::disk('public')->path($this->attachedFile->path);

            try {
                $tikaClient = app(Client::class);
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

                // Tika処理が成功したらテキストを保存
                if (! empty($extractedText)) {
                    $result['meta']['content'] = $extractedText;
                    $this->attachedFile->contain_content = true;
                }

                // ★ Phase5: Tika処理完了マーク
                $this->attachedFile->tika_processed_at = now();

                // ★ Phase2.6: Tika完了後、即座にベクトル化（検索可能に）
                VectorizeAttachedFile::dispatch(
                    $this->attachedFile->id,
                    'tika'
                );

                // ★ Phase5: VLM/OCR並列処理の判定とディスパッチ
                if ($this->attachedFile->isVlmOrOcrTarget()) {
                    Log::info('[Phase5] File is VLM/OCR target, dispatching parallel processing: '.$this->attachedFile->id);
                    $this->dispatchParallelProcessing();
                }
                // Phase2.6: VLM/OCR対象外でもベクトル化は実行済み（上記）

                // メタデータも更新 (extractedMetaがオブジェクトの場合のみ)
                $mime = null;
                if (is_object($extractedMeta)) {
                    if (method_exists($extractedMeta, 'get')) {
                        $mime = $extractedMeta->get('mime');
                    } elseif (isset($extractedMeta->mime)) {
                        $mime = $extractedMeta->mime;
                    }
                }

                if (! empty($mime)) {
                    $this->attachedFile->mime = $mime;
                }

            } catch (Exception $e) {
                Log::error('Tika service error for file '.$this->attachedFile->id.': '.$e->getMessage().'\nStack trace: '.$e->getTraceAsString());

                // ★ Phase5: Tikaエラー時もTika処理完了マーク（空のコンテンツで）
                $this->attachedFile->tika_processed_at = now();

                // ★ Phase5: VLM/OCR対象ならエラー時も並列処理を試みる
                if ($this->attachedFile->isVlmOrOcrTarget()) {
                    Log::info('[Phase5 Fallback] Tika failed but dispatching parallel processing: '.$this->attachedFile->id);
                    $this->dispatchParallelProcessing();
                } else {
                    // VLM/OCR対象外の場合はTIKA_FAILEDとして終了
                    $this->attachedFile->processing_finalized_at = now();
                    $this->attachedFile->finalized_source = 'tika';
                    $this->attachedFile->status = AttachedFileStatus::TIKA_FAILED->value;
                    Log::info('[Phase5] Non-VLM/OCR file with Tika error, marking as failed: '.$this->attachedFile->id);
                }
            }

            // content_attached の更新
            // Log::info('ProcessAttachedFile: Before storing result. hashedbasename: ' . $this->attachedFile->hashedbasename . ', result: ' . json_encode($result));
            $workingContentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] = $result;
            // Log::info('ProcessAttachedFile: Updated workingContentAttached for current file: ' . json_encode($workingContentAttached));

            // Mroongaの要件に合わせて、数値インデックスの配列に戻す
            //             $finalContentAttached = array_values($workingContentAttached);
            //             Log::info('ProcessAttachedFile: Final content_attached before save (numerically indexed): ' . json_encode($finalContentAttached));

            $ledger->content_attached = $workingContentAttached;

            // ログ出力用にcontent_attachedのテキスト内容を短縮する
            $loggableContent = $ledger->content_attached;
            if (is_array($loggableContent)) {
                // 配列を再帰的に処理し、'content'キーの値が文字列なら短縮する
                array_walk_recursive($loggableContent, function (&$value, $key) {
                    if ($key === 'content' && is_string($value)) {
                        $value = mb_strimwidth($value, 0, 100, '...'); // 先頭100文字に省略
                    }
                });
            }
            Log::info('ProcessAttachedFile: Ledger->content_attached before save (content truncated): '.json_encode($loggableContent));

            $ledger->timestamps = false;
            $ledger->save();

        }); // ★ トランザクション終了

        $this->attachedFile->save();
        Log::info('ProcessAttachedFile: attachedFile saved successfully.');

        // ★ サムネイル生成対象か判定し、ジョブをディスパッチ
        $thumbnailMime = $this->attachedFile->original_mime_type ?? $this->attachedFile->mime;
        Log::debug('[ProcessAttachedFile] Checking MIME type for thumbnail generation: '.$thumbnailMime);
        if (str_starts_with($thumbnailMime, 'image/')) {
            GenerateThumbnail::dispatch($this->attachedFile->id);
            Log::info('[ProcessAttachedFile] Dispatched GenerateThumbnail job for AttachedFile ID: '.$this->attachedFile->id);
        }

        // Log::info('ProcessAttachedFile: Final attachedFile state: ' . json_encode($this->attachedFile->toArray()));
        Log::info('ProcessAttachedFile job finished for file: '.$this->attachedFile->id
            .', status: '.$this->attachedFile->status->value);
    }

    /**
     * Determines if the attached file should be processed by the VLM service.
     */
    private function shouldProcessWithVlm(AttachedFile $file): bool
    {
        if (! config('vlm.enabled')) {
            return false;
        }

        $mimeType = $file->mime;
        $isVlmTargetMime = str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'application/pdf');

        if (! $isVlmTargetMime) {
            return false;
        }

        if ($file->vlm_processed_at !== null) {
            return false;
        }

        return true;
    }

    /**
     * ★ Phase5: Dispatch VLM and OCR processing in parallel.
     */
    private function dispatchParallelProcessing(): void
    {
        $vlmEnabled = config('vlm.enabled', false);
        $ocrEnabled = true; // OCR is always available

        // VLMジョブをディスパッチ（vlmキュー）
        if ($vlmEnabled) {
            // ★ 追加: 既に処理済み（成功or失敗）ならスキップ
            if ($this->attachedFile->vlm_processed_at === null && $this->attachedFile->vlm_failed_at === null) {
                ProcessVlmExtraction::dispatch($this->attachedFile)
                    ->onQueue('vlm');
                Log::info('[Phase5] Dispatched VLM job to vlm queue for file: '.$this->attachedFile->id);
            } else {
                Log::info('[Phase5] VLM already processed/failed, skipping dispatch for file: '.$this->attachedFile->id);
            }
        } else {
            // VLMが無効な場合は即座に失敗マーク（まだ設定されていない場合のみ）
            if ($this->attachedFile->vlm_failed_at === null) {
                $this->attachedFile->vlm_failed_at = now();
                Log::info('[Phase5] VLM disabled, marking as failed for file: '.$this->attachedFile->id);
            }
        }

        // OCRジョブをディスパッチ（ocrキュー、2秒遅延）
        if ($ocrEnabled) {
            // ★ 追加: 既に処理済み（成功or失敗）ならスキップ
            if ($this->attachedFile->ocr_processed_at === null && $this->attachedFile->ocr_failed_at === null) {
                OcrAndOptimizeFile::dispatch($this->attachedFile)
                    ->delay(now()->addSeconds(2))
                    ->onQueue('ocr');
                Log::info('[Phase5] Dispatched OCR job to ocr queue (2s delay) for file: '.$this->attachedFile->id);
            } else {
                Log::info('[Phase5] OCR already processed/failed, skipping dispatch for file: '.$this->attachedFile->id);
            }
        } else {
            // OCRが無効な場合は即座に失敗マーク（通常ありえない）
            if ($this->attachedFile->ocr_failed_at === null) {
                $this->attachedFile->ocr_failed_at = now();
                Log::info('[Phase5] OCR disabled, marking as failed for file: '.$this->attachedFile->id);
            }
        }

        // ステータスは変更しない（Phase4のステータスを保持）
        // 最終化処理がスケジューラーで実行される
    }
}
