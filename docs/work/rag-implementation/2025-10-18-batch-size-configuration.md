# バッチサイズ設定ガイド

**作成日:** 2025年10月18日  
**目的:** Embedding処理のバッチサイズを環境変数で調整し、パフォーマンスと安定性のバランスを最適化する

---

## 概要

BGE-M3のような大きなモデルでは、バッチサイズが性能に大きく影響します。バッチサイズを環境変数で制御できるようにしました。

---

## 設定方法

### 1. 環境変数の設定

`.env` ファイルに以下を追加または変更：

```bash
# バッチサイズ設定（推奨値はモデルと環境により異なる）
RAG_BATCH_SIZE=4
```

### 2. コンテナの再起動

設定変更後、embeddingコンテナを再起動：

```bash
./vendor/bin/sail restart embedding

# または
docker-compose restart embedding
```

---

## 推奨バッチサイズ

### モデル別

| モデル | 環境 | 推奨バッチサイズ | 理由 |
|--------|------|------------------|------|
| **all-MiniLM-L6-v2** | ARM64 (Rosetta 2) | 4-8 | 軽量モデル、高速処理可能 |
| **all-MiniLM-L6-v2** | x86_64 | 8-16 | ネイティブ環境で高速 |
| **BGE-M3** | ARM64 (Rosetta 2) | 1-2 | 大きいモデル、安定性優先 |
| **BGE-M3** | x86_64 | 4-8 | ネイティブ環境で高速化可能 |
| **multilingual-e5-base** | ARM64 (Rosetta 2) | 2-4 | 中程度のモデル |
| **multilingual-e5-base** | x86_64 | 8-16 | ネイティブ環境で高速 |

### 環境別の考慮事項

#### ARM64 + Rosetta 2
- **バッチサイズ小さめ推奨**: 1-2
- **理由**: エミュレーション環境で不安定になりやすい
- **トレードオフ**: 安定性 > 速度

#### x86_64 ネイティブ
- **バッチサイズ大きめ可能**: 4-16
- **理由**: ネイティブ実行で安定
- **トレードオフ**: 速度 > メモリ使用量

---

## パフォーマンス比較

### BGE-M3での実測例（予測）

| バッチサイズ | 処理時間/テキスト | スループット | 安定性 | メモリ使用量 |
|-------------|------------------|--------------|--------|--------------|
| **1** | 120秒 | 0.5件/分 | ✅✅✅ 非常に安定 | 2.9GB |
| **2** | 70秒 | 0.9件/分 | ✅✅ 安定 | 3.5GB |
| **4** | 40秒 | 1.5件/分 | ✅ おおむね安定 | 4.5GB |
| **8** | 25秒 | 2.4件/分 | ⚠️ 不安定の可能性 | 6.0GB |

*注: ARM64 + Rosetta 2 環境での予測値*

---

## トラブルシューティング

### 問題1: Container crashes (Exit 139)

**症状:**
```
Container ledgerleap_embedding exited with code 139
```

**原因:** バッチサイズが大きすぎてメモリ不足

**解決策:**
```bash
# .env を編集してバッチサイズを減らす
RAG_BATCH_SIZE=1

# コンテナ再起動
./vendor/bin/sail restart embedding
```

---

### 問題2: Timeout errors

**症状:**
```
cURL error 28: Operation timed out after 60003 milliseconds
```

**原因:** バッチサイズが小さすぎて処理に時間がかかる

**解決策1: バッチサイズを増やす**
```bash
# .env
RAG_BATCH_SIZE=4
```

**解決策2: タイムアウトを延長**
```bash
# .env
EMBEDDING_SERVICE_TIMEOUT=300  # 5分
```

---

### 問題3: 処理が遅い

**症状:** ベンチマークで平均処理時間が長い

**診断:**
```bash
# 現在の設定を確認
./vendor/bin/sail artisan tinker --execute='
echo "Batch Size: " . config("rag.performance.batch_size") . PHP_EOL;
echo "Timeout: " . config("rag.embedding_service.timeout") . " seconds" . PHP_EOL;
'
```

**解決策: バッチサイズを段階的に増やす**
```bash
# Step 1: 現在 1 → 2 に変更
RAG_BATCH_SIZE=2
./vendor/bin/sail restart embedding

# Step 2: 動作確認（1週間運用）
./vendor/bin/sail artisan rag:benchmark --ledgers=5

# Step 3: 問題なければ 4 に増やす
RAG_BATCH_SIZE=4
```

---

## ベンチマーク手順

バッチサイズ変更後、必ずベンチマークを実行して効果を確認：

```bash
# 小規模テスト（3件、1000文字）
./vendor/bin/sail artisan rag:benchmark --ledgers=3 --content-size=1000 --sync

# 標準テスト（5件、2000文字）
./vendor/bin/sail artisan rag:benchmark --ledgers=5 --content-size=2000 --sync

# 大規模テスト（10件、2000文字）
./vendor/bin/sail artisan rag:benchmark --ledgers=10 --content-size=2000 --sync
```

**評価基準:**
- ✅ 平均処理時間が短縮されている
- ✅ すべての台帳が正常に処理完了
- ✅ コンテナがクラッシュしない
- ✅ メモリ使用量が8GB以下

---

## 最適化の流れ

### Phase 1: 安定性確保（現在）

```bash
# 設定
RAG_BATCH_SIZE=1
EMBEDDING_SERVICE_TIMEOUT=180

# 目標
- クラッシュなし
- 処理完了率 100%
```

### Phase 2: パフォーマンス改善

```bash
# 設定
RAG_BATCH_SIZE=2-4
EMBEDDING_SERVICE_TIMEOUT=120

# 目標
- 処理時間 30-50% 削減
- 安定性維持
```

### Phase 3: 最適化

```bash
# 設定（x86_64環境の場合）
RAG_BATCH_SIZE=8-16
EMBEDDING_SERVICE_TIMEOUT=60

# 目標
- 最大スループット
- 本番環境での運用最適化
```

---

## コード内での参照方法

### PHP側（Laravel）

```php
// config/rag.php で定義済み
$batchSize = config('rag.performance.batch_size'); // デフォルト: 1

// 現在は使用していないが、将来的にPHP側でのバッチ制御に利用可能
```

### Python側（app.py）

```python
# 環境変数から読み取り
batch_size = int(os.getenv('EMBEDDING_BATCH_SIZE', '1'))

# model.encode() に渡す
embeddings = model.encode(
    request.texts,
    batch_size=batch_size
)
```

---

## 関連ファイル

- `docker/embedding/app.py` - バッチサイズを環境変数から読み取り
- `config/rag.php` - Laravel側の設定定義
- `docker-compose.yml` - コンテナへの環境変数渡し
- `.env` - 実際の設定値

---

## まとめ

| 項目 | 設定 |
|------|------|
| **環境変数名** | `RAG_BATCH_SIZE` |
| **デフォルト値** | 1 |
| **推奨値 (BGE-M3 + ARM64)** | 1-2 |
| **推奨値 (BGE-M3 + x86_64)** | 4-8 |
| **推奨値 (all-MiniLM-L6-v2)** | 4-16 |
| **変更後の対応** | コンテナ再起動 + ベンチマーク実行 |

---

**バッチサイズの調整により、BGE-M3の処理性能を大幅に改善できる可能性があります。段階的にテストしながら最適値を見つけてください。**
