<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Models\AttachedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class GenerateThumbnail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $attachedFileId;

    public int $uniqueFor = 7200;

    // ジョブの最大試行回数を設定
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(int $attachedFileId)
    {
        $this->attachedFileId = $attachedFileId;
    }

    public function uniqueId(): string
    {
        return (string) $this->attachedFileId;
    }

    /**
     * Execute the job.
     */
    public function handle(ImageManager $imageManager): void
    {
        $attachedFile = tenancy()->central(function () {
            return AttachedFile::find($this->attachedFileId);
        });

        if (! $attachedFile) {
            Log::warning("[GenerateThumbnail] AttachedFile not found for ID: {$this->attachedFileId}. Aborting job.");

            return;
        }

        // ここでテナントを初期化
        tenancy()->initialize($attachedFile->tenant_id);

        $tenantId = tenancy()?->tenant->id ?? 'No Tenant';
        Log::info("[GenerateThumbnail] Current Tenant ID: {$tenantId}");
        Log::info("[GenerateThumbnail] Job started for AttachedFile ID: {$this->attachedFileId}");

        // ▼▼▼ ソースファイルのパスではなく、コンテンツを取得するように変更 ▼▼▼
        $sourcePath = $attachedFile->path; // デフォルトは現在のパス
        $sourcePathForLog = $attachedFile->path; // ログ出力用にパスを保持
        $thumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath(
            $attachedFile->hashedbasename,
            $attachedFile->tenant_id,
        );

        // サムネイルが既に存在する場合はスキップ
        if (Storage::disk('public')->exists($thumbnailPath)) {
            Log::info("[GenerateThumbnail] Thumbnail already exists for AttachedFile ID: {$this->attachedFileId}. Skipping generation.");
            // ステータス更新ガード: 並列処理中などは更新しない
            if (! in_array($attachedFile->status, [
                AttachedFileStatus::PENDING_OCR,
                AttachedFileStatus::OCR_PROCESSING,
                AttachedFileStatus::PENDING_VLM,
                AttachedFileStatus::VLM_PROCESSING,
                AttachedFileStatus::PARALLEL_PROCESSING,
            ])) {
                $attachedFile->update(['status' => AttachedFileStatus::COMPLETED->value]);
            } else {
                Log::info("[GenerateThumbnail] Status update skipped due to processing status: {$attachedFile->status->value}");
            }

            return;
        }

        // 画像ファイルかどうか判定（フォールバックロジック含む）
        $isImage = Str::startsWith($attachedFile->mime, 'image/');

        // 現在のファイルが画像でなく、かつ元画像が存在する場合はそちらを使用
        if (! $isImage && $attachedFile->original_file_path && Str::startsWith($attachedFile->original_mime_type, 'image/')) {
            $sourcePath = $attachedFile->original_file_path;
            $isImage = true;
            Log::info("[GenerateThumbnail] Using original file as source: {$sourcePath}");
        }

        // 画像ファイル以外はスキップ
        if (! $isImage) {
            Log::info("[GenerateThumbnail] File is not an image (MIME: {$attachedFile->mime}). Skipping thumbnail generation for ID: {$this->attachedFileId}.");
            // ステータス更新ガード: 並列処理中などは更新しない
            if (! in_array($attachedFile->status, [
                AttachedFileStatus::PENDING_OCR,
                AttachedFileStatus::OCR_PROCESSING,
                AttachedFileStatus::PENDING_VLM,
                AttachedFileStatus::VLM_PROCESSING,
                AttachedFileStatus::PARALLEL_PROCESSING,
            ])) {
                $attachedFile->update(['status' => AttachedFileStatus::COMPLETED->value]);
            } else {
                Log::info("[GenerateThumbnail] Status update skipped due to processing status: {$attachedFile->status->value}");
            }

            return;
        }

        try {
            // ▼▼▼ ファイルパスではなく、ファイルコンテンツを Storage から取得 ▼▼▼
            if (! Storage::disk('public')->exists($sourcePath)) {
                Log::error("[GenerateThumbnail] Source file not found at path: {$sourcePath}");
                $attachedFile->update(['status' => AttachedFileStatus::THUMBNAIL_FAILED->value]);

                return;
            }
            $sourceContent = Storage::disk('public')->get($sourcePath);

            $img = $imageManager->make($sourceContent);

            $image = $img->resize(200, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            // ▼▼▼ Interventionのsave()の代わりに、encode()してStorage::put()で保存 ▼▼▼
            $encodedImage = $image->encode('jpg'); // 必要に応じてフォーマットを変更
            Storage::disk('public')->put($thumbnailPath, (string) $encodedImage);

            // サムネイル生成成功
            // ステータス更新ガード: 並列処理中などは更新しない
            if (! in_array($attachedFile->status, [
                AttachedFileStatus::PENDING_OCR,
                AttachedFileStatus::OCR_PROCESSING,
                AttachedFileStatus::PENDING_VLM,
                AttachedFileStatus::VLM_PROCESSING,
                AttachedFileStatus::PARALLEL_PROCESSING,
            ])) {
                $attachedFile->update(['status' => AttachedFileStatus::COMPLETED->value]);
            } else {
                Log::info("[GenerateThumbnail] Status update skipped due to processing status: {$attachedFile->status->value}");
            }
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
        Log::error(
            "[GenerateThumbnail] Failed to generate thumbnail for AttachedFile ID: {$this->attachedFileId}. Max attempts reached or unhandled exception. Error: {$exception->getMessage()}",
            ['exception' => $exception]
        );
        $attachedFile = AttachedFile::find($this->attachedFileId);
        if ($attachedFile) {
            $attachedFile->update(['status' => AttachedFileStatus::THUMBNAIL_FAILED->value]);
        }
    }
}
