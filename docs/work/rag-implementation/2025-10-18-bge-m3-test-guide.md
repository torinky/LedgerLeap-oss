# BGE-M3モデルでのWBS1追試ガイド

**作成日:** 2025年10月18日  
**対象モデル:** BAAI/bge-m3 (1024次元)  
**目的:** all-MiniLM-L6-v2 → bge-m3 への切り替えとWBS1機能検証

---

## 前提条件

### 1. embeddingコンテナの再ビルド

モデル変更後、embeddingコンテナを再ビルドしてください：

```bash
# コンテナ停止・削除
docker stop ledgerleap_embedding
docker rm ledgerleap_embedding
docker rmi ledgerleap-embedding

# 再ビルド（時間がかかります: 5-10分程度）
./vendor/bin/sail build --no-cache embedding

# 起動
./vendor/bin/sail up -d embedding

# モデルロード完了まで待機（1-2分）
# ログで確認:
docker logs -f ledgerleap_embedding
# "Successfully loaded model: 'BAAI/bge-m3'" が表示されるまで待つ
```

### 2. 設定確認

以下のファイルが正しく設定されていることを確認：

```bash
# .env
RAG_MODEL=bge-m3

# docker-compose.yml
environment:
  - EMBEDDING_MODEL=BAAI/bge-m3
```

---

## テスト実行方法

### 方法1: 自動テストスクリプト（推奨）

すべてのステップを自動実行します：

```bash
./bin/test-bge-m3.sh
```

**実行内容:**
1. 設定確認
2. Embeddingサービス ヘルスチェック
3. データベース準備（既存データクリーンアップ）
4. 単一テスト: Embedding生成
5. チャンク化テスト
6. ベンチマーク実行（5件同期処理）
7. データ検証

**所要時間:** 約3-5分（ベンチマーク含む）

---

### 方法2: PHPUnitテスト

個別のテストケースを実行：

```bash
# すべてのテスト実行
./vendor/bin/sail test --filter RagBgeM3Test

# 特定のテストのみ実行
./vendor/bin/sail test --filter test_bge_m3_model_configuration
./vendor/bin/sail test --filter test_embedding_generation_with_bge_m3
./vendor/bin/sail test --filter test_benchmark_scenario_1_with_bge_m3
```

---

### 方法3: 手動ステップ実行

#### Step 1: 設定確認

```bash
./vendor/bin/sail artisan tinker --execute='
echo "Active Model: " . config("rag.model.active") . "\n";
echo "Model Name: " . config("rag.model.available_models.bge-m3.name") . "\n";
echo "Dimension: " . config("rag.model.available_models.bge-m3.dimension") . "\n";
'
```

**期待される出力:**
```
Active Model: bge-m3
Model Name: BAAI/bge-m3
Dimension: 1024
```

---

#### Step 2: ヘルスチェック

```bash
docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health | jq .
```

**期待される出力:**
```json
{
  "status": "healthy",
  "model_is_loaded": true,
  "model_name": "BAAI/bge-m3"
}
```

---

#### Step 3: Embedding生成テスト

```bash
./vendor/bin/sail artisan tinker --execute='
$service = app(App\Services\EmbeddingService::class);
$embeddings = $service->embed(["テスト文章"]);
echo "Dimension: " . count($embeddings[0]) . "\n";
'
```

**期待される出力:**
```
Dimension: 1024
```

---

#### Step 4: ベンチマーク実行

```bash
./vendor/bin/sail artisan rag:benchmark --ledgers=5 --content-size=2000 --sync
```

**期待される出力例:**
```
Starting RAG Benchmark...
---------------------------
Ledgers to process: 5
Content size per ledger: 2000 chars
Dispatch mode: Synchronous
---------------------------
Using Ledger Define: #1 and User: #1
 5/5 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%
Benchmark finished.
-------------------
Total time: XX.XX seconds
Average time per ledger: X.XX seconds
```

---

## 性能比較

### all-MiniLM-L6-v2 vs BGE-M3

| 項目 | all-MiniLM-L6-v2 | BAAI/bge-m3 | 比較 |
|------|------------------|-------------|------|
| **モデルサイズ** | 90MB | 1.1GB | 12倍大きい |
| **次元数** | 384 | 1024 | 2.7倍高次元 |
| **処理時間/件** | 1.64秒 | 3-5秒（予測） | 2-3倍遅い |
| **メモリ使用量** | 低 | 高 | - |
| **日本語精度** | 普通 | 高 | BGE-M3が優位 |
| **多言語対応** | 限定的 | 優秀 | BGE-M3が優位 |

---

## トラブルシューティング

### エラー1: "Model is not loaded"

**原因:** モデルのロードが完了していない

**解決策:**
```bash
# ログを確認
docker logs ledgerleap_embedding

# "Successfully loaded model" が表示されるまで待つ
# BGE-M3は大きいので1-2分かかる場合があります
```

---

### エラー2: "Expected 1024 dimensions, got 384"

**原因:** 古いモデルがキャッシュされている

**解決策:**
```bash
# コンテナを完全に再ビルド
docker stop ledgerleap_embedding
docker rm ledgerleap_embedding
docker rmi ledgerleap-embedding
./vendor/bin/sail build --no-cache embedding
./vendor/bin/sail up -d embedding
```

---

### エラー3: Container crashes (Exit 139)

**原因:** メモリ不足

**解決策:**
```bash
# docker-compose.yml でメモリ制限を確認・増加
embedding:
  deploy:
    resources:
      limits:
        memory: 12G  # 8GB → 12GB に増やす
```

---

### エラー4: "SQLSTATE[HY000]: General error: 1364"

**原因:** マイグレーションが正しく実行されていない

**解決策:**
```bash
# マイグレーションをやり直す
./vendor/bin/sail artisan migrate:fresh --seed
```

---

## 検証チェックリスト

WBS1の完了を確認するためのチェックリスト：

- [ ] 設定ファイルで bge-m3 が選択されている
- [ ] embeddingサービスのヘルスチェックが成功
- [ ] Embedding生成で1024次元ベクトルが返される
- [ ] チャンク化が正常に動作（embeddingサイズ = 4096バイト）
- [ ] ベンチマークが完了（5件以上）
- [ ] 平均処理時間が10秒未満/件
- [ ] データベースに ledger_chunks レコードが作成されている
- [ ] Observer → Job → EmbeddingService の連携が動作

---

## 次のステップ

WBS1が完了したら、WBS2（検索ロジック実装）に進みます：

1. **RagSearchService の実装**
   - `mroonga_command()` を使ったベクトル検索
   - スコア集計ロジック

2. **ハイブリッド検索の実装**
   - キーワード検索 + ベクトル検索
   - スコアの統合

3. **API統合**
   - 既存の検索APIにRAG機能を組み込み

---

## 参考情報

### BGE-M3モデルの特徴

- **Multi-Functionality**: Dense retrieval, multi-vector retrieval, sparse retrieval の3つの検索方式をサポート
- **Multi-Linguality**: 100以上の言語をサポート（日本語も含む）
- **Multi-Granularity**: 短文から長文（最大8192トークン）まで対応

### 公式リンク

- Hugging Face: https://huggingface.co/BAAI/bge-m3
- 論文: https://arxiv.org/abs/2402.03216

---

**このガイドに従って、BGE-M3モデルでのWBS1追試を完了させてください。**
