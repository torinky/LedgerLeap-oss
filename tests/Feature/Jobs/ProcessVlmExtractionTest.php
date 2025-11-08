<?php

namespace Tests\Feature\Jobs;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\ProcessVlmExtraction;
use App\Models\AttachedFile;
use App\Services\VlmClientService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ProcessVlmExtractionTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        Bus::fake();
        Config::set('rag.auto_update_chunks', false);
        Config::set('vlm.retry.times', 1);
    }

    #[Test]
    public function job_updates_database_on_successful_extraction(): void
    {
        // Arrange
        $attachedFile = AttachedFile::factory()->create([
            'status' => AttachedFileStatus::PENDING_VLM,
            'vlm_markdown' => null,
        ]);

        $mockVlmData = [
            'success' => true,
            'markdown' => '# Invoice\n\nTotal: $500',
            'structured_data' => ['total' => 500],
            'model' => 'test-model-v1',
            'processing_time_s' => 2.5,
            'confidence' => 0.95,
        ];

        $this->mock(VlmClientService::class, function ($mock) use ($mockVlmData, $attachedFile) {
            $mock->shouldReceive('extract')
                ->once()
                ->with(\Mockery::on(function ($arg) use ($attachedFile) {
                    return $arg->id === $attachedFile->id;
                }))
                ->andReturn($mockVlmData);
        });

        $job = new ProcessVlmExtraction($attachedFile);

        // Act
        $job->handle($this->app->make(VlmClientService::class));

        // Assert
        $this->assertDatabaseHas('attached_files', [
            'id' => $attachedFile->id,
            'status' => AttachedFileStatus::COMPLETED->value,
            'vlm_markdown' => '# Invoice\n\nTotal: $500',
            'vlm_model' => 'test-model-v1',
        ]);

        $attachedFile->refresh();
        $this->assertNotNull($attachedFile->vlm_structured_data);
        $this->assertEquals(['total' => 500], $attachedFile->vlm_structured_data);
        $this->assertEquals(0.95, $attachedFile->vlm_confidence);
        $this->assertNotNull($attachedFile->vlm_processed_at);
    }

    #[Test]
    public function job_marks_as_failed_when_vlm_returns_empty_markdown(): void
    {
        // Arrange
        $attachedFile = AttachedFile::factory()->create([
            'status' => AttachedFileStatus::PENDING_VLM,
        ]);

        $this->mock(VlmClientService::class, function ($mock) use ($attachedFile) {
            $mock->shouldReceive('extract')
                ->once()
                ->with(\Mockery::on(function ($arg) use ($attachedFile) {
                    return $arg->id === $attachedFile->id;
                }))
                ->andReturn([
                    'success' => true,
                    'markdown' => '',
                    'structured_data' => [],
                    'model' => 'test-model',
                ]);
        });

        $job = new ProcessVlmExtraction($attachedFile);

        // Act & Assert
        try {
            $job->handle($this->app->make(VlmClientService::class));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('empty markdown', $e->getMessage());

            $job->failed($e);

            // Phase5: Check vlm_failed_at is set
            $this->assertDatabaseHas('attached_files', [
                'id' => $attachedFile->id,
                'status' => AttachedFileStatus::VLM_FAILED->value,
            ]);

            $attachedFile->refresh();
            $this->assertNotNull($attachedFile->vlm_failed_at);
        }
    }

    #[Test]
    public function job_handles_vlm_service_exception(): void
    {
        // Arrange
        $attachedFile = AttachedFile::factory()->create([
            'status' => AttachedFileStatus::PENDING_VLM,
        ]);

        $this->mock(VlmClientService::class, function ($mock) use ($attachedFile) {
            $mock->shouldReceive('extract')
                ->once()
                ->with(\Mockery::on(function ($arg) use ($attachedFile) {
                    return $arg->id === $attachedFile->id;
                }))
                ->andThrow(new RuntimeException('VLM service returned status 500'));
        });

        $job = new ProcessVlmExtraction($attachedFile);

        // Act & Assert
        try {
            $job->handle($this->app->make(VlmClientService::class));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('500', $e->getMessage());

            $job->failed($e);

            // Phase5: Check vlm_failed_at is set
            $this->assertDatabaseHas('attached_files', [
                'id' => $attachedFile->id,
                'status' => AttachedFileStatus::VLM_FAILED->value,
            ]);

            $attachedFile->refresh();
            $this->assertNotNull($attachedFile->vlm_failed_at);
        }
    }
}
