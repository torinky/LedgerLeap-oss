# 代替Embeddingモデル選択ガイド (2025年10月版)

**更新日:** 2025年10月18日  
**目的:** BGE-M3の代替として、特にARM64開発環境で実用的な日本語埋め込みモデルを選択する。

---

## ✨ 更新のポイント (2025年10月)

- **情報修正:** `ruri-v3-30m` の次元数を `256` に修正。超軽量・高速な日本語特化モデルとして再評価。
- **新モデル追加:** 高品質な日本語モデルとして `ruri-nakamura/ruri-v3-310m` (768次元) を新たに追加。
- **ベンチマーク:** 日本語モデル評価の標準として **JMTEB (Japanese Massive Text Embedding Benchmark)** が重要に。
- **推奨の見直し:** 開発効率と日本語品質の観点から、推奨順位を更新。

---

## 🎯 推奨モデル（優先順）

### 🥇 1位: intfloat/multilingual-e5-small

**開発環境の新たなベストバランス。軽量・高速で十分な次元数と多言語対応を両立。**

```bash
# 設定
RAG_MODEL=multilingual-e5-small
EMBEDDING_MODEL=intfloat/multilingual-e5-small
```

| 項目 | 値 |
|------|-----|
| **サイズ** | 120MB |
| **次元数** | 384 |
| **速度 (ARM64)** | 2-3秒/text |
| **速度 (x86_64)** | 0.8秒/text |
| **日本語精度** | Good ⭐⭐⭐⭐ |
| **総合評価** | ⭐⭐⭐⭐⭐ |

**メリット:**
- ✅ 軽量・高速
- ✅ 優れた多言語対応と実績
- ✅ 384次元あり、汎用性が高い

**デメリット:**
- ⚠️ 日本語特化モデルには精度で一歩劣る

**推奨用途:**
- **全てのARM64開発環境**
- 多言語対応が必須のプロジェクト
- 汎用的なプロトタイピング

---

### 🥈 2位: ruri-nakamura/ruri-v3-30m

**超軽量・超高速な日本語特化モデル。速度最優先ならこれ。**

```bash
# 設定
RAG_MODEL=ruri-v3-30m
EMBEDDING_MODEL=ruri-nakamura/ruri-v3-30m
```

| 項目 | 値 |
|------|-----|
| **サイズ** | 148MB |
| **次元数** | **256** |
| **速度 (ARM64)** | ~2秒/text (推定) |
| **速度 (x86_64)** | ~0.7秒/text (推定) |
| **日本語精度** | Excellent ⭐⭐⭐⭐⭐ (JMTEB) |
| **総合評価** | ⭐⭐⭐⭐ |

**メリット:**
- ✅ JMTEBで証明された**トップクラスの日本語性能**
- ✅ 非常に軽量かつ高速
- ✅ 開発サイクルを劇的に改善

**デメリット:**
- ⚠️ **次元数が256と低い**ため、表現力が他のモデルに劣る可能性

**推奨用途:**
- 日本語品質と速度を最優先する開発
- テキストの複雑性が高くないアプリケーション

---

### 🥉 3位: ruri-nakamura/ruri-v3-310m

**高品質な日本語埋め込みを実現する新選択肢。**

```bash
# 設定
RAG_MODEL=ruri-v3-310m
EMBEDDING_MODEL=ruri-nakamura/ruri-v3-310m
```

| 項目 | 値 |
|------|-----|
| **サイズ** | ~620MB |
| **次元数** | **768** |
| **速度 (ARM64)** | 8-10秒/text (推定) |
| **速度 (x86_64)** | 3-4秒/text (推定) |
| **日本語精度** | Excellent ⭐⭐⭐⭐⭐ (JMTEB) |
| **総合評価** | ⭐⭐⭐⭐ |

**メリット:**
- ✅ 768次元による高い表現力
- ✅ 非常に高い日本語性能
- ✅ `multilingual-e5-base` の日本語特化版として有力

**デメリット:**
- ⚠️ サイズが大きく、ARM64環境ではやや重い

**推奨用途:**
- 日本語品質を重視するテスト環境
- `multilingual-e5-base`からのステップアップ

---

### 🏅 4位: intfloat/multilingual-e5-base

**品質と速度を両立した、信頼性の高い多言語モデル。**

```bash
# 設定
RAG_MODEL=multilingual-e5-base
EMBEDDING_MODEL=intfloat/multilingual-e5-base
```

| 項目 | 値 |
|------|-----|
| **サイズ** | 280MB |
| **次元数** | 768 |
| **速度 (ARM64)** | 5-8秒/text |
| **速度 (x86_64)** | 2秒/text |
| **日本語精度** | Very Good ⭐⭐⭐⭐⭐ |
| **総合評価** | ⭐⭐⭐⭐ |

**メリット:**
- ✅ 高品質な埋め込み（768次元）
- ✅ 優れた多言語性能と実績
- ✅ ARM64でも実用的な速度

**デメリット:**
- ⚠️ 日本語特化の `ruri-v3-310m` と比較検討が必要

**推奨用途:**
- 多言語対応かつ品質を重視するテスト環境
- 中規模本番環境

---

## 📊 性能比較表

### ARM64 (Rosetta 2) 環境

| モデル | サイズ | 次元 | 速度(推定) | 日本語精度 | 推奨度 |
|--------|--------|------|------------|--------------|--------|
| **all-MiniLM-L6-v2** | 90MB | 384 | 1.5秒 | Limited | ⭐⭐⭐ |
| **multilingual-e5-small** | 120MB | 384 | 2-3秒 | Good | ⭐⭐⭐⭐⭐ |
| **ruri-v3-30m** | 148MB | **256** | ~2秒 | Excellent | ⭐⭐⭐⭐ |
| **multilingual-e5-base** | 280MB | 768 | 5-8秒 | Very Good | ⭐⭐⭐⭐ |
| **ruri-v3-310m** | ~620MB | **768** | 8-10秒 | Excellent | ⭐⭐⭐⭐ |
| **bge-m3** | 1.1GB | 1024 | 120秒 | Excellent | ⭐ |

### x86_64 ネイティブ環境

| モデル | サイズ | 次元 | 速度(推定) | 日本語精度 | 推奨度 |
|--------|--------|------|------------|--------------|--------|
| **all-MiniLM-L6-v2** | 90MB | 384 | 0.5秒 | Limited | ⭐⭐⭐ |
| **ruri-v3-30m** | 148MB | **256** | ~0.7秒 | Excellent | ⭐⭐⭐⭐ |
| **multilingual-e5-small** | 120MB | 384 | 0.8秒 | Good | ⭐⭐⭐⭐⭐ |
| **multilingual-e5-base** | 280MB | 768 | 2秒 | Very Good | ⭐⭐⭐⭐⭐ |
| **ruri-v3-310m** | ~620MB | **768** | 3-4秒 | Excellent | ⭐⭐⭐⭐ |
| **bge-m3** | 1.1GB | 1024 | 10-15秒 | Excellent | ⭐⭐⭐⭐⭐ |

---

## 🎯 シナリオ別推奨

### シナリオ1: 開発効率最優先（多言語 or 汎用）

**推奨:** `intfloat/multilingual-e5-small`

```bash
RAG_MODEL=multilingual-e5-small
```
**理由:** 速度、サイズ、次元数、多言語対応のバランスが最も良い。

---

### シナリオ2: 開発効率最優先（日本語特化）

**推奨:** `ruri-nakamura/ruri-v3-30m`

```bash
RAG_MODEL=ruri-v3-30m
```
**理由:** 最高の日本語品質と速度を両立。次元数の低さが問題にならない場合に最適。

---

### シナリオ3: 品質重視の日本語環境

**推奨:** `ruri-nakamura/ruri-v3-310m`

```bash
RAG_MODEL=ruri-v3-310m
```
**理由:** 768次元による高い表現力と、JMTEBで証明された日本語性能。

---

## 🔄 モデル切り替え手順

### Step 1: config/rag.phpに新モデル追加

```php
'available_models' => [
    // ... 既存のモデル
    
    'ruri-v3-30m' => [
        'name' => 'ruri-nakamura/ruri-v3-30m',
        'dimension' => 256,
        'description' => 'Ultra-fast and lightweight model specialized for Japanese.',
    ],
    
    'ruri-v3-310m' => [
        'name' => 'ruri-nakamura/ruri-v3-310m',
        'dimension' => 768,
        'description' => 'High-quality model specialized for Japanese.',
    ],

    'multilingual-e5-small' => [
        'name' => 'intfloat/multilingual-e5-small',
        'dimension' => 384,
        'description' => 'Lightweight multilingual model.',
    ],
    
    // ... etc
],
```

### Step 2, 3, 4, 5, 6: (変更なし)
`./bin/switch-model.sh` スクリプトを使用してください。

---

## 💡 最終推奨

### 現在の状況（開発環境 ARM64）

**即座に切り替えるべき:** `intfloat/multilingual-e5-small` または `ruri-nakamura/ruri-v3-30m`

**理由:**
1. ✅ **40-60倍高速**な開発サイクル（BGE-M3比）
2. ✅ **汎用性なら e5-small (384次元)**
3. ✅ **日本語品質と速度なら ruri-v3-30m (256次元)**
4. ✅ どちらも軽量・省メモリ

**切り替え時間:** 5-10分
**効果:** テスト時間 15-20分 → **約2-3分**

---

### 将来（本番環境 x86_64）

**推奨:** `BAAI/bge-m3`, `intfloat/multilingual-e5-large`, または `ruri-nakamura/ruri-v3-310m`

**理由:**
- x86_64環境では大規模モデルも実用的な速度で動作。
- `bge-m3`や`e5-large`は多言語で最高品質。
- `ruri-v3-310m`は日本語において最高品質を追求する場合の選択肢。

---

## 📝 まとめ

| 環境 | 推奨モデル | 理由 |
|------|-----------|------|
| **開発 (ARM64)** | **multilingual-e5-small** | **速度、次元数、多言語対応のベストバランス** |
| **開発 (ARM64, 日本語特化)** | **ruri-v3-30m** | **最高の日本語品質と速度** |
| **テスト (ARM64, 日本語品質重視)** | **ruri-v3-310m** | 高品質な日本語性能 |
| **本番 (x86_64)** | bge-m3 または ruri-v3-310m | 最高品質（用途に応じて選択） |

**次のアクション:**
```bash
# バランスの取れた e5-small に切り替え（推奨）
./bin/switch-model.sh multilingual-e5-small

# または日本語特化の ruri-v3-30m に切り替え
./bin/switch-model.sh ruri-v3-30m
```

