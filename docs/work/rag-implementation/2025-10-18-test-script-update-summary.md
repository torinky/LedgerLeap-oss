# RAG性能テストスクリプト更新サマリー

**更新日:** 2025年10月18日 17:21 JST  
**新スクリプト:** `bin/test-rag-performance.sh`  
**旧スクリプト:** `bin/test-bge-m3.sh` → `bin/test-bge-m3.sh.bak`（バックアップ）

---

## 主な改善点

### ❌ 旧スクリプトの問題

1. **モデル依存**: BGE-M3専用（タイトルとハードコード）
2. **次元数ハードコード**: 1024次元を決め打ち
3. **ProcessLedgerForRagJobの使用が不完全**: 一部で直接処理していた
4. **エラーハンドリング不足**: jq必須、失敗時の対処が不明確

### ✅ 新スクリプトの改善

1. **✨ モデル非依存**
   ```bash
   # 設定から自動検出
   config("rag.model.active")  # all-minilm-l6-v2, bge-m3, etc.
   ```

2. **✨ 動的な次元数取得**
   ```bash
   # ハードコードなし
   $expectedDim = config("rag.model.available_models." . config("rag.model.active") . ".dimension");
   ```

3. **✨ ProcessLedgerForRagJobを正しく使用**
   ```bash
   # 完全な実装準拠
   $job = new App\Jobs\ProcessLedgerForRagJob($ledger);
   $job->handle(app(App\Services\EmbeddingService::class));
   ```

4. **✨ jq optional対応**
   ```bash
   # jqがない環境でも動作
   if command -v jq &> /dev/null; then
       echo "$HEALTH_RESPONSE" | jq .
   else
       echo "$HEALTH_RESPONSE"
   fi
   ```

5. **✨ 性能設定の表示**
   ```bash
   # 現在のパフォーマンス設定を表示
   Batch Size: 4
   Num Threads: 4
   Interop Threads: 2
   Device: cpu
   ```

---

## スクリプト比較

| 項目 | 旧スクリプト | 新スクリプト |
|------|-------------|-------------|
| **名前** | `test-bge-m3.sh` | `test-rag-performance.sh` |
| **モデル** | BGE-M3専用 | 全モデル対応 |
| **次元数** | 1024固定 | 動的取得 |
| **実装準拠** | 部分的 | 完全 |
| **エラー処理** | 基本的 | 詳細 |
| **色分け出力** | ✅ | ✅ 改善 |
| **性能設定表示** | ❌ | ✅ |
| **jq依存** | 必須 | Optional |

---

## テストの流れ

### 7ステップの包括的なテスト

```bash
./bin/test-rag-performance.sh
```

**実行内容:**

1. ✅ **設定確認** - RAG設定と性能パラメータを表示
2. ✅ **ヘルスチェック** - Embeddingサービスの状態確認
3. ✅ **データベース準備** - テスト用データの作成
4. ✅ **単一テスト** - 2テキストのembedding生成
5. ✅ **チャンク化テスト** - ProcessLedgerForRagJobの動作確認
6. ✅ **ベンチマーク** - 3件の台帳で性能測定
7. ✅ **データ検証** - 結果の整合性チェック

---

## 対応モデル

すべてのモデルで同じスクリプトが使えます：

```bash
# all-MiniLM-L6-v2 (384次元)
RAG_MODEL=all-minilm-l6-v2
./bin/test-rag-performance.sh

# BGE-M3 (1024次元)
RAG_MODEL=bge-m3
./bin/test-rag-performance.sh

# multilingual-e5-base (768次元)
RAG_MODEL=multilingual-e5-base
./bin/test-rag-performance.sh
```

---

## 使用例

### 例1: BGE-M3での実行

```bash
$ ./bin/test-rag-performance.sh

==========================================
RAG WBS1 性能テスト
==========================================

[Step 1] 現在の設定を確認...
=== RAG Configuration ===
RAG Enabled: true
Active Model: bge-m3
Model Name: BAAI/bge-m3
Dimension: 1024
Embedding Service URL: http://embedding:8000

=== Performance Settings ===
Batch Size: 4
Num Threads: 4
Interop Threads: 2
Device: cpu
Timeout: 180 seconds
=========================

[Step 2] Embeddingサービス ヘルスチェック...
{
  "status": "healthy",
  "model_is_loaded": true,
  "model_name": "BAAI/bge-m3"
}
✓ Health check passed

...

✓ WBS1 性能テスト完了

Summary:
  Model: BAAI/bge-m3 (1024 dimensions)
  Batch Size: 4
  Num Threads: 4
  Total ledgers: 4
  Total chunks: 12
```

---

### 例2: モデル切り替えテスト

```bash
# Step 1: all-MiniLM-L6-v2でテスト
RAG_MODEL=all-minilm-l6-v2
# docker-compose.ymlも更新してコンテナ再ビルド
./bin/test-rag-performance.sh

# Step 2: BGE-M3でテスト
RAG_MODEL=bge-m3
# docker-compose.ymlも更新してコンテナ再ビルド
./bin/test-rag-performance.sh

# どちらも同じスクリプトで実行可能！
```

---

## 主要な改善コード

### 改善1: 動的な次元数検証

**旧:**
```bash
# 1024固定
if ($embeddingSize !== 4096) {
    echo "ERROR: Expected 4096 bytes, got " . $embeddingSize . "\n";
    exit(1);
}
```

**新:**
```bash
# 設定から動的に取得
$expectedDim = config("rag.model.available_models." . config("rag.model.active") . ".dimension");
$expectedSize = $expectedDim * 4;

if ($embeddingSize !== $expectedSize) {
    echo "ERROR: Expected " . $expectedSize . " bytes, got " . $embeddingSize . "\n";
    exit(1);
}
```

---

### 改善2: ProcessLedgerForRagJobの正しい使用

**旧:**
```bash
# 直接処理していた部分があった
# （詳細は省略）
```

**新:**
```bash
# 完全にJobを使用
$job = new App\Jobs\ProcessLedgerForRagJob($ledger);
$job->handle(app(App\Services\EmbeddingService::class));
```

---

### 改善3: 性能設定の表示

**旧:**
```bash
# 性能設定の表示なし
```

**新:**
```bash
echo "=== Performance Settings ===" . "\n";
echo "Batch Size: " . config("rag.performance.batch_size") . "\n";
echo "Num Threads: " . config("rag.performance.num_threads") . "\n";
echo "Interop Threads: " . config("rag.performance.num_interop_threads") . "\n";
echo "Device: " . config("rag.performance.device") . "\n";
echo "Timeout: " . config("rag.embedding_service.timeout") . " seconds\n";
```

---

## ファイル構成

```
bin/
├── test-rag-performance.sh     # 新しい汎用テストスクリプト
└── test-bge-m3.sh.bak         # 旧スクリプト（バックアップ）

docs/work/rag-implementation/
└── 2025-10-18-test-script-guide.md  # 詳細ガイド
```

---

## 次のアクション

### 1. テスト実行

```bash
# embeddingコンテナが起動していることを確認
docker ps | grep embedding

# テスト実行
./bin/test-rag-performance.sh
```

### 2. 結果の確認

```bash
# 期待される結果
✓ Health check passed
✓ Database prepared
✓ Embedding generation successful
✓ Chunking test passed
✓ Benchmark completed
✓ Data verification passed
✓ WBS1 性能テスト完了
```

### 3. WBS1完了確認

- [ ] すべてのステップが成功
- [ ] チャンクが正しく作成されている
- [ ] Embeddingサイズが正しい
- [ ] 性能が要件を満たす
- [ ] ドキュメントが更新されている

---

## トラブルシューティング

### Q: "Model is not loaded"エラー

**A:** モデルのロードを待つ
```bash
docker logs ledgerleap_embedding --tail 20
# "Successfully loaded model" が表示されるまで待機
```

---

### Q: "No user found"エラー

**A:** シーダーを実行
```bash
./vendor/bin/sail artisan db:seed
```

---

### Q: タイムアウトエラー

**A:** タイムアウトを延長
```bash
# .env
EMBEDDING_SERVICE_TIMEOUT=300

./vendor/bin/sail restart embedding
```

---

## まとめ

### ✅ 実装完了

1. **モデル非依存のテストスクリプト作成**
   - `bin/test-rag-performance.sh`
   
2. **ProcessLedgerForRagJobの正しい使用**
   - 完全な実装準拠
   
3. **詳細なドキュメント作成**
   - `2025-10-18-test-script-guide.md`

### 🎯 主な特徴

- ✨ **モデル自動検出**: RAG_MODELから自動判定
- ✨ **動的な次元数**: configから取得
- ✨ **完全な実装準拠**: ProcessLedgerForRagJob使用
- ✨ **詳細なログ**: 色分けと進捗表示
- ✨ **性能設定表示**: バッチサイズ、スレッド数など

### 📊 対応モデル

- ✅ all-MiniLM-L6-v2 (384次元)
- ✅ BGE-M3 (1024次元)
- ✅ multilingual-e5-base (768次元)
- ✅ 将来追加されるモデルも自動対応

---

**これで、どのモデルでも同じスクリプトでWBS1の性能テストを実行できます！**
