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
use Illuminate\Support\Facades\DB;

// ★ DBファサードをインポート
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vaites\ApacheTika\Client;
use App\Helpers\AttachedFilePathHelper;

use Stancl	enancy\Contracts\TenantAware;
use Stancl\Tenancy\Concerns\HasATenant;

class ProcessAttachedFile implements ShouldQueue, TenantAware
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasATenant;

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
        Log::info('ProcessAttachedFile: ID: ' . $this->attachedFile->id . ', Status: ' . $this->attachedFile->status->value);
        // Log::info('ProcessAttachedFile job started for file: ' . $this->attachedFile->id);

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
                // Log::info('Original file moved to: ' . $newOriginalPath);
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
//            Log::info('ProcessAttachedFile: Initial ledger->content_attached (from DB): ' . json_encode($ledger->content_attached));

            // 1. Get the current content_attached from the ledger. This will be a numerically indexed array.
            $existingContentAttached = $ledger->content_attached ?? [];

            // 2. Create a temporary working array, ensuring all column IDs are present and initialized.
            //    This array will be associative, using column_id as keys.
            $workingContentAttached = [];
            $columnDefines = $ledger->define->column_define;
            $maxColumnId = $columnDefines->max('id');

            //columnIdで存在しないカラムも踏まえて準備しておく
            for ($i = 0; $maxColumnId > $i; $i++) {
                $workingContentAttached[$i] = [];
            }

            foreach ($columnDefines as $columnDefine) {
                $columnId = $columnDefine->id;
                // Map the numerically indexed existing content back to column_id keys
                // 既存のコンテンツが配列であることを保証
                $contentForColumn = $existingContentAttached[$columnId] ?? [];
                if (!is_array($contentForColumn)) {
                    $contentForColumn = [];
                }
                $workingContentAttached[$columnId] = $contentForColumn;
            }
//             Log::info('ProcessAttachedFile: Working content_attached (associative array by column_id): ' . json_encode($workingContentAttached));

            // 3. Update the specific column_id's content.
            $currentColumnContent = $workingContentAttached[$this->attachedFile->column_id] ?? [];
            // 既存のコンテンツが配列であることを保証
            if (!is_array($currentColumnContent)) {
                $currentColumnContent = [];
            }
            $result = $currentColumnContent[$this->attachedFile->hashedbasename] ?? ['meta' => ['content' => '']];
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
                    Log::info('Tika text extraction successful for file: ' . $this->attachedFile->id);
                } else {
                    // テキスト抽出失敗時
                    $mimeType = $this->attachedFile->mime;
                    if ((str_starts_with($mimeType, 'application/pdf') || str_starts_with($mimeType, 'image/')) && !$this->attachedFile->optimized) {
                        // OCR対象の場合 (PDF/画像) かつまだ最適化されていない場合: PENDING_OCR に更新し、OcrAndOptimizeFile ジョブをディスパッチ
                        $this->attachedFile->status = AttachedFileStatus::PENDING_OCR->value;
                        OcrAndOptimizeFile::dispatch($this->attachedFile)->delay(now()->addSeconds(5));
                        Log::info('Dispatched OcrAndOptimizeFile for file: ' . $this->attachedFile->id);
                    } else {
                        // OCR対象外の場合 (ZIP, etc.) または既に最適化されている場合: COMPLETED に更新して処理を正常終了
                        // optimized が true の場合、OCR処理は完了しているため、Tikaがテキストを抽出できなくてもCOMPLETEDとする
                        $this->attachedFile->status = AttachedFileStatus::COMPLETED->value;
                        Log::info('File is not OCR-eligible or already optimized, marking as completed: ' . $this->attachedFile->id);
                    }
                }

                // メタデータも更新 (extractedMetaがオブジェクトの場合のみ)
                if (is_object($extractedMeta) && !empty($extractedMeta->mime)) {
                    $this->attachedFile->mime = $extractedMeta->mime;
                }

            } catch (Exception $e) {
                Log::error('Tika service error for file ' . $this->attachedFile->id . ': ' . $e->getMessage() . '
Stack trace: ' . $e->getTraceAsString());
                $mimeType = $this->attachedFile->mime;
                if ((str_starts_with($mimeType, 'application/pdf') || str_starts_with($mimeType, 'image/')) && !$this->attachedFile->optimized) {
                    $this->attachedFile->status = AttachedFileStatus::PENDING_OCR->value;
                    OcrAndOptimizeFile::dispatch($this->attachedFile)->delay(now()->addSeconds(5));
                    Log::info('Dispatched OcrAndOptimizeFile after Tika error for file: ' . $this->attachedFile->id);
                } else {
                    // OCR対象外の場合、または既に最適化されている場合 (TikaがOCR済みPDFからテキスト抽出できなかったケース)
                    // Tikaエラーが発生したが、OCR処理は完了している（optimized=true）場合はCOMPLETEDとする
                    $this->attachedFile->status = $this->attachedFile->optimized ? AttachedFileStatus::COMPLETED->value : AttachedFileStatus::TIKA_FAILED->value;
                    Log::info('File is not OCR-eligible or already optimized after Tika error, marking as completed/failed: ' . $this->attachedFile->id);
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
            Log::info('ProcessAttachedFile: Ledger->content_attached before save (content truncated): ' . json_encode($loggableContent));

            $ledger->save();

        }); // ★ トランザクション終了

        $this->attachedFile->save();
        // Log::info('ProcessAttachedFile: Final attachedFile state: ' . json_encode($this->attachedFile->toArray()));
        Log::info('ProcessAttachedFile job finished for file: ' . $this->attachedFile->id
            . ', status: ' . $this->attachedFile->status->value);
    }
}
