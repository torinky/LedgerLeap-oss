<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use App\Helpers\AttachedFilePathHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class GenerateThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $attachedFileId;

    // ジョブの最大試行回数を設定
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(int $attachedFileId)
    {
        $this->attachedFileId = $attachedFileId;
    }

    /**
     * Execute the job.
     */
    public function handle(ImageManager $imageManager): void
    {
        Log::info("[GenerateThumbnail] Job started for AttachedFile ID: {$this->attachedFileId}");

        $attachedFile = AttachedFile::find($this->attachedFileId);

        if (!$attachedFile) {
            Log::warning("[GenerateThumbnail] AttachedFile not found for ID: {$this->attachedFileId}. Aborting job.");
            return;
        }

        // サムネイル生成対象のファイルパス
        $sourcePath = Storage::disk('public')->path($attachedFile->path);
        $thumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath($attachedFile->hashedbasename);
        $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);

        // サムネイルが既に存在する場合はスキップ
        if (Storage::disk('public')->exists($thumbnailPath)) {
            Log::info("[GenerateThumbnail] Thumbnail already exists for AttachedFile ID: {$this->attachedFileId}. Skipping generation.");
            // 念のためステータスをCOMPLETEDに更新
            $attachedFile->update(['status' => AttachedFileStatus::COMPLETED]);
            return;
        }

        // 画像ファイル以外はサムネイル生成を試みない
        if (!Str::startsWith($attachedFile->mime, 'image/')) {
            Log::info("[GenerateThumbnail] File is not an image (MIME: {$attachedFile->mime}). Skipping thumbnail generation for ID: {$this->attachedFileId}.");
            // 非画像ファイルの場合もCOMPLETEDとして処理を終了
            $attachedFile->update(['status' => AttachedFileStatus::COMPLETED]);
            return;
        }

        try {
            // サムネイル保存ディレクトリが存在しない場合は作成
            if (!is_dir(dirname($thumbnailFullPath))) {
                if (!mkdir($concurrentDirectory = dirname($thumbnailFullPath), 0777, true) && !is_dir($concurrentDirectory)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
            }

            $img = $imageManager->make($sourcePath);

            // 画像のリサイズ (幅を200pxに、アスペクト比を維持)
            $image = $img->resize(200, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            $image->save($thumbnailFullPath);

            // サムネイル生成成功
            $attachedFile->update(['status' => AttachedFileStatus::COMPLETED]);
            Log::info("[GenerateThumbnail] Thumbnail successfully generated for AttachedFile ID: {$this->attachedFileId}. Path: {$thumbnailPath}");

        } catch (Throwable $e) {
            // サムネイル生成失敗
            $attachedFile->update(['status' => AttachedFileStatus::THUMBNAIL_FAILED]);
            Log::error("[GenerateThumbnail] Failed to generate thumbnail for AttachedFile ID: {$this->attachedFileId}. Error: {$e->getMessage()}", ['exception' => $e]);
            // ジョブを再キューに入れる (triesで制御される)
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("[GenerateThumbnail] Job failed for AttachedFile ID: {$this->attachedFileId}. Max attempts reached or unhandled exception. Error: {$exception->getMessage()}", ['exception' => $exception]);
        $attachedFile = AttachedFile::find($this->attachedFileId);
        if ($attachedFile) {
            $attachedFile->update(['status' => AttachedFileStatus::THUMBNAIL_FAILED]);
        }
    }
}