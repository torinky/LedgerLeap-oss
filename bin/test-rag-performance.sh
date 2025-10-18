#!/bin/bash

set -e

echo "=========================================="
echo "RAG WBS1 性能テスト"
echo "=========================================="
echo ""

# 色定義
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ステップ1: 設定確認
echo -e "${YELLOW}[Step 1]${NC} 現在の設定を確認..."
./vendor/bin/sail artisan tinker --execute='
echo "=== RAG Configuration ===" . "\n";
echo "RAG Enabled: " . (config("rag.enabled") ? "true" : "false") . "\n";
echo "Active Model: " . config("rag.model.active") . "\n";
echo "Model Name: " . config("rag.model.available_models." . config("rag.model.active") . ".name") . "\n";
echo "Dimension: " . config("rag.model.available_models." . config("rag.model.active") . ".dimension") . "\n";
echo "Embedding Service URL: " . config("rag.embedding_service.url") . "\n";
echo "\n=== Performance Settings ===" . "\n";
echo "Batch Size: " . config("rag.performance.batch_size") . "\n";
echo "Num Threads: " . config("rag.performance.num_threads") . "\n";
echo "Interop Threads: " . config("rag.performance.num_interop_threads") . "\n";
echo "Device: " . config("rag.performance.device") . "\n";
echo "Timeout: " . config("rag.embedding_service.timeout") . " seconds\n";
echo "=========================" . "\n";
' 2>&1 | grep -v "DEPRECATED"

# ステップ2: embeddingサービスのヘルスチェック
echo ""
echo -e "${YELLOW}[Step 2]${NC} Embeddingサービス ヘルスチェック..."
HEALTH_RESPONSE=$(docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health 2>&1 || echo '{"status":"error"}')

# JSONパース（jqがない場合でも動作するように）
if command -v jq &> /dev/null; then
    echo "$HEALTH_RESPONSE" | jq .
    MODEL_LOADED=$(echo "$HEALTH_RESPONSE" | jq -r '.model_is_loaded // "false"')
else
    echo "$HEALTH_RESPONSE"
    if echo "$HEALTH_RESPONSE" | grep -q '"model_is_loaded":true'; then
        MODEL_LOADED="true"
    else
        MODEL_LOADED="false"
    fi
fi

if [ "$MODEL_LOADED" != "true" ]; then
    echo -e "${RED}ERROR: Model is not loaded. Please wait for model loading to complete.${NC}"
    echo "Check logs: docker logs ledgerleap_embedding"
    exit 1
fi

echo -e "${GREEN}✓ Health check passed${NC}"

# ステップ3: データベース準備
echo ""
echo -e "${YELLOW}[Step 3]${NC} データベース準備..."
./vendor/bin/sail artisan tinker --execute='
DB::table("ledger_chunks")->truncate();
DB::table("ledgers")->delete();
DB::table("ledger_defines")->delete();
DB::table("folders")->delete();
echo "✓ Existing data cleaned\n";
' 2>&1 | grep -v "DEPRECATED"

# Folderとdefineの作成
./vendor/bin/sail artisan tinker --execute='
$user = App\Models\User::first();
if (!$user) {
    echo "ERROR: No user found. Please run seeders first.\n";
    exit(1);
}

$folder = App\Models\Folder::create([
    "name" => "RAG性能テスト用フォルダ",
    "title" => "RAG性能テスト用フォルダ",
    "detail" => "RAG機能の性能テスト用",
    "creator_id" => $user->id,
    "modifier_id" => $user->id,
    "tenant_id" => $user->tenant_id,
]);

$define = new App\Models\LedgerDefine([
    "name" => "RAG性能テスト用台帳",
    "title" => "RAG性能テスト用台帳",
    "ledger_label" => "RAGTEST",
    "detail_description" => "RAG機能の性能テスト用台帳定義",
    "folder_id" => $folder->id,
    "creator_id" => $user->id,
    "modifier_id" => $user->id,
    "tenant_id" => $user->tenant_id,
    "column_define" => [
        ["id" => 1, "name" => "title", "type" => "text", "label" => "タイトル", "order" => 1, "required" => true],
        ["id" => 2, "name" => "body", "type" => "textarea", "label" => "本文", "order" => 2, "required" => false]
    ]
]);
$define->save();

echo "✓ Folder created: #" . $folder->id . "\n";
echo "✓ LedgerDefine created: #" . $define->id . "\n";
' 2>&1 | grep -v "DEPRECATED"

echo -e "${GREEN}✓ Database prepared${NC}"

# ステップ4: 単一テスト - Embedding生成
echo ""
echo -e "${YELLOW}[Step 4]${NC} 単一テスト: Embedding生成..."
./vendor/bin/sail artisan tinker --execute='
$service = app(App\Services\EmbeddingService::class);
$texts = [
    "これはRAG機能のテストです。",
    "セマンティック検索の精度を検証します。"
];

echo "Generating embeddings for " . count($texts) . " texts...\n";
$startTime = microtime(true);
$embeddings = $service->embed($texts);
$elapsedTime = microtime(true) - $startTime;

echo "✓ Generated embeddings for " . count($embeddings) . " texts\n";
echo "  Dimension: " . count($embeddings[0]) . "\n";
echo "  Time: " . number_format($elapsedTime, 2) . " seconds\n";
echo "  Average: " . number_format($elapsedTime / count($texts), 2) . " seconds/text\n";

$expectedDim = config("rag.model.available_models." . config("rag.model.active") . ".dimension");
if (count($embeddings[0]) !== $expectedDim) {
    echo "ERROR: Expected " . $expectedDim . " dimensions, got " . count($embeddings[0]) . "\n";
    exit(1);
}
' 2>&1 | grep -v "DEPRECATED"

echo -e "${GREEN}✓ Embedding generation successful${NC}"

# ステップ5: チャンク化テスト
echo ""
echo -e "${YELLOW}[Step 5]${NC} チャンク化テスト..."
./vendor/bin/sail artisan tinker --execute='
$user = App\Models\User::first();
$folder = App\Models\Folder::first();
$define = App\Models\LedgerDefine::first();

$longText = str_repeat("これはRAG機能のチャンク化テストです。テキストが正しくチャンク化され、ベクトルに変換されることを確認します。", 50);

echo "Creating test ledger with long content...\n";
$ledger = App\Models\Ledger::create([
    "ledger_define_id" => $define->id,
    "folder_id" => $folder->id,
    "creator_id" => $user->id,
    "modifier_id" => $user->id,
    "tenant_id" => $user->tenant_id,
    "content" => [
        "title" => "RAGチャンク化テスト",
        "body" => $longText
    ]
]);

echo "✓ Ledger created: #" . $ledger->id . "\n";
echo "Processing chunks (this may take time)...\n";

// 同期的にJob実行
$startTime = microtime(true);
$job = new App\Jobs\ProcessLedgerForRagJob($ledger);
$job->handle(app(App\Services\EmbeddingService::class));
$elapsedTime = microtime(true) - $startTime;

$chunks = DB::table("ledger_chunks")->where("ledger_id", $ledger->id)->get();
echo "✓ Chunks created: " . $chunks->count() . "\n";
echo "  Processing time: " . number_format($elapsedTime, 2) . " seconds\n";

if ($chunks->count() === 0) {
    echo "ERROR: No chunks were created\n";
    exit(1);
}

// 各チャンクのembeddingサイズを確認
$expectedDim = config("rag.model.available_models." . config("rag.model.active") . ".dimension");
$expectedSize = $expectedDim * 4; // 4 bytes per float

foreach ($chunks as $chunk) {
    $embeddingSize = strlen($chunk->embedding);
    if ($embeddingSize !== $expectedSize) {
        echo "ERROR: Expected " . $expectedSize . " bytes, got " . $embeddingSize . "\n";
        exit(1);
    }
}

echo "✓ All chunks have correct embedding size (" . $expectedSize . " bytes)\n";
' 2>&1 | grep -v "DEPRECATED"

echo -e "${GREEN}✓ Chunking test passed${NC}"

# ステップ6: ベンチマークテスト
echo ""
echo -e "${YELLOW}[Step 6]${NC} ベンチマーク実行..."
echo -e "${BLUE}小規模テスト（3件、1000文字）${NC}"
./vendor/bin/sail artisan rag:benchmark --ledgers=3 --content-size=1000 --sync 2>&1

echo ""
echo -e "${GREEN}✓ Benchmark completed${NC}"

# ステップ7: データ検証
echo ""
echo -e "${YELLOW}[Step 7]${NC} データ検証..."
./vendor/bin/sail artisan tinker --execute='
$totalLedgers = DB::table("ledgers")->count();
$totalChunks = DB::table("ledger_chunks")->count();

echo "=== Database Statistics ===" . "\n";
echo "Total ledgers: " . $totalLedgers . "\n";
echo "Total chunks: " . $totalChunks . "\n";

if ($totalChunks === 0) {
    echo "ERROR: No chunks were created\n";
    exit(1);
}

// サンプルチェック
$sample = DB::table("ledger_chunks")->first();
$embeddingSize = strlen($sample->embedding);
$expectedDim = config("rag.model.available_models." . config("rag.model.active") . ".dimension");
$expectedSize = $expectedDim * 4;

echo "Sample chunk embedding size: " . $embeddingSize . " bytes\n";
echo "Expected: " . $expectedSize . " bytes\n";

if ($embeddingSize !== $expectedSize) {
    echo "ERROR: Incorrect embedding size\n";
    exit(1);
}

echo "✓ All validations passed\n";
' 2>&1 | grep -v "DEPRECATED"

echo -e "${GREEN}✓ Data verification passed${NC}"

# 完了
echo ""
echo "=========================================="
echo -e "${GREEN}✓ WBS1 性能テスト完了${NC}"
echo "=========================================="
echo ""
echo "Summary:"
./vendor/bin/sail artisan tinker --execute='
$model = config("rag.model.available_models." . config("rag.model.active"));
echo "  Model: " . $model["name"] . " (" . $model["dimension"] . " dimensions)\n";
echo "  Batch Size: " . config("rag.performance.batch_size") . "\n";
echo "  Num Threads: " . config("rag.performance.num_threads") . "\n";
echo "  Total ledgers: " . DB::table("ledgers")->count() . "\n";
echo "  Total chunks: " . DB::table("ledger_chunks")->count() . "\n";
' 2>&1 | grep -v "DEPRECATED"
echo ""
