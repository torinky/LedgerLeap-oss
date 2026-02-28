<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\Embedding\RuriChunkFormatter;
use App\Services\EmbeddingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ProcessLedgerForRagJobTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        config(['rag.enabled' => true]);
    }

    #[Test]
    public function it_processes_ledger_body_only()
    {
        // Arrange
        config(['rag.enabled' => false]); // Disable RAG to prevent Observer from running

        $user = User::factory()->create(['name' => 'テストユーザー']);
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => '日報',
            'column_define' => [
                ['id' => 1, 'name' => '報告者', 'type' => 'text', 'order' => 1, 'group' => '基本情報', 'display_level' => 1, 'required' => true, 'options' => []],
            ],
        ]);

        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create([
            'tenant_id' => tenant('id'),
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => '田中太郎'],
        ])->load('define');

        \DB::table('ledger_chunks')->delete(); // Clean up chunks created by Observer

        config(['rag.enabled' => true]); // Re-enable RAG for the job

        // Mock EmbeddingService
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->once()
            ->andReturn([array_fill(0, 768, 0.1)]);

        $formatter = app(RuriChunkFormatter::class);

        // Act
        // attachedFileId = null (default)
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock, $formatter);

        // Assert
        $this->assertDatabaseCount('ledger_chunks', 1);
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();

        $this->assertTrue(is_null($chunk->attached_file_id) || $chunk->attached_file_id === 0);
        $this->assertStringContainsString('[Metadata]', $chunk->chunk_text);
        $this->assertStringContainsString('Type: 台帳レコード', $chunk->chunk_text);
        $this->assertStringContainsString('ID: '.$ledger->id, $chunk->chunk_text);
        $this->assertStringContainsString('Title: 日報', $chunk->chunk_text);
        $this->assertStringContainsString('[Body]', $chunk->chunk_text);
        $this->assertStringContainsString('# 日報', $chunk->chunk_text);
        $this->assertStringContainsString('田中太郎', $chunk->chunk_text);
    }

    #[Test]
    public function it_processes_attached_file_only()
    {
        // Arrange
        config(['rag.enabled' => false]);

        $ledger = Ledger::factory()->create(['tenant_id' => tenant('id')])->load('define');

        $file = AttachedFile::create([
            'tenant_id' => tenant('id'),
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->define->id, // Use ledger->define->id
            'column_id' => 1,
            'filename' => 'test.pdf',
            'hashedbasename' => 'test.pdf',
            'mime' => 'application/pdf',
            'path' => 'path/to/file',
            'size' => 1024,
            'status' => \App\Enums\AttachedFileStatus::COMPLETED,
            'contain_content' => true,
            'optimized' => true,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
            'vlm_markdown' => '添付ファイルの内容です。重要なキーワードが含まれています。',
        ]);

        \DB::table('ledger_chunks')->delete(); // Clean up chunks created by Observer

        config(['rag.enabled' => true]); // Re-enable RAG for the job

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->once()
            ->andReturn([array_fill(0, 768, 0.2)]);

        $formatter = app(RuriChunkFormatter::class);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id, $file->id);
        $job->handle($embeddingServiceMock, $formatter);

        // Assert
        $this->assertDatabaseCount('ledger_chunks', 1);
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();

        $this->assertEquals($file->id, $chunk->attached_file_id);
        $this->assertStringContainsString('[Metadata]', $chunk->chunk_text);
        $this->assertStringContainsString('Type: 添付ファイル', $chunk->chunk_text);
        $this->assertStringContainsString('Filename: test.pdf', $chunk->chunk_text);
        $this->assertStringContainsString('[Body]', $chunk->chunk_text);
        $this->assertStringContainsString('添付ファイルの内容です', $chunk->chunk_text);
    }

    #[Test]
    public function it_performs_granular_updates()
    {
        // Arrange
        config(['rag.enabled' => false]);

        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 0, 'name' => 'ZeroContent', 'type' => 'text', 'order' => 0, 'group' => 'G0', 'display_level' => 1, 'required' => false, 'options' => []],
                ['id' => 1, 'name' => 'BodyContent', 'type' => 'text', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);

        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create([
            'tenant_id' => tenant('id'),
            'content' => [0 => 'Content for ID 0', 1 => 'Ledger Body Text'],
        ])->load('define');

        $baseFileData = [
            'tenant_id' => tenant('id'),
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'mime' => 'application/pdf',
            'path' => 'path/to/file',
            'size' => 1024,
            'status' => \App\Enums\AttachedFileStatus::COMPLETED,
            'contain_content' => true,
            'optimized' => true,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
        ];

        $file1 = AttachedFile::create(array_merge($baseFileData, [
            'filename' => 'file1.pdf',
            'hashedbasename' => 'file1.pdf',
            'vlm_markdown' => 'File 1 content',
        ]));

        $file2 = AttachedFile::create(array_merge($baseFileData, [
            'filename' => 'file2.pdf',
            'hashedbasename' => 'file2.pdf',
            'vlm_markdown' => 'File 2 content',
        ]));

        \DB::table('ledger_chunks')->delete(); // Clean up chunks before Act
        config(['rag.enabled' => true]); // Re-enable RAG before Act

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        // We expect 4 calls in total for embed method
        $embeddingServiceMock->shouldReceive('embed')
            ->times(4)
            ->andReturn([array_fill(0, 768, 0.1)]);

        $formatter = app(RuriChunkFormatter::class);

        // 1. Initial State: Process Body and File 2
        (new ProcessLedgerForRagJob($ledger->id))->handle($embeddingServiceMock, $formatter);
        (new ProcessLedgerForRagJob($ledger->id, $file2->id))->handle($embeddingServiceMock, $formatter);

        $this->assertDatabaseCount('ledger_chunks', 2);

        // 2. Process File 1 (Should add 1 chunk, total 3)
        (new ProcessLedgerForRagJob($ledger->id, $file1->id))->handle($embeddingServiceMock, $formatter);
        $this->assertDatabaseCount('ledger_chunks', 3);

        // 3. Update File 1 (Should replace File 1 chunk, total still 3)
        $oldChunkId = \DB::table('ledger_chunks')->where('attached_file_id', $file1->id)->value('id');

        (new ProcessLedgerForRagJob($ledger->id, $file1->id))->handle($embeddingServiceMock, $formatter);

        $this->assertDatabaseCount('ledger_chunks', 3);
        $newChunkId = \DB::table('ledger_chunks')->where('attached_file_id', $file1->id)->value('id');

        $this->assertNotEquals($oldChunkId, $newChunkId, 'Old chunk should be deleted and new one created');

        // Verify Ledger Body and File 2 chunks still exist
        $chunks = \DB::table('ledger_chunks')->get();

        $this->assertTrue(
            $chunks->contains(function ($chunk) {
                return is_null($chunk->attached_file_id) || $chunk->attached_file_id === 0;
            }),
            'Ledger Body chunks should exist (attached_file_id is NULL or 0)'
        );

        $this->assertTrue(
            $chunks->contains('attached_file_id', $file2->id),
            'File 2 chunks should exist'
        );
    }

    #[Test]
    public function it_uses_previewable_text_if_vlm_markdown_is_empty()
    {
        // Arrange
        config(['rag.enabled' => false]);

        $ledger = Ledger::factory()->create([
            'tenant_id' => tenant('id'),
            'content_attached' => [
                0 => [], // Required for normalization
                1 => [ // column_id
                    'test.pdf' => [
                        'meta' => ['content' => 'OCR Text Content'],
                    ],
                ],
            ],
        ])->load('define');

        $file = AttachedFile::create([
            'tenant_id' => tenant('id'),
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->define->id,
            'column_id' => 1,
            'filename' => 'test.pdf',
            'hashedbasename' => 'test.pdf',
            'mime' => 'application/pdf',
            'path' => 'path/to/file',
            'size' => 1024,
            'status' => \App\Enums\AttachedFileStatus::COMPLETED,
            'contain_content' => true,
            'optimized' => true,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
            'vlm_markdown' => null, // Empty VLM
            'processing_finalized_at' => now(),
            'finalized_source' => 'ocr',
        ]);

        \DB::table('ledger_chunks')->delete(); // Clean up chunks created by Observer

        config(['rag.enabled' => true]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->once()->andReturn([array_fill(0, 768, 0.1)]);
        $formatter = app(RuriChunkFormatter::class);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id, $file->id);
        $job->handle($embeddingServiceMock, $formatter);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('attached_file_id', $file->id)->first();
        $this->assertNotNull($chunk);
        $this->assertStringContainsString('OCR Text Content', $chunk->chunk_text);
    }
}
