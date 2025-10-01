<?php

namespace Tests\Feature\Jobs;

use App\Helpers\AttachedFilePathHelper; // ★ 追加
use App\Jobs\Ledger\GenerateThumbnail;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Models\AttachedFile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
        // ★ 正しいパスをヘルパーから取得してファイルを配置
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

        // Tikaクライアントをモック
        $tikaClientMock = $this->mock(\Vaites\ApacheTika\Client::class, function ($mock) {
            $mock->shouldReceive('getText')->andReturn('');

            // MetadataInterfaceのモックを作成
            $metadataMock = \Mockery::mock(\Vaites\ApacheTika\Metadata\MetadataInterface::class, \IteratorAggregate::class);
            $metadataMock->shouldReceive('get')->with('mime')->andReturn('image/jpeg');
            // is_object() と foreach で使われるため、IteratorAggregate を実装する必要がある
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
    public function it_does_not_dispatch_generate_thumbnail_job_for_pdf_file()
    {
        // Arrange
        Bus::fake();
        $attachedFile = AttachedFile::factory()->create([
            'mime' => 'application/pdf',
        ]);
        // ★ 正しいパスをヘルパーから取得してファイルを配置
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

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
        // ★ 正しいパスをヘルパーから取得してファイルを配置
        $path = AttachedFilePathHelper::getAttachmentPath($attachedFile->ledger_define_id, $attachedFile->hashedbasename);
        $attachedFile->update(['path' => $path]);
        Storage::disk('public')->put($path, 'dummy_content');

        // Act
        $job = new ProcessAttachedFile($attachedFile);
        $job->handle();

        // Assert
        Bus::assertNotDispatched(GenerateThumbnail::class);
    }
}
