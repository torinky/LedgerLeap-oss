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
| **multilingual-e5-small** | intfloat/multilingual-e5-small | 384 | ⭐ 汎用・軽量多言語（推奨） |
| **ruri-v3-30m** | ruri-nakamura/ruri-v3-30m | **256** | 日本語特化・超高速 |
| **ruri-v3-310m** | ruri-nakamura/ruri-v3-310m | **768** | 日本語特化・高品質 |
| **multilingual-e5-base** | intfloat/multilingual-e5-base | 768 | バランス型多言語 |
| **all-minilm-l6-v2** | sentence-transformers/all-MiniLM-L6-v2 | 384 | 超高速（英語中心） |
| **granite-embedding-107m** | ibm/granite-embedding-107m-multilingual | 1024 | コード検索対応 |
| **bge-m3** | BAAI/bge-m3 | 1024 | 最高品質（x86_64推奨） |

---

## 実行例

### 例1: 推奨モデル（multilingual-e5-small）に切り替え

```bash
$ ./bin/switch-model.sh multilingual-e5-small

==========================================
Switching to: multilingual-e5-small
==========================================
  Model: intfloat/multilingual-e5-small
  Dimensions: 384
  Description: Lightweight multilingual model

Continue? (y/n) y

[Step 1/6] Updating .env file...
...
```

---

### 例2: モデル一覧を表示

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
all-minilm-l6-v2         384         Ultra-fast (English-focused)
bge-m3                   1024        High-quality (slow on ARM64)
granite-embedding-107m   1024        Code search capable
multilingual-e5-base     768         Balanced multilingual
multilingual-e5-small    384         ⭐ Lightweight multilingual (Recommended)
ruri-v3-30m              256         Ultra-fast Japanese model
ruri-v3-310m             768         High-quality Japanese model

Recommendations:
  ⭐ multilingual-e5-small  - Best balance for ARM64 development
  ⭐ ruri-v3-30m           - Fastest for Japanese-specific tasks
  ⭐ ruri-v3-310m          - Best quality for Japanese-specific tasks
```

---

## スクリプトの処理内容

スクリプトは以下の6ステップを自動で実行します。

1.  **`.env`の更新:** `RAG_MODEL` を指定されたキーに更新。
2.  **`docker-compose.yml`の更新:** `EMBEDDING_MODEL` をモデルのフルネームに更新。
3.  **コンテナの停止:** `sail down embedding` を実行。
4.  **古いイメージの削除:** `docker rmi ledgerleap-embedding` を実行。
5.  **新しいコンテナのビルド:** `sail build --no-cache embedding` を実行。
6.  **コンテナの起動:** `sail up -d embedding` を実行。

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
| ruri-v3-310m | 60-80秒 |
| granite-embedding-107m | 60-90秒 |
| bge-m3 | 90-120秒 |

**ログで確認:**
```bash
docker logs -f ledgerleap_embedding
# "Successfully loaded model" が表示されるまで待つ
```

### 2. ヘルスチェック

```bash
docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health | jq .
```

### 3. 性能テスト

```bash
./bin/test-rag-performance.sh
```

---

## 推奨モデル

### 開発環境（ARM64）

**1位: multilingual-e5-small (汎用)**
```bash
./bin/switch-model.sh multilingual-e5-small
```
- **理由:** 速度、次元数(384)、多言語対応のバランスが最も良い。

**2位: ruri-v3-30m (日本語特化・速度重視)**
```bash
./bin/switch-model.sh ruri-v3-30m
```
- **理由:** 最高の日本語品質と速度。次元数(256)が低くても問題ない場合に。

**3位: ruri-v3-310m (日本語特化・品質重視)**
```bash
./bin/switch-model.sh ruri-v3-310m
```
- **理由:** 768次元による高い表現力と日本語性能。

### 本番環境（x86_64）

**推奨: bge-m3 または ruri-v3-310m**
```bash
# 最高品質の多言語なら
./bin/switch-model.sh bge-m3

# 最高品質の日本語なら
./bin/switch-model.sh ruri-v3-310m
```
- **理由:** x86_64では大規模モデルも実用的。用途に応じて最高品質のモデルを選択。

---

## トラブルシューティング

### Q1: "Container still running"エラー
**A:** 手動でコンテナを停止・削除してから再実行してください。
```bash
docker stop ledgerleap_embedding && docker rm ledgerleap_embedding
```

### Q2: ビルドが失敗する
**A:** Dockerのリソースをクリーンアップしてから再試行してください。
```bash
docker system prune -a
```

### Q3: モデルがロードされない
**A:** ログを確認し、モデル名が正しいか、Hugging Face Hubに接続できるか確認してください。
```bash
docker logs ledgerleap_embedding --tail 50
```

---

## コマンドリファレンス

```bash
# モデル一覧表示
./bin/switch-model.sh
./bin/switch-model.sh -l, --list

# ヘルプ表示
./bin/switch-model.sh -h, --help

# モデル切り替え
./bin/switch-model.sh <model-key>
```

