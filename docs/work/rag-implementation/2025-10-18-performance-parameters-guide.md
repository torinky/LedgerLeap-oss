# RAG性能パラメータ完全ガイド

**作成日:** 2025年10月18日  
**対象:** BAAI/bge-m3 および all-MiniLM-L6-v2 モデル  
**環境:** ARM64 (Rosetta 2) および x86_64

---

## 利用可能な性能パラメータ

### 1. バッチサイズ (`RAG_BATCH_SIZE`)

**説明:** 一度に処理するテキストの数

```bash
# .env
RAG_BATCH_SIZE=4
```

**推奨値:**
- BGE-M3 + ARM64: `1-2`
- BGE-M3 + x86_64: `4-8`
- all-MiniLM-L6-v2 + ARM64: `4-8`
- all-MiniLM-L6-v2 + x86_64: `8-16`

**影響:**
- ✅ **高い値**: 速度向上、メモリ使用量増加
- ⚠️ **低い値**: 安定性向上、速度低下

---

### 2. PyTorchスレッド数 (`RAG_NUM_THREADS`)

**説明:** CPUオペレーション並列化のスレッド数

```bash
# .env
RAG_NUM_THREADS=4
```

**推奨値:**
- `0`: 自動（全CPUコア使用）
- `2-4`: 中程度の並列化
- `1`: シングルスレッド（デバッグ用）

**影響:**
- ✅ **最適値**: CPUコア数の50-100%
- ⚠️ **高すぎ**: コンテキストスイッチのオーバーヘッド
- ⚠️ **低すぎ**: CPU活用不足

**M1 Mac（8コア）での推奨:**
- Performance cores: 4
- 推奨設定: `RAG_NUM_THREADS=4`

---

### 3. Inter-opスレッド数 (`RAG_NUM_INTEROP_THREADS`)

**説明:** 独立オペレーション間の並列化

```bash
# .env
RAG_NUM_INTEROP_THREADS=2
```

**推奨値:**
- `0`: 自動
- `1-2`: 軽量な並列化
- `4-8`: 積極的な並列化（x86_64のみ）

**影響:**
- ✅ **低めの値**: メモリ効率良好
- ⚠️ **高い値**: 複雑な並列化、デバッグ困難

---

### 4. Numpy変換 (`RAG_CONVERT_TO_NUMPY`)

**説明:** 結果をnumpy配列に変換するか

```bash
# .env
RAG_CONVERT_TO_NUMPY=true
```

**推奨値:**
- `true`: デフォルト（互換性重視）
- `false`: PyTorchテンソルで返す（わずかに高速）

**影響:**
- ✅ **true**: JSON変換が簡単
- ⚠️ **false**: 追加の変換処理が必要

---

### 5. デバイス (`RAG_DEVICE`)

**説明:** 推論に使用するデバイス

```bash
# .env
RAG_DEVICE=cpu
```

**利用可能な値:**
- `cpu`: CPU推論（デフォルト）
- `cuda`: NVIDIA GPU（利用可能な場合）
- `mps`: Apple Silicon GPU（実験的）

**推奨値:**
- **開発環境（M1 Mac）**: `cpu`
  - 理由: mpsは不安定、Rosetta 2環境ではCPUが安全
- **本番環境（NVIDIA GPU）**: `cuda`
  - 理由: 10-100倍高速化
- **将来（ARMネイティブ）**: `mps`
  - 理由: Apple Silicon GPU活用

**影響:**
- ✅ **cuda**: 劇的な速度向上（GPUが必要）
- ⚠️ **mps**: 不安定、テスト必須
- ✅ **cpu**: 最も安定、汎用性高い

---

### 6. タイムアウト (`EMBEDDING_SERVICE_TIMEOUT`)

**説明:** HTTPリクエストのタイムアウト（秒）

```bash
# .env
EMBEDDING_SERVICE_TIMEOUT=180
```

**推奨値:**
- BGE-M3 + ARM64: `180-300`
- BGE-M3 + x86_64: `60-120`
- all-MiniLM-L6-v2: `30-60`

**計算式:**
```
Timeout = (平均処理時間/テキスト × 最大テキスト数 × 安全係数1.5)
```

---

## 推奨設定プリセット

### プリセット1: 安定性最優先（開発初期）

```bash
# .env
RAG_BATCH_SIZE=1
RAG_NUM_THREADS=2
RAG_NUM_INTEROP_THREADS=1
RAG_CONVERT_TO_NUMPY=true
RAG_DEVICE=cpu
EMBEDDING_SERVICE_TIMEOUT=300
```

**用途:** 
- 初回セットアップ
- デバッグ
- 不安定な環境

**期待性能:**
- BGE-M3: 120秒/テキスト
- all-MiniLM-L6-v2: 3-5秒/テキスト

---

### プリセット2: バランス型（推奨）

```bash
# .env - BGE-M3 on ARM64
RAG_BATCH_SIZE=4
RAG_NUM_THREADS=4
RAG_NUM_INTEROP_THREADS=2
RAG_CONVERT_TO_NUMPY=true
RAG_DEVICE=cpu
EMBEDDING_SERVICE_TIMEOUT=180
```

**用途:**
- 通常の開発環境
- テスト環境

**期待性能:**
- BGE-M3: 30-40秒/テキスト
- all-MiniLM-L6-v2: 1-2秒/テキスト

---

### プリセット3: 性能最優先（x86_64のみ）

```bash
# .env - BGE-M3 on x86_64
RAG_BATCH_SIZE=8
RAG_NUM_THREADS=8
RAG_NUM_INTEROP_THREADS=4
RAG_CONVERT_TO_NUMPY=true
RAG_DEVICE=cpu
EMBEDDING_SERVICE_TIMEOUT=120
```

**用途:**
- 本番環境（x86_64サーバー）
- ハイスペック開発環境

**期待性能:**
- BGE-M3: 10-15秒/テキスト
- all-MiniLM-L6-v2: 0.5-1秒/テキスト

---

### プリセット4: GPU利用（NVIDIA環境）

```bash
# .env - NVIDIA GPU available
RAG_BATCH_SIZE=32
RAG_NUM_THREADS=4
RAG_NUM_INTEROP_THREADS=2
RAG_CONVERT_TO_NUMPY=true
RAG_DEVICE=cuda
EMBEDDING_SERVICE_TIMEOUT=60
```

**用途:**
- 本番環境（GPU搭載サーバー）
- 高負荷処理

**期待性能:**
- BGE-M3: 1-3秒/テキスト（10-50倍高速化）
- all-MiniLM-L6-v2: 0.1-0.3秒/テキスト

---

## パラメータ調整のワークフロー

### Step 1: ベースライン測定

```bash
# 安定性最優先設定で測定
RAG_BATCH_SIZE=1
RAG_NUM_THREADS=2

./vendor/bin/sail restart embedding
sleep 90  # モデルロード待機

./vendor/bin/sail artisan rag:benchmark --ledgers=3 --content-size=1000 --sync
```

**記録事項:**
- 平均処理時間
- メモリ使用量
- クラッシュの有無

---

### Step 2: バッチサイズ最適化

```bash
# 段階的に増加
for batch_size in 2 4 8; do
  echo "Testing batch_size=$batch_size"
  sed -i '' "s/RAG_BATCH_SIZE=.*/RAG_BATCH_SIZE=$batch_size/" .env
  ./vendor/bin/sail restart embedding
  sleep 90
  ./vendor/bin/sail artisan rag:benchmark --ledgers=3 --content-size=1000 --sync
done
```

**評価基準:**
- 処理時間が30%以上改善
- クラッシュなし
- メモリ使用量が制限内（< 8GB）

---

### Step 3: スレッド数最適化

```bash
# 最適なバッチサイズで、スレッド数を調整
RAG_BATCH_SIZE=4  # Step 2で決定した最適値

for threads in 2 4 8; do
  echo "Testing num_threads=$threads"
  sed -i '' "s/RAG_NUM_THREADS=.*/RAG_NUM_THREADS=$threads/" .env
  ./vendor/bin/sail restart embedding
  sleep 90
  ./vendor/bin/sail artisan rag:benchmark --ledgers=3 --content-size=1000 --sync
done
```

---

### Step 4: 実運用テスト

```bash
# 最適化した設定で大規模テスト
./vendor/bin/sail artisan rag:benchmark --ledgers=10 --content-size=2000 --sync
```

**合格基準:**
- 全10件処理完了
- 平均処理時間がベースラインより50%以上改善
- コンテナクラッシュなし

---

## トラブルシューティング

### 症状1: "Operation timed out"

**原因:** タイムアウトが短すぎる

**解決策:**
```bash
# タイムアウトを延長
EMBEDDING_SERVICE_TIMEOUT=300

# またはバッチサイズを増やす
RAG_BATCH_SIZE=8
```

---

### 症状2: Container exit code 139

**原因:** メモリ不足またはSegmentation Fault

**解決策:**
```bash
# パラメータを保守的に
RAG_BATCH_SIZE=1
RAG_NUM_THREADS=2
RAG_NUM_INTEROP_THREADS=1
```

---

### 症状3: CPU使用率が低い（< 50%）

**原因:** スレッド数が少なすぎる

**解決策:**
```bash
# スレッド数を増やす
RAG_NUM_THREADS=8
RAG_NUM_INTEROP_THREADS=4
```

---

### 症状4: CPU使用率が高い（> 200%）が遅い

**原因:** スレッド数が多すぎてオーバーヘッド

**解決策:**
```bash
# スレッド数を減らす
RAG_NUM_THREADS=4
RAG_NUM_INTEROP_THREADS=2
```

---

## 性能比較マトリックス

### BGE-M3 on ARM64 (Rosetta 2)

| 設定 | 平均処理時間 | スループット | 安定性 | メモリ |
|------|-------------|-------------|--------|--------|
| **Preset 1 (安定)** | 120秒 | 0.5件/分 | ⭐⭐⭐ | 2.9GB |
| **Preset 2 (バランス)** | 40秒 | 1.5件/分 | ⭐⭐ | 4.0GB |
| **カスタム (batch=8)** | 25秒 | 2.4件/分 | ⭐ | 5.5GB |

### all-MiniLM-L6-v2 on ARM64 (Rosetta 2)

| 設定 | 平均処理時間 | スループット | 安定性 | メモリ |
|------|-------------|-------------|--------|--------|
| **Preset 1** | 3秒 | 20件/分 | ⭐⭐⭐ | 1.5GB |
| **Preset 2** | 1.5秒 | 40件/分 | ⭐⭐⭐ | 2.0GB |
| **Preset 3** | 0.8秒 | 75件/分 | ⭐⭐ | 2.5GB |

---

## 設定変更チェックリスト

パラメータ変更後、以下を確認：

- [ ] `.env` ファイルを編集
- [ ] `./vendor/bin/sail restart embedding` を実行
- [ ] モデルロード完了を確認（`docker logs ledgerleap_embedding`）
- [ ] ヘルスチェック成功を確認（`curl http://embedding:8000/health`）
- [ ] ベンチマーク実行で性能改善を確認
- [ ] 1週間運用して安定性を確認
- [ ] ドキュメントに最終設定を記録

---

## まとめ

| パラメータ | デフォルト | BGE-M3推奨 | all-MiniLM推奨 |
|-----------|----------|-----------|---------------|
| `RAG_BATCH_SIZE` | 1 | 2-4 | 4-8 |
| `RAG_NUM_THREADS` | 0 | 4 | 4-8 |
| `RAG_NUM_INTEROP_THREADS` | 0 | 2 | 2-4 |
| `RAG_CONVERT_TO_NUMPY` | true | true | true |
| `RAG_DEVICE` | cpu | cpu | cpu |
| `EMBEDDING_SERVICE_TIMEOUT` | 60 | 180 | 60 |

**段階的な最適化が鍵です。いきなり aggressive な設定にせず、ベースラインから徐々に改善してください。**
