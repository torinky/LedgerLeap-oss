<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RagBgeM3Test extends TestCase
{
    use DatabaseMigrations;

    private User $user;
    private Folder $folder;
    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // RAG機能を有効化
        config(['rag.enabled' => true]);
        config(['rag.model.active' => 'bge-m3']);

        // テストデータの準備
        $this->user = User::factory()->create();
        $this->folder = Folder::create([
            'name' => 'BGE-M3テスト用フォルダ',
            'title' => 'BGE-M3テスト用フォルダ',
            'detail' => 'BAAI/bge-m3モデルのテスト用',
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $this->ledgerDefine = LedgerDefine::create([
            'name' => 'BGE-M3テスト用台帳',
            'title' => 'RAG性能テスト用台帳（BGE-M3）',
            'ledger_label' => 'BGEM3',
            'detail_description' => 'BAAI/bge-m3モデルでのRAG性能テスト',
            'folder_id' => $this->folder->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'column_define' => [
                ['id' => 1, 'name' => 'title', 'type' => 'text', 'label' => 'タイトル', 'order' => 1, 'required' => true],
                ['id' => 2, 'name' => 'body', 'type' => 'textarea', 'label' => '本文', 'order' => 2, 'required' => false]
            ]
        ]);
    }

    #[Test]
    public function test_bge_m3_model_configuration()
    {
        $this->assertEquals('bge-m3', config('rag.model.active'));
        $this->assertEquals('BAAI/bge-m3', config('rag.model.available_models.bge-m3.name'));
        $this->assertEquals(1024, config('rag.model.available_models.bge-m3.dimension'));
    }

    #[Test]
    public function test_embedding_service_health_check()
    {
        $response = Http::timeout(10)->get(config('rag.embedding_service.url') . '/health');

        $this->assertTrue($response->successful(), 'Embedding service health check failed');

        $data = $response->json();
        $this->assertTrue($data['model_is_loaded'] ?? false, 'Model is not loaded');
        $this->assertStringContainsString('bge-m3', strtolower($data['model_name'] ?? ''), 'Wrong model loaded');
    }

    #[Test]
    public function test_embedding_generation_with_bge_m3()
    {
        $embeddingService = app(\App\Services\EmbeddingService::class);

        $testTexts = [
            'これはBGE-M3モデルのテストです。',
            'セマンティック検索の精度を検証します。',
            'This is a test for multilingual embedding.'
        ];

        $embeddings = $embeddingService->embed($testTexts);

        $this->assertIsArray($embeddings);
        $this->assertCount(3, $embeddings);

        foreach ($embeddings as $embedding) {
            $this->assertIsArray($embedding);
            $this->assertCount(1024, $embedding, 'BGE-M3 should produce 1024-dimensional vectors');
        }
    }

    #[Test]
    public function test_ledger_chunk_creation_with_bge_m3()
    {
        // 2000文字以上のテストコンテンツを作成
        $longText = str_repeat('これはBGE-M3モデルでのチャンク化テストです。日本語テキストが正しくチャンク化され、1024次元のベクトルに変換されることを確認します。', 50);

        $ledger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->folder->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'content' => [
                'title' => 'BGE-M3チャンク化テスト',
                'body' => $longText
            ]
        ]);

        // Observerが発火するまで待機（同期的に処理）
        // 実際の環境ではJobがキューで実行されるが、テストでは同期実行
        $job = new \App\Jobs\ProcessLedgerForRagJob($ledger);
        $job->handle(app(\App\Services\EmbeddingService::class));

        // チャンクが作成されたことを確認
        $chunks = DB::table('ledger_chunks')
            ->where('ledger_id', $ledger->id)
            ->get();

        $this->assertGreaterThan(0, $chunks->count(), 'No chunks were created');

        // 各チャンクのembeddingサイズを確認（1024次元 × 4バイト = 4096バイト）
        foreach ($chunks as $chunk) {
            $embeddingSize = strlen($chunk->embedding);
            $this->assertEquals(4096, $embeddingSize, "Expected 4096 bytes (1024 dimensions × 4 bytes), got {$embeddingSize}");
        }
    }

    #[Test]
    public function test_benchmark_scenario_1_with_bge_m3()
    {
        $startTime = microtime(true);
        $ledgerCount = 5;
        $createdLedgers = [];

        for ($i = 1; $i <= $ledgerCount; $i++) {
            $content = str_repeat("BGE-M3ベンチマークテスト #{$i}。この台帳はBAAI/bge-m3モデルの性能を測定するために作成されました。", 25);

            $ledger = Ledger::create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'folder_id' => $this->folder->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
                'tenant_id' => $this->user->tenant_id,
                'content' => [
                    'title' => "BGE-M3ベンチマーク #{$i}",
                    'body' => $content
                ]
            ]);

            // 同期的にJob実行
            $job = new \App\Jobs\ProcessLedgerForRagJob($ledger);
            $job->handle(app(\App\Services\EmbeddingService::class));

            $createdLedgers[] = $ledger->id;
        }

        $totalTime = microtime(true) - $startTime;
        $averageTime = $totalTime / $ledgerCount;

        // すべての台帳のチャンクが作成されたことを確認
        $totalChunks = DB::table('ledger_chunks')
            ->whereIn('ledger_id', $createdLedgers)
            ->count();

        $this->assertGreaterThan(0, $totalChunks, 'No chunks were created for any ledger');

        // パフォーマンスログ出力
        echo "\n";
        echo "=== BGE-M3 Benchmark Results ===\n";
        echo "Ledgers processed: {$ledgerCount}\n";
        echo "Total chunks created: {$totalChunks}\n";
        echo "Total time: " . number_format($totalTime, 2) . " seconds\n";
        echo "Average time per ledger: " . number_format($averageTime, 2) . " seconds\n";
        echo "Throughput: " . number_format(60 / $averageTime, 2) . " ledgers/minute\n";
        echo "================================\n\n";

        // 性能基準の確認（BGE-M3は大きいモデルなので、all-MiniLM-L6-v2より遅くなることを許容）
        $this->assertLessThan(10, $averageTime, 'Average processing time exceeds 10 seconds per ledger');
    }

    #[Test]
    public function test_embedding_vector_quality()
    {
        $embeddingService = app(\App\Services\EmbeddingService::class);

        // 意味的に類似したテキスト
        $similarTexts = [
            '今日は良い天気です。',
            '本日は晴天に恵まれています。'
        ];

        // 意味的に異なるテキスト
        $differentText = 'プログラミング言語のPythonについて学習しています。';

        $embeddings = $embeddingService->embed(array_merge($similarTexts, [$differentText]));

        // コサイン類似度を計算
        $similarity_1_2 = $this->cosineSimilarity($embeddings[0], $embeddings[1]);
        $similarity_1_3 = $this->cosineSimilarity($embeddings[0], $embeddings[2]);

        echo "\n";
        echo "=== Semantic Similarity Test (BGE-M3) ===\n";
        echo "Similarity (類似文1 vs 類似文2): " . number_format($similarity_1_2, 4) . "\n";
        echo "Similarity (類似文1 vs 異なる文): " . number_format($similarity_1_3, 4) . "\n";
        echo "=========================================\n\n";

        // 類似したテキストの方が、異なるテキストよりも類似度が高いことを確認
        $this->assertGreaterThan($similarity_1_3, $similarity_1_2, 'Similar texts should have higher similarity than different texts');
    }

    /**
     * コサイン類似度を計算
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] * $vec1[$i];
            $magnitude2 += $vec2[$i] * $vec2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}
