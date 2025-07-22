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

        // ▼▼▼ ソースファイルのパスではなく、コンテンツを取得するように変更 ▼▼▼
        $sourcePathForLog = $attachedFile->path; // ログ出力用にパスを保持
        $thumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath($attachedFile->hashedbasename);

        // サムネイルが既に存在する場合はスキップ
        if (Storage::disk('public')->exists($thumbnailPath)) {
            Log::info("[GenerateThumbnail] Thumbnail already exists for AttachedFile ID: {$this->attachedFileId}. Skipping generation.");
            $attachedFile->update(['status' => AttachedFileStatus::COMPLETED->value]);
            return;
        }

        // 画像ファイル以外はスキップ
        if (!Str::startsWith($attachedFile->mime, 'image/')) {
            Log::info("[GenerateThumbnail] File is not an image (MIME: {$attachedFile->mime}). Skipping thumbnail generation for ID: {$this->attachedFileId}.");
            $attachedFile->update(['status' => AttachedFileStatus::COMPLETED->value]);
            return;
        }

        try {
            // ▼▼▼ ファイルパスではなく、ファイルコンテンツを Storage から取得 ▼▼▼
            if (!Storage::disk('public')->exists($attachedFile->path)) {
                Log::error("[GenerateThumbnail] Source file not found at path: {$sourcePathForLog}");
                $attachedFile->update(['status' => AttachedFileStatus::THUMBNAIL_FAILED->value]);
                return;
            }
            $sourceContent = Storage::disk('public')->get($attachedFile->path);

            $img = $imageManager->make($sourceContent);

            $image = $img->resize(200, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            // ▼▼▼ Interventionのsave()の代わりに、encode()してStorage::put()で保存 ▼▼▼
            $encodedImage = $image->encode('jpg'); // 必要に応じてフォーマットを変更
            Storage::disk('public')->put($thumbnailPath, (string) $encodedImage);

            // サムネイル生成成功
            $attachedFile->update(['status' => AttachedFileStatus::COMPLETED->value]);
            Log::info("[GenerateThumbnail] Thumbnail successfully generated for AttachedFile ID: {$this->attachedFileId}. Path: {$thumbnailPath}");

        } catch (Throwable $e) {
            // ... (失敗時の処理は変更なし)
            $attachedFile->update(['status' => AttachedFileStatus::THUMBNAIL_FAILED->value]);
            Log::error("[GenerateThumbnail] Failed to generate thumbnail for AttachedFile ID: {$this->attachedFileId}. Error: {$e->getMessage()}", ['exception' => $e]);
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