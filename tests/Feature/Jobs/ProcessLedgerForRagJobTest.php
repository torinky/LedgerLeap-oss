<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Log;
use Mockery;
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
        $job = new ProcessLedgerForRagJob($ledger);
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
        $job = new ProcessLedgerForRagJob($ledger);
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
        $job = new ProcessLedgerForRagJob($ledger);
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
        $job = new ProcessLedgerForRagJob($ledger);
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
        $job = new ProcessLedgerForRagJob($ledger);
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
        $job = new ProcessLedgerForRagJob($ledger);
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
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringNotContainsString('空項目', $chunk->chunk_text);
        $this->assertStringContainsString('値あり', $chunk->chunk_text);
    }

    #[Test]
    public function it_includes_attached_file_content()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                ['id' => 1, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => 'テスト'],
            'content_attached' => 'これは添付ファイルから抽出されたテキストです。',
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('## 添付ファイル内容', $chunk->chunk_text);
        $this->assertStringContainsString('これは添付ファイルから抽出されたテキストです。', $chunk->chunk_text);
    }

    #[Test]
    public function it_truncates_long_attached_text_and_logs_warning()
    {
        // Arrange
        config(['rag.chunking.max_attached_text_length' => 100]);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnSelf();
        Log::shouldReceive('warning')
            ->atLeast()->once()
            ->with('Attached text truncated for RAG', Mockery::type('array'));

        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                ['id' => 1, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);

        $longText = str_repeat('あ', 150);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => 'テスト'],
            'content_attached' => $longText,
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('[... 以降のテキストは省略されました]', $chunk->chunk_text);

        // Extract the attached content section and verify it was truncated
        preg_match('/## 添付ファイル内容\n(.+)$/s', $chunk->chunk_text, $matches);
        $this->assertNotEmpty($matches, 'Attached file content section should exist');
        $attachedSection = $matches[1] ?? '';

        // The attached section should be around 100 chars (config limit) plus truncation message
        // Original was 150 chars, so it should be less
        $this->assertLessThan(150, mb_strlen($attachedSection));
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
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('## その他', $chunk->chunk_text);
    }

    #[Test]
    public function it_handles_array_content_attached()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'テスト',
            'column_define' => [
                ['id' => 1, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'group' => 'G1', 'display_level' => 1, 'required' => false, 'options' => []],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => [0 => '', 1 => 'テスト'],
            'content_attached' => ['ファイル1のテキスト', 'ファイル2のテキスト'],
        ]);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([array_fill(0, 768, 0.1)]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($embeddingServiceMock);

        // Assert
        $chunk = \DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();
        $this->assertStringContainsString('ファイル1のテキスト', $chunk->chunk_text);
        $this->assertStringContainsString('ファイル2のテキスト', $chunk->chunk_text);
    }
}
