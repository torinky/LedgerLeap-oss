# モデル切り替えスクリプトガイド

**作成日:** 2025年10月18日  
**スクリプト:** `bin/switch-model.sh`  
**目的:** RAG Embeddingモデルを簡単に切り替える

---

## 概要

`switch-model.sh`は、RAG機能で使用するembeddingモデルを自動的に切り替えるスクリプトです。`.env`と`docker-compose.yml`の更新、コンテナの再ビルドまで一括で実行します。

---

## 使い方

### 基本的な使い方

```bash
# モデル一覧を表示
./bin/switch-model.sh

# モデルを切り替え
./bin/switch-model.sh <model-key>
```

### 利用可能なモデル

| Key | Model | Dimensions | Description |
|-----|-------|-----------|-------------|
| **ruri-v3-30m** | ruri-nakamura/ruri-v3-30m | 768 | ⭐ 推奨（ARM64開発環境） |
| **multilingual-e5-small** | intfloat/multilingual-e5-small | 384 | 軽量多言語 |
| **multilingual-e5-base** | intfloat/multilingual-e5-base | 768 | バランス型 |
| **all-minilm-l6-v2** | sentence-transformers/all-MiniLM-L6-v2 | 384 | 超高速（英語中心） |
| **granite-embedding-107m** | ibm/granite-embedding-107m-multilingual | 1024 | コード検索対応 |
| **bge-m3** | BAAI/bge-m3 | 1024 | 高品質（x86_64推奨） |

---

## 実行例

### 例1: 推奨モデル（ruri-v3-30m）に切り替え

```bash
$ ./bin/switch-model.sh ruri-v3-30m

==========================================
RAG Embedding Model Switcher
==========================================

==========================================
Switching to: ruri-v3-30m
==========================================
  Model: ruri-nakamura/ruri-v3-30m
  Dimensions: 768
  Description: Fast Japanese model (recommended for ARM64)

Continue? (y/n) y

[Step 1/6] Updating .env file...
  ✓ Updated RAG_MODEL=ruri-v3-30m

[Step 2/6] Updating docker-compose.yml...
  ✓ Updated EMBEDDING_MODEL=ruri-nakamura/ruri-v3-30m

[Step 3/6] Stopping existing embedding container...
  ✓ Container stopped

[Step 4/6] Removing old image...
  ✓ Old image removed

[Step 5/6] Building new container...
  This may take a few minutes...
  ✓ Container built

[Step 6/6] Starting embedding container...
  ✓ Container started

==========================================
✓ Model switch completed!
==========================================

Next steps:
  1. Wait for model to load (30-90 seconds depending on size)
     docker logs -f ledgerleap_embedding

  2. Check health status:
     docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health

  3. Run performance test:
     ./bin/test-rag-performance.sh
```

---

### 例2: 多言語モデルに切り替え

```bash
./bin/switch-model.sh multilingual-e5-small
```

---

### 例3: モデル一覧を表示

```bash
$ ./bin/switch-model.sh --list

Current Configuration:
  RAG_MODEL: bge-m3
  EMBEDDING_MODEL: BAAI/bge-m3

==========================================
Available Embedding Models
==========================================

Key                     Dimensions  Description
------------------------------------------------------------
all-minilm-l6-v2        384         Ultra-fast (English-focused)
bge-m3                  1024        High-quality (slow on ARM64)
granite-embedding-107m  1024        Code search capable
multilingual-e5-base    768         Balanced multilingual
multilingual-e5-small   384         Lightweight multilingual
ruri-v3-30m             768         Fast Japanese model (recommended for ARM64)

Recommendations:
  ⭐ ruri-v3-30m           - Best for ARM64 development
  ⭐ multilingual-e5-small  - Good for multilingual apps
  ⭐ multilingual-e5-base   - Balanced quality/speed
```

---

## スクリプトの処理内容

### Step 1: .envの更新

`RAG_MODEL`を指定されたモデルキーに更新します。

```bash
# Before
RAG_MODEL=bge-m3

# After
RAG_MODEL=ruri-v3-30m
```

### Step 2: docker-compose.ymlの更新

`EMBEDDING_MODEL`をモデルの完全な名前に更新します。

```yaml
# Before
EMBEDDING_MODEL=BAAI/bge-m3

# After
EMBEDDING_MODEL=ruri-nakamura/ruri-v3-30m
```

### Step 3: コンテナの停止

既存のembeddingコンテナを完全に停止します。

```bash
./vendor/bin/sail down embedding
```

### Step 4: 古いイメージの削除

古いモデルを含むDockerイメージを削除します。

```bash
docker rmi ledgerleap-embedding
```

### Step 5: 新しいコンテナのビルド

新しいモデルを使用するコンテナをビルドします。

```bash
./vendor/bin/sail build --no-cache embedding
```

### Step 6: コンテナの起動

新しいコンテナを起動します。

```bash
./vendor/bin/sail up -d embedding
```

---

## 切り替え後の確認手順

### 1. モデルロードの待機

モデルのサイズによって待機時間が異なります：

| モデル | 待機時間 |
|--------|---------|
| all-minilm-l6-v2 | 30秒 |
| multilingual-e5-small | 30-40秒 |
| ruri-v3-30m | 40-50秒 |
| multilingual-e5-base | 50-60秒 |
| granite-embedding-107m | 60-90秒 |
| bge-m3 | 90-120秒 |

**ログで確認:**
```bash
docker logs -f ledgerleap_embedding

# "Successfully loaded model" が表示されるまで待つ
```

---

### 2. ヘルスチェック

```bash
docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health | jq .
```

**期待される出力:**
```json
{
  "status": "healthy",
  "model_is_loaded": true,
  "model_name": "ruri-nakamura/ruri-v3-30m"
}
```

---

### 3. 性能テスト

```bash
./bin/test-rag-performance.sh
```

**期待される結果:**
- すべてのステップが成功
- 処理時間が改善
- Embeddingの次元数が正しい

---

## 推奨モデル

### 開発環境（ARM64）

**1位: ruri-v3-30m**

```bash
./bin/switch-model.sh ruri-v3-30m
```

**理由:**
- ✅ 最速（BGE-M3の40-60倍）
- ✅ 高い日本語品質
- ✅ 768次元
- ✅ 軽量（148MB）

---

**2位: multilingual-e5-small**

```bash
./bin/switch-model.sh multilingual-e5-small
```

**理由:**
- ✅ 多言語対応が必須の場合
- ✅ 実績豊富
- ✅ 非常に安定

---

**3位: multilingual-e5-base**

```bash
./bin/switch-model.sh multilingual-e5-base
```

**理由:**
- ✅ 品質重視
- ✅ 768次元
- ✅ 多言語対応

---

### 本番環境（x86_64）

**推奨: bge-m3**

```bash
./bin/switch-model.sh bge-m3
```

**理由:**
- ✅ 最高品質
- ✅ 1024次元
- ✅ x86_64では実用的な速度（10-15秒/text）

---

## トラブルシューティング

### Q1: "Container still running"エラー

**原因:** コンテナが完全に停止していない

**解決策:**
```bash
# 手動で停止
docker stop ledgerleap_embedding
docker rm ledgerleap_embedding

# スクリプト再実行
./bin/switch-model.sh <model-key>
```

---

### Q2: ビルドが失敗する

**原因:** Dockerの容量不足やネットワークエラー

**解決策:**
```bash
# Dockerのクリーンアップ
docker system prune -a

# 再試行
./bin/switch-model.sh <model-key>
```

---

### Q3: モデルがロードされない

**原因:** モデル名の誤り、またはHugging Face Hubへの接続エラー

**確認:**
```bash
# ログを確認
docker logs ledgerleap_embedding --tail 50

# エラーメッセージを確認
```

**解決策:**
```bash
# コンテナを再起動
./vendor/bin/sail restart embedding

# 90秒待機
sleep 90

# 再確認
docker logs ledgerleap_embedding --tail 20
```

---

### Q4: 切り替え後に古いモデルが動いている

**原因:** コンテナイメージが完全に削除されていない

**解決策:**
```bash
# 完全リセット
./vendor/bin/sail down
docker rmi ledgerleap-embedding -f
docker volume prune -f

# スクリプト再実行
./bin/switch-model.sh <model-key>
```

---

## 性能比較

### 切り替え前後の予想される改善

#### BGE-M3 → ruri-v3-30m

| 項目 | Before (BGE-M3) | After (ruri-v3-30m) | 改善率 |
|------|----------------|---------------------|--------|
| **処理時間/text** | 120秒 | 2秒 | **98.3%改善** |
| **テスト完了時間** | 15-20分 | 2-3分 | **85-90%短縮** |
| **メモリ使用量** | 4GB | 2GB | **50%削減** |
| **モデルサイズ** | 1.1GB | 148MB | **86.5%削減** |

#### BGE-M3 → multilingual-e5-small

| 項目 | Before (BGE-M3) | After (e5-small) | 改善率 |
|------|----------------|------------------|--------|
| **処理時間/text** | 120秒 | 2-3秒 | **97.5-98%改善** |
| **テスト完了時間** | 15-20分 | 2-3分 | **85-90%短縮** |
| **メモリ使用量** | 4GB | 1.5GB | **62.5%削減** |
| **モデルサイズ** | 1.1GB | 120MB | **89%削減** |

---

## 設定ファイルとの関係

### config/rag.php

スクリプトは`config/rag.php`の`available_models`配列と連携しています。

```php
'available_models' => [
    'ruri-v3-30m' => [
        'name' => 'ruri-nakamura/ruri-v3-30m',
        'dimension' => 768,
        'description' => 'Fast and lightweight model...',
    ],
    // ...
],
```

モデルキーがここに定義されている必要があります。

---

### .env

`RAG_MODEL`がモデルキーを保持します。

```bash
RAG_MODEL=ruri-v3-30m
```

---

### docker-compose.yml

`EMBEDDING_MODEL`がHugging Faceのモデル名を保持します。

```yaml
environment:
  - EMBEDDING_MODEL=ruri-nakamura/ruri-v3-30m
```

---

## コマンドリファレンス

```bash
# モデル一覧表示
./bin/switch-model.sh
./bin/switch-model.sh -l
./bin/switch-model.sh --list

# ヘルプ表示
./bin/switch-model.sh -h
./bin/switch-model.sh --help

# モデル切り替え
./bin/switch-model.sh <model-key>

# 例
./bin/switch-model.sh ruri-v3-30m
./bin/switch-model.sh multilingual-e5-small
./bin/switch-model.sh bge-m3
```

---

## まとめ

### スクリプトの利点

1. ✅ **簡単**: 1コマンドで切り替え完了
2. ✅ **安全**: 設定の整合性を自動保証
3. ✅ **明確**: 各ステップの進捗を表示
4. ✅ **確実**: エラーハンドリング実装

### 推奨ワークフロー

```bash
# Step 1: モデル一覧を確認
./bin/switch-model.sh --list

# Step 2: 推奨モデルに切り替え
./bin/switch-model.sh ruri-v3-30m

# Step 3: モデルロード待機（60秒）
sleep 60

# Step 4: 動作確認
docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health | jq .

# Step 5: 性能テスト
./bin/test-rag-performance.sh
```

これで、開発効率が劇的に向上します！
