<?php

namespace Tests\Feature\Jobs;

use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Jobs\Ledger\OcrAndOptimizeFile;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Jobs\Ledger\ProcessVlmExtraction;
use App\Models\AttachedFile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessAttachedFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
    }

    #[Test]
    public function it_dispatches_generate_thumbnail_job_for_image_file()
    {
        // Arrange
        Bus::fake();
        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/jpeg',
        ]);
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

        // Tikaクライアントをモック
        $tikaClientMock = $this->mock(\Vaites\ApacheTika\Client::class, function ($mock) {
            $mock->shouldReceive('getText')->andReturn('Sample text');

            $metadataMock = \Mockery::mock(\Vaites\ApacheTika\Metadata\MetadataInterface::class, \IteratorAggregate::class);
            $metadataMock->shouldReceive('get')->with('mime')->andReturn('image/jpeg');
            $metadataMock->shouldReceive('getIterator')->andReturn(new \ArrayIterator(['mime' => 'image/jpeg']));

            $mock->shouldReceive('getMetadata')->andReturn($metadataMock);
            $mock->shouldReceive('setTimeout');
        });
        $this->app->instance(\Vaites\ApacheTika\Client::class, $tikaClientMock);

        // Act
        $job = new ProcessAttachedFile($attachedFile);
        $job->handle();

        // Assert
        Bus::assertDispatched(function (GenerateThumbnail $job) use ($attachedFile) {
            return $job->attachedFileId === $attachedFile->id;
        });
    }

    #[Test]
    public function it_dispatches_parallel_processing_for_vlm_ocr_target_files()
    {
        // Arrange
        Bus::fake();
        Config::set('vlm.enabled', true);

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/png',
        ]);
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

        // Tikaクライアントをモック
        $this->mockTikaClient('image/png', 'Some text');

        // Act
        $job = new ProcessAttachedFile($attachedFile);
        $job->handle();

        // Assert - Phase5: VLM and OCR dispatched in parallel
        Bus::assertDispatched(ProcessVlmExtraction::class);
        Bus::assertDispatched(OcrAndOptimizeFile::class);

        // Check tika_processed_at is set
        $attachedFile->refresh();
        $this->assertNotNull($attachedFile->tika_processed_at);
    }

    #[Test]
    public function it_finalizes_non_vlm_ocr_target_files_immediately()
    {
        // Arrange
        Bus::fake();
        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/zip',
        ]);
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

        // Tikaクライアントをモック
        $this->mockTikaClient('application/zip', 'Archive content');

        // Act
        $job = new ProcessAttachedFile($attachedFile);
        $job->handle();

        // Assert - Phase5: Non-VLM/OCR files are finalized immediately
        Bus::assertNotDispatched(ProcessVlmExtraction::class);
        Bus::assertNotDispatched(OcrAndOptimizeFile::class);

        $attachedFile->refresh();
        $this->assertNotNull($attachedFile->tika_processed_at);
        $this->assertNotNull($attachedFile->processing_finalized_at);
        $this->assertEquals('tika', $attachedFile->finalized_source);
    }

    #[Test]
    public function it_does_not_dispatch_generate_thumbnail_job_for_pdf_file()
    {
        // Arrange
        Bus::fake();
        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/pdf',
        ]);
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

        $this->mockTikaClient('application/pdf', '');

        // Act
        $job = new ProcessAttachedFile($attachedFile);
        $job->handle();

        // Assert
        Bus::assertNotDispatched(GenerateThumbnail::class);
    }

    #[Test]
    public function it_does_not_dispatch_generate_thumbnail_job_for_other_file_types()
    {
        // Arrange
        Bus::fake();
        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/zip',
        ]);
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

        $this->mockTikaClient('application/zip', '');

        // Act
        $job = new ProcessAttachedFile($attachedFile);
        $job->handle();

        // Assert
        Bus::assertNotDispatched(GenerateThumbnail::class);
    }

    private function mockTikaClient(string $mime, string $text): void
    {
        $tikaClientMock = $this->mock(\Vaites\ApacheTika\Client::class, function ($mock) use ($mime, $text) {
            $mock->shouldReceive('getText')->andReturn($text);

            $metadataMock = \Mockery::mock(\Vaites\ApacheTika\Metadata\MetadataInterface::class, \IteratorAggregate::class);
            $metadataMock->shouldReceive('get')->with('mime')->andReturn($mime);
            $metadataMock->shouldReceive('getIterator')->andReturn(new \ArrayIterator(['mime' => $mime]));

            $mock->shouldReceive('getMetadata')->andReturn($metadataMock);
            $mock->shouldReceive('setTimeout');
        });
        $this->app->instance(\Vaites\ApacheTika\Client::class, $tikaClientMock);
    }
}
