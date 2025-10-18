# RAG性能テストスクリプトガイド

**作成日:** 2025年10月18日  
**スクリプト名:** `bin/test-rag-performance.sh`  
**目的:** モデルに依存しない汎用的なRAG性能テストスクリプト

---

## 概要

`test-rag-performance.sh`は、現在設定されているembeddingモデルで自動的にRAG機能の性能テストを実行するスクリプトです。モデル名を決め打ちせず、`.env`の設定に基づいて動作します。

---

## 主な改善点

### 旧スクリプト（test-bge-m3.sh）の問題

1. ❌ BGE-M3モデルに特化
2. ❌ ProcessLedgerForRagJobの存在を前提としていない部分があった
3. ❌ 次元数をハードコード（1024）

### 新スクリプト（test-rag-performance.sh）の特徴

1. ✅ **モデル非依存**: `.env`の`RAG_MODEL`設定を自動検出
2. ✅ **完全な実装準拠**: ProcessLedgerForRagJobを正しく使用
3. ✅ **動的な次元数**: config/rag.phpから自動取得
4. ✅ **詳細なログ出力**: 各ステップの結果を色分け表示
5. ✅ **性能設定表示**: バッチサイズ、スレッド数などを表示

---

## 実行方法

### 基本的な実行

```bash
./bin/test-rag-performance.sh
```

### 前提条件

1. **embeddingコンテナが起動していること**
   ```bash
   docker ps | grep embedding
   # STATUS: Up X minutes (healthy)
   ```

2. **モデルがロード済みであること**
   ```bash
   docker logs ledgerleap_embedding --tail 10
   # "Successfully loaded model" が表示されること
   ```

3. **シーダーが実行済みであること**
   ```bash
   # Userが存在することが必要
   ./vendor/bin/sail artisan tinker --execute='echo App\Models\User::count();'
   # 1以上であること
   ```

---

## テストの流れ

### Step 1: 設定確認

現在のRAG設定を表示します。

**確認項目:**
- RAG有効化状態
- アクティブなモデル名と次元数
- バッチサイズ、スレッド数などの性能設定

**期待される出力:**
```
=== RAG Configuration ===
RAG Enabled: true
Active Model: bge-m3
Model Name: BAAI/bge-m3
Dimension: 1024
...
=== Performance Settings ===
Batch Size: 4
Num Threads: 4
...
```

---

### Step 2: ヘルスチェック

Embeddingサービスが正常に動作しているか確認します。

**確認項目:**
- サービスの応答
- モデルのロード状態

**期待される出力:**
```json
{
  "status": "healthy",
  "model_is_loaded": true,
  "model_name": "BAAI/bge-m3"
}
```

**エラー時の対処:**
```bash
# モデルがロードされていない場合
docker logs ledgerleap_embedding --tail 50

# 必要に応じて再起動
./vendor/bin/sail restart embedding
sleep 90
```

---

### Step 3: データベース準備

テスト用のデータを準備します。

**実行内容:**
1. 既存のチャンクデータを削除
2. テスト用Folderを作成
3. テスト用LedgerDefineを作成

**期待される出力:**
```
✓ Existing data cleaned
✓ Folder created: #1
✓ LedgerDefine created: #1
```

---

### Step 4: 単一テスト - Embedding生成

2つのテキストでembedding生成をテストします。

**確認項目:**
- 生成成功
- 次元数が正しい
- 処理時間

**期待される出力:**
```
✓ Generated embeddings for 2 texts
  Dimension: 1024
  Time: 3.45 seconds
  Average: 1.73 seconds/text
```

---

### Step 5: チャンク化テスト

長文の台帳を作成し、チャンク化とembedding生成をテストします。

**実行内容:**
1. 長文台帳の作成
2. ProcessLedgerForRagJobの同期実行
3. 生成されたチャンクの検証

**期待される出力:**
```
✓ Ledger created: #1
Processing chunks (this may take time)...
✓ Chunks created: 3
  Processing time: 5.23 seconds
✓ All chunks have correct embedding size (4096 bytes)
```

---

### Step 6: ベンチマークテスト

`rag:benchmark`コマンドで小規模な性能テストを実行します。

**実行内容:**
- 3件の台帳を作成
- 各台帳に1000文字のコンテンツ
- 同期処理で実行

**期待される出力:**
```
Starting RAG Benchmark...
---------------------------
Ledgers to process: 3
Content size per ledger: 1000 chars
Dispatch mode: Synchronous
---------------------------
 3/3 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%
Benchmark finished.
-------------------
Total time: XX.XX seconds
Average time per ledger: X.XX seconds
```

---

### Step 7: データ検証

生成されたデータの整合性を検証します。

**確認項目:**
- チャンクが作成されたか
- Embeddingサイズが正しいか

**期待される出力:**
```
=== Database Statistics ===
Total ledgers: 4
Total chunks: 12
Sample chunk embedding size: 4096 bytes
Expected: 4096 bytes
✓ All validations passed
```

---

## 対応モデル

このスクリプトは以下のすべてのモデルで動作します：

| モデル | 次元数 | 設定 |
|--------|--------|------|
| **all-MiniLM-L6-v2** | 384 | `RAG_MODEL=all-minilm-l6-v2` |
| **BGE-M3** | 1024 | `RAG_MODEL=bge-m3` |
| **multilingual-e5-base** | 768 | `RAG_MODEL=multilingual-e5-base` |

---

## モデル切り替え手順

### 例: BGE-M3 → all-MiniLM-L6-v2

```bash
# 1. .envを編集
RAG_MODEL=all-minilm-l6-v2

# 2. docker-compose.ymlを編集
# embedding.environment.EMBEDDING_MODEL を変更
sed -i '' 's/EMBEDDING_MODEL=.*/EMBEDDING_MODEL=sentence-transformers\/all-MiniLM-L6-v2/' docker-compose.yml

# 3. コンテナ再ビルド
docker stop ledgerleap_embedding
docker rm ledgerleap_embedding
docker rmi ledgerleap-embedding
./vendor/bin/sail build --no-cache embedding
./vendor/bin/sail up -d embedding

# 4. モデルロード待機
sleep 90

# 5. テスト実行
./bin/test-rag-performance.sh
```

---

## トラブルシューティング

### エラー1: "Model is not loaded"

**原因:** モデルのロードが完了していない

**解決策:**
```bash
# ログを確認
docker logs ledgerleap_embedding --tail 50

# "Successfully loaded model" が表示されるまで待つ
# 大きなモデル（BGE-M3）は1-2分かかる
```

---

### エラー2: "No user found"

**原因:** シーダーが実行されていない

**解決策:**
```bash
./vendor/bin/sail artisan db:seed
```

---

### エラー3: "Operation timed out"

**原因:** タイムアウト設定が短すぎる

**解決策:**
```bash
# .envでタイムアウトを延長
EMBEDDING_SERVICE_TIMEOUT=300

# コンテナ再起動
./vendor/bin/sail restart embedding
```

---

### エラー4: "Expected X dimensions, got Y"

**原因:** モデル設定とコンテナのモデルが不一致

**解決策:**
```bash
# 設定を確認
grep "RAG_MODEL" .env
grep "EMBEDDING_MODEL" docker-compose.yml

# 不一致なら修正してコンテナ再ビルド
```

---

## 性能評価基準

### 合格基準

- ✅ すべてのステップが成功
- ✅ チャンクが正しく作成される
- ✅ Embeddingサイズが正しい
- ✅ コンテナがクラッシュしない

### 性能目標

| モデル | 環境 | 目標処理時間/件 |
|--------|------|----------------|
| all-MiniLM-L6-v2 | ARM64 | < 2秒 |
| all-MiniLM-L6-v2 | x86_64 | < 1秒 |
| BGE-M3 | ARM64 | < 60秒 |
| BGE-M3 | x86_64 | < 20秒 |

---

## 実行例

### 成功例（all-MiniLM-L6-v2）

```bash
$ ./bin/test-rag-performance.sh

==========================================
RAG WBS1 性能テスト
==========================================

[Step 1] 現在の設定を確認...
=== RAG Configuration ===
RAG Enabled: true
Active Model: all-minilm-l6-v2
Model Name: sentence-transformers/all-MiniLM-L6-v2
Dimension: 384
...

[Step 2] Embeddingサービス ヘルスチェック...
{
  "status": "healthy",
  "model_is_loaded": true,
  "model_name": "sentence-transformers/all-MiniLM-L6-v2"
}
✓ Health check passed

...

✓ WBS1 性能テスト完了

Summary:
  Model: sentence-transformers/all-MiniLM-L6-v2 (384 dimensions)
  Batch Size: 8
  Num Threads: 4
  Total ledgers: 4
  Total chunks: 15
```

---

## 関連ファイル

- **`bin/test-rag-performance.sh`**: メインスクリプト
- **`app/Jobs/ProcessLedgerForRagJob.php`**: チャンク化処理
- **`app/Services/EmbeddingService.php`**: Embedding生成
- **`app/Console/Commands/RagBenchmarkCommand.php`**: ベンチマークコマンド
- **`config/rag.php`**: RAG設定

---

## まとめ

`test-rag-performance.sh`は、モデルに依存しない汎用的なRAG性能テストスクリプトです。

**主な特徴:**
- ✅ モデル自動検出
- ✅ 完全な実装準拠
- ✅ 詳細なログ出力
- ✅ 7ステップの包括的なテスト

**使い方:**
```bash
# 設定確認 → コンテナ起動 → テスト実行
./bin/test-rag-performance.sh
```

このスクリプトでWBS1の性能テストを完了させ、次のステップ（WBS2: 検索ロジック実装）に進むことができます。
