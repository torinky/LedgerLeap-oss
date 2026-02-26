# RAG導入 Phase1 WBS-1 追加最適化オプション

**作成日:** 2025年10月18日  
**現在のステータス:** ✅ 完了・安定動作中  
**目的:** 現在の実装に対する追加最適化の可能性を評価

---

## 現在の実装状況

### ✅ 既に実施済みの対策

| 提案 | 項目 | ステータス | 効果 |
|-----|------|----------|------|
| 提案4 | Rosetta 2エミュレーション | ✅ 実施済み | **安定性確保（最重要）** |
| 提案1-5 | バッチサイズ=1 | ✅ 実施済み | **クラッシュ防止** |
| - | 軽量モデル採用 | ✅ 実施済み | メモリ使用量削減 |

### 検証済みの性能

```
平均処理時間: 1.64秒/件
スループット: 37件/分（同期処理）
安定性: クラッシュなし ✅
```

---

## 追加最適化オプションの評価

### オプション1: バッチサイズの段階的引き上げ

#### 目的
現在の`batch_size=1`を段階的に増やし、安定性とパフォーマンスのバランスを最適化

#### 実施方法
```python
# docker/embedding/app.py
embeddings = model.encode(
    request.texts,
    normalize_embeddings=request.normalize,
    show_progress_bar=False,
    batch_size=4  # 1 → 4 → 8 と段階的にテスト
)
```

#### 期待される効果
- **batch_size=4**: 20-30%高速化（推定1.1-1.3秒/件）
- **batch_size=8**: 30-40%高速化（推定0.95-1.15秒/件）

#### リスク
- Rosetta 2環境でのメモリ消費増加
- 安定性低下の可能性（要検証）

#### 推奨度: ⭐⭐⭐⭐
- WBS 2-5期間中に段階的にテスト推奨
- まずbatch_size=4で1週間運用し、問題なければ8に引き上げ

---

### オプション2: ARMネイティブ版への切り替え

#### 目的
Rosetta 2のオーバーヘッドを排除し、M1チップのネイティブ性能を活用

#### 実施方法

**Step 1: platform設定を削除**
```yaml
# docker-compose.yml
embedding:
  # platform: linux/amd64  # ← 削除してARMネイティブに
  build:
    context: ./docker/embedding
```

**Step 2: PyTorchバージョンの調整**
```txt
# docker/embedding/requirements.txt
torch==2.2.0  # ARM64最適化版
sentence-transformers==2.3.1
transformers==4.37.0
```

**Step 3: MPS（Metal Performance Shaders）の活用**
```python
# app.py
import torch

# M1チップのGPUを利用
device = 'mps' if torch.backends.mps.is_available() else 'cpu'
model = SentenceTransformer(..., device=device)
```

#### 期待される効果
- Rosetta 2オーバーヘッド削除: 20-30%高速化
- MPS GPU加速: さらに50-100%高速化の可能性
- 理論上: **0.5-0.8秒/件** まで改善可能

#### リスク
- **高リスク**: 過去にSegmentation Faultが発生した構成
- PyTorchバージョンによっては不安定
- 広範な検証が必要

#### 推奨度: ⭐⭐
- **Phase 2以降で検討**
- 別ブランチで実験的に実施
- 本番環境はx86_64サーバーの可能性が高いため優先度低

---

### オプション3: Ollama統合

#### 目的
M1 Macでの最高の安定性を確保

#### 実施方法

**Step 1: Ollamaインストール**
```bash
# ホストOSで実行
brew install ollama
ollama serve &
ollama pull nomic-embed-text
```

**Step 2: app.pyの全面書き換え**
```python
import requests

OLLAMA_URL = "http://host.docker.internal:11434"

@app.post("/embed")
async def embed_texts(request: EmbedRequest):
    embeddings = []
    for text in request.texts:
        response = requests.post(
            f"{OLLAMA_URL}/api/embeddings",
            json={"model": "nomic-embed-text", "prompt": text}
        )
        embeddings.append(response.json()["embedding"])
    
    return {
        "embeddings": embeddings,
        "dimension": 768,  # nomic-embed-text
        "model": "nomic-embed-text"
    }
```

**Step 3: マイグレーション調整**
```bash
# embeddingカラムサイズを768次元用に変更
php artisan migrate:fresh
```

#### 期待される効果
- **最高の安定性**: M1 Macでの実績豊富
- メモリ消費削減
- Pythonコンテナの軽量化

#### デメリット
- 環境依存性の増加（全開発者がOllamaインストール必要）
- 768次元 → マイグレーション再実行必要
- nomic-embed-textは英語特化（日本語精度低下の可能性）

#### 推奨度: ⭐⭐⭐
- **現状で問題がある場合のみ**
- 開発環境の複雑化とのトレードオフ
- 本番環境への影響を考慮

---

### オプション4: PHP側でのバッチ制御

#### 目的
Python側のbatch_sizeは1のまま、PHP側で複数リクエストを並列処理

#### 実施方法

```php
// app/Services/EmbeddingService.php

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

public function embedInParallel(array $texts, int $concurrency = 3): array
{
    $batches = array_chunk($texts, 1);  // 1件ずつ
    $allEmbeddings = [];
    
    foreach (array_chunk($batches, $concurrency) as $group) {
        $responses = Http::pool(function (Pool $pool) use ($group) {
            $requests = [];
            foreach ($group as $batch) {
                $requests[] = $pool->timeout($this->timeout)
                    ->post("{$this->embeddingServiceUrl}/embed", [
                        'texts' => $batch,
                        'normalize' => true,
                    ]);
            }
            return $requests;
        });
        
        foreach ($responses as $response) {
            $data = $response->json();
            $allEmbeddings = array_merge($allEmbeddings, $data['embeddings']);
        }
    }
    
    return $allEmbeddings;
}
```

#### 期待される効果
- Python側の安定性維持（batch_size=1のまま）
- 並列処理によるスループット向上（concurrency=3で約3倍）
- 理論上: **0.5-0.6秒/件** （3並列の場合）

#### デメリット
- HTTPオーバーヘッド増加
- embeddingコンテナへの同時接続数増加

#### 推奨度: ⭐⭐⭐⭐
- **安全性を保ちつつ高速化**
- 実装が比較的簡単
- WBS 2-5期間中に実装推奨

---

## 推奨実施プラン

### Phase 1: 現状維持（WBS 2完了まで）

**理由:**
- 現在の構成で安定動作している
- WBS 2（検索ロジック実装）に集中すべき

### Phase 2: 段階的最適化（WBS 3-5期間中）

**Week 1-2: バッチサイズ引き上げテスト**
1. batch_size=4に変更
2. 1週間運用して安定性確認
3. 問題なければbatch_size=8にチャレンジ

**Week 3-4: PHP並列処理実装**
1. `EmbeddingService::embedInParallel()` 実装
2. concurrency=2でテスト
3. 安定なら concurrency=3-4に引き上げ

**期待される最終性能:**
- batch_size=8 + concurrency=3
- 理論値: **0.3-0.4秒/件**
- 現状比: **4-5倍高速化**

### Phase 3: ARMネイティブ実験（Phase 2以降）

**条件:**
- 別ブランチで実験
- 本番環境がARM64サーバーの場合のみ優先度上昇

---

## 現時点での結論

### ✅ 現在の実装は十分

現在の性能（1.64秒/件）は以下の理由で許容範囲：

1. **非同期処理**: キューワーカーがバックグラウンドで処理
2. **頻度**: 台帳作成・更新時のみ（リアルタイム性不要）
3. **安定性**: クラッシュなしが最優先

### 📊 最適化の優先順位

| 順位 | 施策 | タイミング | 期待効果 | リスク |
|-----|------|----------|---------|--------|
| 1️⃣ | バッチサイズ引き上げ | WBS 3-5 | 20-40%高速化 | 低 |
| 2️⃣ | PHP並列処理 | WBS 3-5 | 2-3倍高速化 | 低 |
| 3️⃣ | Ollama統合 | 問題発生時 | 安定性向上 | 中 |
| 4️⃣ | ARMネイティブ | Phase 2 | 2-4倍高速化 | 高 |

### 🎯 推奨アクション

**今すぐ:**
- なし（現状維持）

**WBS 2完了後:**
- batch_size=4にチャレンジ

**WBS 3-5期間中:**
- PHP並列処理実装

**Phase 2以降:**
- ARMネイティブ実験（optional）

---

## 参考: 各構成の性能比較表

| 構成 | 処理時間/件 | スループット/分 | 安定性 | 実装難易度 |
|-----|-----------|--------------|--------|-----------|
| **現在** (Rosetta2 + batch=1) | 1.64秒 | 37件 | ✅✅✅ | - |
| batch=4 | 1.1-1.3秒 | 46-55件 | ✅✅ | ⭐ |
| batch=8 | 0.95-1.15秒 | 52-63件 | ✅ | ⭐ |
| PHP並列(×3) | 0.5-0.6秒 | 100-120件 | ✅✅ | ⭐⭐ |
| batch=8 + 並列×3 | 0.3-0.4秒 | 150-200件 | ✅ | ⭐⭐ |
| ARMネイティブ + MPS | 0.5-0.8秒 | 75-120件 | ❓ | ⭐⭐⭐⭐ |
| Ollama | 1.0-1.5秒 | 40-60件 | ✅✅✅ | ⭐⭐⭐ |

**凡例:**
- ✅✅✅ = 非常に安定
- ✅✅ = 安定
- ✅ = おおむね安定
- ❓ = 未検証（リスクあり）

---

**結論:** 現在の構成で WBS 2-5 に進み、必要に応じて段階的に最適化する方針を推奨します。
