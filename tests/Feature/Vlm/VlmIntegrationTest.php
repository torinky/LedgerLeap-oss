<?php

namespace Tests\Feature\Vlm;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\OcrAndOptimizeFile;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Jobs\Ledger\ProcessVlmExtraction;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;
use Vaites\ApacheTika\Client as TikaClient;

class VlmIntegrationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        Storage::fake('public');
        Bus::fake();
    }

    #[Test]
    public function vlm_job_is_dispatched_for_eligible_file_when_vlm_is_enabled(): void
    {
        // Arrange
        Config::set('vlm.enabled', true);

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/png',
            'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING,
        ]);

        // ファイルを配置
        $path = AttachedFilePathHelper::getAttachmentPath(
            $attachedFile->ledger_define_id,
            $attachedFile->hashedbasename
        );
        Storage::disk('public')->put($path, 'dummy image content');
        $attachedFile->update(['path' => $path]);

        // TikaClientをモック（テキスト抽出成功）
        $this->mock(TikaClient::class, function ($mock) {
            $mock->shouldReceive('getText')->once()->andReturn('Some extracted text from Tika');
            $mock->shouldReceive('setTimeout')->once();

            $metadataMock = \Mockery::mock(\Vaites\ApacheTika\Metadata\MetadataInterface::class);
            $metadataMock->shouldReceive('get')->with('mime')->andReturn('image/png');
            $mock->shouldReceive('getMetadata')->once()->andReturn($metadataMock);
        });

        $job = new ProcessAttachedFile($attachedFile);

        // Act
        $job->handle();

        // Assert
        Bus::assertDispatched(ProcessVlmExtraction::class, function ($job) use ($attachedFile) {
            return $job->attachedFile->id === $attachedFile->id;
        });

        Bus::assertNotDispatched(OcrAndOptimizeFile::class);

        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::PENDING_VLM->value,
        ]);
    }

    #[Test]
    public function ocr_job_is_dispatched_when_vlm_is_disabled(): void
    {
        // Arrange
        Config::set('vlm.enabled', false);

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'image/jpeg',
            'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING,
        ]);

        $path = AttachedFilePathHelper::getAttachmentPath(
            $attachedFile->ledger_define_id,
            $attachedFile->hashedbasename
        );
        Storage::disk('public')->put($path, 'dummy image content');
        $attachedFile->update(['path' => $path]);

        $this->mock(TikaClient::class, function ($mock) {
            $mock->shouldReceive('getText')->once()->andReturn(''); // 空のテキスト
            $mock->shouldReceive('setTimeout')->once();

            $metadataMock = \Mockery::mock(\Vaites\ApacheTika\Metadata\MetadataInterface::class);
            $metadataMock->shouldReceive('get')->with('mime')->andReturn('image/jpeg');
            $mock->shouldReceive('getMetadata')->once()->andReturn($metadataMock);
        });

        $job = new ProcessAttachedFile($attachedFile);

        // Act
        $job->handle();

        // Assert
        Bus::assertDispatched(OcrAndOptimizeFile::class);
        Bus::assertNotDispatched(ProcessVlmExtraction::class);
    }

    #[Test]
    public function vlm_job_is_not_dispatched_for_ineligible_file_type(): void
    {
        // Arrange
        Config::set('vlm.enabled', true);

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/zip',
            'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING,
        ]);

        $path = AttachedFilePathHelper::getAttachmentPath(
            $attachedFile->ledger_define_id,
            $attachedFile->hashedbasename
        );
        Storage::disk('public')->put($path, 'dummy zip content');
        $attachedFile->update(['path' => $path]);

        $this->mock(TikaClient::class, function ($mock) {
            $mock->shouldReceive('getText')->once()->andReturn('Zip file content');
            $mock->shouldReceive('setTimeout')->once();

            $metadataMock = \Mockery::mock(\Vaites\ApacheTika\Metadata\MetadataInterface::class);
            $metadataMock->shouldReceive('get')->with('mime')->andReturn('application/zip');
            $mock->shouldReceive('getMetadata')->once()->andReturn($metadataMock);
        });

        $job = new ProcessAttachedFile($attachedFile);

        // Act
        $job->handle();

        // Assert
        Bus::assertNotDispatched(ProcessVlmExtraction::class);
        Bus::assertNotDispatched(OcrAndOptimizeFile::class);

        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);
    }

    #[Test]
    public function vlm_job_is_dispatched_when_tika_extraction_fails(): void
    {
        // Arrange
        Config::set('vlm.enabled', true);

        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING,
        ]);

        $path = AttachedFilePathHelper::getAttachmentPath(
            $attachedFile->ledger_define_id,
            $attachedFile->hashedbasename
        );
        Storage::disk('public')->put($path, 'dummy pdf content');
        $attachedFile->update(['path' => $path]);

        // TikaClientがException をスロー
        $this->mock(TikaClient::class, function ($mock) {
            $mock->shouldReceive('getText')
                ->once()
                ->andThrow(new \Exception('Tika extraction failed'));
            $mock->shouldReceive('setTimeout')->once();
        });

        $job = new ProcessAttachedFile($attachedFile);

        // Act
        $job->handle();

        // Assert
        Bus::assertDispatched(ProcessVlmExtraction::class, function ($job) use ($attachedFile) {
            return $job->attachedFile->id === $attachedFile->id;
        });

        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::PENDING_VLM->value,
        ]);
    }
}
