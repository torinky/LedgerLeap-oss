<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\EmbeddingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ProcessLedgerForRagJobTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        config(['rag.enabled' => true]);
    }

    #[Test]
    public function it_generates_structured_markdown_from_ledger()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => '日報',
            'create_description' => '日々の業務内容を記録する台帳です',
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '報告者',
                    'type' => 'text',
                    'order' => 1,
                    'group' => '基本情報',
                    'display_level' => 1,
                    'required' => true,
                    'options' => [],
                ],
                [
                    'id' => 2,
                    'name' => '達成事項',
                    'type' => 'textarea',
                    'order' => 2,
                    'group' => '基本情報',
                    'display_level' => 1,
                    'required' => false,
                    'options' => [],
                ],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [
                0 => '',
                1 => '田中太郎',
                2 => 'RAG機能の設計を完了しました',
            ],
        ]);

        // Mock EmbeddingService
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->once()
            ->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert - check that chunk contains structured markdown
        $this->assertDatabaseCount('ledger_chunks', 1);
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('# 日報', $chunk->chunk_text);
        $this->assertStringContainsString('> 日々の業務内容を記録する台帳です', $chunk->chunk_text);
        $this->assertStringContainsString('## 基本情報', $chunk->chunk_text);
        $this->assertStringContainsString('### 報告者', $chunk->chunk_text);
        $this->assertStringContainsString('田中太郎', $chunk->chunk_text);
        $this->assertStringContainsString('### 達成事項', $chunk->chunk_text);
        $this->assertStringContainsString('RAG機能の設計を完了しました', $chunk->chunk_text);
    }

    #[Test]
    public function it_handles_different_display_levels()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト台帳',
            'column_define' => [
                ['id' => 1, 'name' => '通常項目', 'type' => 'text', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
                ['id' => 2, 'name' => '詳細項目', 'type' => 'text', 'order' => 2, 'group' => 'G1', 'display_level' => 2, 'required' => false, 'options' => []],
                ['id' => 3, 'name' => '補足項目', 'type' => 'text', 'order' => 3, 'group' => 'G1', 'display_level' => 3, 'required' => false, 'options' => []],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => 'value1', 2 => 'value2', 3 => 'value3'],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('### 通常項目', $chunk->chunk_text);
        $this->assertStringContainsString('#### 詳細項目', $chunk->chunk_text);
        $this->assertStringContainsString('##### 補足項目', $chunk->chunk_text);
    }

    #[Test]
    public function it_converts_select_type_with_associative_options()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                [
                    'id' => 1,
                    'name' => 'ステータス',
                    'type' => 'select',
                    'order' => 1,
                    'group' => 'G1',
                    'display_level' => 1,
                    'required' => false,
                    'options' => ['draft' => '下書き', 'published' => '公開'],
                ],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => 'draft'],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('下書き', $chunk->chunk_text);
        $this->assertStringNotContainsString('draft', $chunk->chunk_text);
    }

    #[Test]
    public function it_converts_checkbox_type_with_multiple_selections()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                [
                    'id' => 1,
                    'name' => 'タグ',
                    'type' => 'chk',
                    'order' => 1,
                    'group' => 'G1',
                    'display_level' => 1,
                    'required' => false,
                    'options' => ['urgent' => '緊急', 'important' => '重要', 'review' => 'レビュー必要'],
                ],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => ['urgent' => true, 'important' => false, 'review' => true]],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('緊急、レビュー必要', $chunk->chunk_text);
        $this->assertStringNotContainsString('重要', $chunk->chunk_text);
    }

    #[Test]
    public function it_converts_files_type_with_original_filenames()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '添付ファイル',
                    'type' => 'files',
                    'order' => 1,
                    'group' => 'G1',
                    'display_level' => 1,
                    'required' => false,
                    'options' => [],
                ],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => ['hashed_file1.pdf' => 'document.pdf', 'hashed_file2.jpg' => 'photo.jpg']],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('document.pdf、photo.jpg', $chunk->chunk_text);
    }

    #[Test]
    public function it_adds_unit_to_number_type()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '金額',
                    'type' => 'number',
                    'order' => 1,
                    'group' => 'G1',
                    'display_level' => 1,
                    'required' => false,
                    'options' => ['unit' => '円'],
                ],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => 10000],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('10000 円', $chunk->chunk_text);
    }

    #[Test]
    public function it_skips_null_and_empty_values()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                ['id' => 1, 'name' => '空項目', 'type' => 'text', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
                ['id' => 2, 'name' => '値あり', 'type' => 'text', 'order' => 2, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => null, 2 => 'データあり'],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringNotContainsString('空項目', $chunk->chunk_text);
        $this->assertStringContainsString('値あり', $chunk->chunk_text);
    }

    #[Test]
    public function it_handles_empty_group_name()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                ['id' => 1, 'name' => '項目', 'type' => 'text', 'order' => 1, 'group' => '', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => 'データ'],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('## その他', $chunk->chunk_text);
    }

    #[Test]
    public function it_updates_content_attached_when_vlm_result_is_better()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 1, 'name' => '添付', 'type' => 'files', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [
                0 => '',
                1 => ['file1.pdf' => 'original_file1.pdf'],
            ],
            'content_attached' => [
                0 => [],
                1 => [
                    'file1.pdf' => [
                        'originalName' => 'original_file1.pdf',
                        'meta' => ['content' => '古いTikaテキスト'],
                    ],
                ],
            ],
        ]);

        $vlmText = 'これはVLMによって生成された、より詳細で優れたテキストです。';
        $attachedFile = AttachedFile::factory()->for($ledger)->create([
            'filename' => 'file1.pdf',
            'hashedbasename' => 'file1.pdf',
            'column_id' => 1,
            'vlm_markdown' => $vlmText,
            'vlm_processed_at' => now(),
        ]);

        // Debug: Verify attached file was created with correct tenant_id
        $this->assertEquals($ledger->tenant_id, $attachedFile->tenant_id,
            "AttachedFile tenant_id ({$attachedFile->tenant_id}) should match Ledger tenant_id ({$ledger->tenant_id})");

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->once()->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert - refresh to get updated data from database
        $ledger->refresh();
        $this->assertEquals($vlmText, $ledger->content_attached[1]['file1.pdf']['meta']['content']);

        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('#### ファイル: original_file1.pdf', $chunk->chunk_text);
        $this->assertStringContainsString($vlmText, $chunk->chunk_text);
        $this->assertStringNotContainsString('古いTikaテキスト', $chunk->chunk_text);
    }

    #[Test]
    public function it_does_not_update_content_attached_when_vlm_result_is_worse()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 1, 'name' => '添付', 'type' => 'files', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);
        $tikaText = 'これはTikaによって抽出された、より詳細で優れたテキストです。';
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [
                0 => '',
                1 => ['file1.pdf' => 'original_file1.pdf'],
            ],
            'content_attached' => [
                0 => [],
                1 => [
                    'file1.pdf' => [
                        'originalName' => 'original_file1.pdf',
                        'meta' => ['content' => $tikaText],
                    ],
                ],
            ],
        ]);

        AttachedFile::factory()->for($ledger)->create([
            'filename' => 'file1.pdf',
            'hashedbasename' => 'file1.pdf',
            'column_id' => 1,
            'vlm_markdown' => 'VLMの短いテキスト',
            'vlm_processed_at' => now(),
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->once()->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert - refresh to get data from database
        $ledger->refresh();
        $this->assertEquals($tikaText, $ledger->content_attached[1]['file1.pdf']['meta']['content']);

        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        // VLM処理済みでもテキストが更新されなかったので、Tikaテキストが使われる
        $this->assertStringContainsString('#### ファイル: original_file1.pdf', $chunk->chunk_text);
        $this->assertStringContainsString($tikaText, $chunk->chunk_text);
        $this->assertStringNotContainsString('VLMの短いテキスト', $chunk->chunk_text);
    }

    #[Test]
    public function it_adds_new_entry_to_content_attached_from_vlm_result()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 1, 'name' => '添付', 'type' => 'files', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [
                0 => '',
                1 => ['new_file.pdf' => 'original_new_file.pdf'],
            ],
            'content_attached' => [], // content_attached is initially empty
        ]);

        $vlmText = 'これはVLMによって生成された新しいテキストです。';
        AttachedFile::factory()->for($ledger)->create([
            'filename' => 'new_file.pdf',
            'hashedbasename' => 'new_file.pdf',
            'column_id' => 1,
            'vlm_markdown' => $vlmText,
            'vlm_processed_at' => now(),
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->once()->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle($embeddingServiceMock);

        // Assert - refresh to get updated data from database
        $ledger->refresh();
        $this->assertArrayHasKey('new_file.pdf', $ledger->content_attached[1]);
        $this->assertEquals($vlmText, $ledger->content_attached[1]['new_file.pdf']['meta']['content']);

        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('#### ファイル: original_new_file.pdf', $chunk->chunk_text);
        $this->assertStringContainsString($vlmText, $chunk->chunk_text);
    }
}
