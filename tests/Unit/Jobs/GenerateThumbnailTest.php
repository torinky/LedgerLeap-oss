<?php

namespace Tests\Unit\Jobs;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Models\AttachedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Mockery;
use Tests\TestCase;
use Throwable;

class GenerateThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Bus::fake();
    }

    #[Test]
    public function it_generates_a_thumbnail_for_an_image_file()
    {
        // --- Arrange ---
        // ★「成功」ログが1回呼ばれることだけを検証し、他のinfoログは無視する
        Log::shouldReceive('info')->with(Mockery::pattern('/Thumbnail successfully generated/'))->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $mockImage = Mockery::mock(Image::class);
        $mockImage->shouldReceive('resize')->andReturnSelf();
        $mockImage->shouldReceive('encode')->with('jpg')->andReturnSelf();
        $mockImage->shouldReceive('__toString')->andReturn('dummy_image_content');

        $mockImageManager = Mockery::mock(ImageManager::class);
        $mockImageManager->shouldReceive('make')->andReturn($mockImage);

        $this->app->instance(ImageManager::class, $mockImageManager);

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/jpeg',
            'path' => 'attachments/test_image.jpg',
            'hashedbasename' => 'some_hash.jpg',
            'status' => AttachedFileStatus::INITIAL_PROCESSING,
        ]);
        Storage::disk('public')->put($attachedFile->path, 'dummy content');

        // --- Act ---
        $job = new GenerateThumbnail($attachedFile->id);
        $job->handle($this->app->make(ImageManager::class));

        // --- Assert ---
        $expectedThumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath($attachedFile->hashedbasename);
        Storage::disk('public')->assertExists($expectedThumbnailPath);

        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);
    }

    #[Test]
    public function it_aborts_if_attached_file_is_not_found()
    {
        // --- Arrange ---
        $nonExistentFileId = 999;

        // ★「見つからない」というwarningログが1回呼ばれることだけを検証し、他のログは無視する
        Log::shouldReceive('warning')->with(Mockery::pattern('/AttachedFile not found/'))->once();
        Log::shouldReceive('info')->zeroOrMoreTimes(); // 他のinfoログは無視

        // --- Act ---
        $job = new GenerateThumbnail($nonExistentFileId);
        $job->handle($this->app->make(ImageManager::class));
    }

    #[Test]
    public function it_updates_status_to_failed_on_exception()
    {
        // --- Arrange ---
        // 「失敗」というerrorログが1回呼ばれることを期待
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) {
                // メッセージの内容をより柔軟にチェック
                return str_contains($message, 'Failed to generate thumbnail');
            });
        // 他のログは無視
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $mockImageManager = Mockery::mock(ImageManager::class);
        $mockImageManager->shouldReceive('make')->andThrow(new \Exception('Image processing failed'));
        $this->app->instance(ImageManager::class, $mockImageManager);

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/jpeg',
            'path' => 'attachments/test_image.jpg',
            'status' => AttachedFileStatus::INITIAL_PROCESSING,
        ]);
        Storage::disk('public')->put($attachedFile->path, 'dummy content');

        // --- Act ---
        $caughtException = null;
        try {
            $job = new GenerateThumbnail($attachedFile->id);
            $job->handle($this->app->make(ImageManager::class));
        } catch (Throwable $e) {
            $caughtException = $e;
        }

        // --- Assert ---
        // 1. 意図した例外がスローされたことを確認
        $this->assertNotNull($caughtException, 'Expected an exception to be thrown, but it was not.');
        $this->assertInstanceOf(\Exception::class, $caughtException);
        $this->assertEquals('Image processing failed', $caughtException->getMessage());

        // 2. データベースの状態が正しく更新されたことを確認
        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::THUMBNAIL_FAILED->value,
        ]);

        // 3. Mockeryによるログの検証はテスト終了時に自動で行われる
    }
    #[Test]
    public function it_skips_generation_for_non_image_files()
    {
        // --- Arrange ---
        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::INITIAL_PROCESSING,
        ]);

        // ★「画像ではない」というinfoログが1回呼ばれることだけを検証し、他のinfoログは無視する
        Log::shouldReceive('info')->with(Mockery::pattern('/File is not an image/'))->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        // --- Act ---
        $job = new GenerateThumbnail($attachedFile->id);
        $job->handle($this->app->make(ImageManager::class));

        // --- Assert ---
        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);
    }
}