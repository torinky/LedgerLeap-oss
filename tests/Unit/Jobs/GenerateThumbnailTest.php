<?php

namespace tests\Unit\Jobs;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class GenerateThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Storage ファサードをモック
        Storage::fake('public');

        // Image ファサードをモック
        Image::shouldReceive('make')->zeroOrMoreTimes()->andReturnUsing(function ($path) {
            $mockImage = Mockery::mock('Intervention\Image\Image');
            $mockImage->shouldReceive('resize')->andReturnSelf();
            $mockImage->shouldReceive('save')->andReturnUsing(function ($path) {
                // $path は Storage::disk('public')->path($thumbnailPath) で絶対パスになる
                // Storage::put は相対パスを期待するので、Storage::disk('public')->path() の結果から
                // Storage::disk('public')->path('') を取り除いて相対パスにする
                $relativePath = str_replace(Storage::disk('public')->path(''), '', $path);
                Storage::disk('public')->put($relativePath, 'mock thumbnail content');
                return true;
            });
            return $mockImage;
        });

        Log::shouldReceive('info')->zeroOrMoreTimes(); // Allow info logs without specific expectations
        Log::shouldReceive('error')->zeroOrMoreTimes(); // Allow error logs without specific expectations
    }

    /** @test */
    public function it_dispatches_generate_thumbnail_job_on_attached_file_creation(): void
    {
        Bus::fake();

        $attachedFile = AttachedFile::factory()->image()->create();

        Bus::assertDispatched(GenerateThumbnail::class, function ($job) use ($attachedFile) {
            return $job->attachedFileId === $attachedFile->id;
        });
    }

    /** @test */
    public function it_generates_thumbnail_for_image_file(): void
    {
        // 準備
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'mime' => 'image/jpeg',
            'path' => 'public/Ledger/Attachments/' . $ledgerDefine->id . '/test_image.jpg',
            'status' => AttachedFileStatus::UPLOADED,
            'hashedbasename' => 'test_image.jpg', // Ensure hashedbasename matches extension
        ]);

        // ダミーの画像ファイルを作成
        Storage::disk('public')->put($attachedFile->path, 'dummy image content');

        // 実行
        (new GenerateThumbnail($attachedFile->id))->handle($this->app->make(ImageManager::class));

        // 検証
        Storage::disk('public')->assertExists('public/Ledger/thumbs/' . $attachedFile->hashedbasename);
        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);
    }

    /** @test */
    public function it_skips_thumbnail_generation_for_non_image_file(): void
    {
        // 準備
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'mime' => 'application/pdf',
            'path' => 'public/Ledger/Attachments/' . $ledgerDefine->id . '/test_document.pdf',
            'status' => AttachedFileStatus::UPLOADED,
        ]);

        // 実行
        (new GenerateThumbnail($attachedFile->id))->handle($this->app->make(ImageManager::class));

        // 検証
        Storage::disk('public')->assertMissing('public/Ledger/thumbs/' . $attachedFile->hashedbasename);
        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::COMPLETED->value, // 非画像ファイルも完了ステータスになる
        ]);
    }

    /** @test */
    public function it_handles_attached_file_not_found(): void
    {
        // ログのモック
        Log::shouldReceive('warning')
            ->once()
            ->with('[' . GenerateThumbnail::class . '] AttachedFile not found for ID: 999. Aborting job.');
        Log::shouldReceive('info')
            ->zeroOrMoreTimes(); // Allow info logs without specific expectations

        // 実行
        (new GenerateThumbnail(999))->handle($this->app->make(ImageManager::class));

        // 検証 (ログが記録されたことを確認)
        $this->assertTrue(true); // アサーションがないとテストがパスしないため
    }

    /** @test */
    public function it_updates_status_on_thumbnail_generation_failure(): void
    {
        // Image::make() が例外を投げるようにモック
        Image::shouldReceive('make')
            ->andThrow(new \Exception('Image processing error'));

        // 準備
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'mime' => 'image/jpeg',
            'path' => 'public/Ledger/Attachments/' . $ledgerDefine->id . '/test_image.jpg',
            'status' => AttachedFileStatus::UPLOADED,
        ]);

        // ダミーの画像ファイルを作成
        Storage::disk('public')->put($attachedFile->path, 'dummy image content');

        // ログのモック
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to generate thumbnail');
            });

        // 実行
        $job = new GenerateThumbnail($attachedFile->id);
        try {
            $job->handle($this->app->make(ImageManager::class));
        } catch (\Exception $e) {
            // 例外が投げられた後、failed メソッドが呼ばれることを確認
            $job->failed($e);
        }

        // 検証
        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::THUMBNAIL_FAILED->value,
        ]);
    }

    /** @test */
    public function it_updates_status_on_job_failed_method(): void
    {
        // 準備
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'mime' => 'image/jpeg',
            'path' => 'public/Ledger/Attachments/' . $ledgerDefine->id . '/test_image.jpg',
            'status' => AttachedFileStatus::UPLOADED,
        ]);

        // 実行
        $job = new GenerateThumbnail($attachedFile->id);
        $job->failed(new \RuntimeException('Test failure'));

        // 検証
        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::THUMBNAIL_FAILED->value,
        ]);
    }
}
