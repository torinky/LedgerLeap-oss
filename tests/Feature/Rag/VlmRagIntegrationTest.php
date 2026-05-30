<?php

namespace Tests\Feature\Rag;

use App\Jobs\Embedding\VectorizeAttachedFile;
use App\Jobs\Ledger\ProcessVlmExtraction;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerChunk;
use App\Services\EmbeddingService;
use App\Services\VlmClientService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class VlmRagIntegrationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        Storage::fake('local');

        // VLMクライアントをモック化
        $this->mock(VlmClientService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([
                'markdown' => '# VLM Markdown Content',
                'structured_data' => [],
                'model' => 'mock-vlm-model',
                'confidence' => 0.95,
            ]);
        });

        // Embeddingサービスをモック化 (APIコールを避けるため)
        $this->mock(EmbeddingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn([
                array_fill(0, 384, 0.123), // ダミーのベクトルデータを返す
            ]);
        });
    }

    #[Test]
    public function it_dispatches_vectorize_attached_file_job_after_vlm_extraction_succeeds(): void
    {
        // 1. 準備 (Arrange)
        Bus::fake();
        Config::set('rag.chunking.auto_update_chunks', true);

        $ledger = Ledger::factory()->create();
        $attachedFile = AttachedFile::factory()
            ->forLedger($ledger)
            ->create(['path' => 'test.pdf']);

        Storage::disk('local')->put($attachedFile->path, 'dummy content');

        // 2. 実行 (Act)
        $job = new ProcessVlmExtraction($attachedFile);
        $job->handle(app(VlmClientService::class));

        // 3. 検証 (Assert)
        // VectorizeAttachedFileがディスパッチされたことを確認
        Bus::assertDispatched(VectorizeAttachedFile::class, function ($job) use ($attachedFile) {
            // dispatchされたジョブが正しいAttachedFile IDを持っているか確認
            return $job->attachedFileId === $attachedFile->id && $job->source === 'vlm';
        });
    }

    #[Test]
    public function full_vlm_to_embedding_flow_works_correctly_via_queue(): void
    {
        // 準備 (Arrange)
        Config::set('rag.chunking.auto_update_chunks', true);
        Config::set('rag.enabled', true);
        Config::set('queue.default', 'sync'); // 同期実行でテスト

        $ledger = Ledger::factory()->create();

        // 最初のカラムIDを取得して使用
        // column_defineはColumnDefineオブジェクトの配列としてキャストされるため、プロパティとしてアクセスする
        $firstColumnId = $ledger->define->column_define[0]->id ?? 1;

        $attachedFile = AttachedFile::factory()
            ->forLedger($ledger)
            ->create([
                'path' => 'test.pdf',
                'hashedbasename' => 'test_hash', // ハッシュを固定
                'column_id' => $firstColumnId,   // 存在するカラムIDを使用
            ]);

        // content_attachedをこのファイル用に準備
        // これによりProcessLedgerForRagJobがVLM結果を正しい位置にマージできるようになる
        $ledger->update([
            'content_attached' => [
                $firstColumnId => [
                    'test_hash' => [
                        'originalName' => 'test.pdf',
                        'meta' => ['content' => 'Initial content'],
                    ],
                ],
            ],
        ]);

        Storage::disk('local')->put($attachedFile->path, 'dummy content');

        // 2. 実行 (Act)
        // VLMジョブをディスパッチ（同期実行）
        ProcessVlmExtraction::dispatch($attachedFile);

        // 3. 検証 (Assert)
        // チャンクが作成されたことを確認
        $this->assertDatabaseHas('ledger_chunks', [
            'ledger_id' => $ledger->id,
        ]);

        // Embeddingが生成されたことを確認
        // ファクトリ等で生成された他のチャンクと区別するため、VLMコンテンツを含むものを検索
        $chunk = LedgerChunk::where('ledger_id', $ledger->id)
            ->where('chunk_text', 'like', '%VLM Markdown Content%')
            ->first();

        $this->assertNotNull($chunk, 'VLM content chunk was not found.');
        $this->assertNotNull($chunk->embedding);
        $this->assertIsArray($chunk->embedding);

        // VLMのMarkdownがチャンクに含まれていることを確認
        $this->assertStringContainsString('VLM Markdown Content', $chunk->chunk_text);
    }
}
