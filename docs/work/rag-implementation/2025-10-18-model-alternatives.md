# 代替Embeddingモデル選択ガイド (2025年10月版)

**更新日:** 2025年10月18日  
**目的:** BGE-M3の代替として、特にARM64開発環境で実用的な日本語埋め込みモデルを選択する。

---

## ✨ 更新のポイント (2025年10月)

- **新星登場:** `cl-nagoya/ruri-v3-30m` が開発環境の**新たな最有力候補**に。軽量(150MB)・高速でありながら、768次元と高い日本語性能(JMTEBベンチマーク)を両立。
- **ベンチマーク:** 日本語モデル評価の標準として **JMTEB (Japanese Massive Text Embedding Benchmark)** が重要に。
- **推奨の見直し:** 開発効率と日本語品質の観点から、推奨順位を大幅に更新。
- **特殊用途モデル:** `ibm/granite-embedding-107m-multilingual` をコード検索用途として追加。

---

## 🎯 推奨モデル（優先順）

### 🥇 1位: cl-nagoya/ruri-v3-30m

**開発環境のニュースタンダード。速度・サイズ・日本語品質の全てをハイレベルで両立。**

```bash
# 設定
RAG_MODEL=ruri-v3-30m
EMBEDDING_MODEL=cl-nagoya/ruri-v3-30m
```

| 項目 | 値 |
|------|-----|
| **サイズ** | 148MB |
| **次元数** | 768 |
| **速度 (ARM64)** | ~2秒/text (推定) |
| **速度 (x86_64)** | ~0.7秒/text (推定) |
| **日本語精度** | Excellent ⭐⭐⭐⭐⭐ (JMTEB) |
| **総合評価** | ⭐⭐⭐⭐⭐ |

**メリット:**
- ✅ 非常に軽量かつ高速
- ✅ **768次元**と、軽量モデルとしては高い次元数
- ✅ JMTEBで証明された**トップクラスの日本語性能**
- ✅ 開発サイクルを劇的に改善

**デメリット:**
- ⚠️ 多言語性能はe5シリーズに劣る可能性

**推奨用途:**
- **全てのARM64開発環境**
- 日本語中心のプロトタイピング
- 高速なテスト環境

---

### 🥈 2位: intfloat/multilingual-e5-small

**実績豊富な多言語モデル。多言語対応が必須な場合に最適。**

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
| **総合評価** | ⭐⭐⭐⭐ |

**メリット:**
- ✅ 軽量・高速
- ✅ 優れた多言語対応
- ✅ 非常に安定しており、実績豊富

**デメリット:**
- ⚠️ 次元数が少ない（384）
- ⚠️ 日本語特化モデルに精度で劣る

**推奨用途:**
- 多言語対応が必須の開発・テスト環境
- 汎用的なプロトタイピング

---

### 🥉 3位: intfloat/multilingual-e5-base

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
- ✅ 優れた多言語性能
- ✅ ARM64でも実用的な速度

**デメリット:**
- ⚠️ ruri-v3-30mやe5-smallよりは重い

**推奨用途:**
- 品質を重視するテスト環境
- 中規模本番環境

---

### 🏅 4位: ibm/granite-embedding-107m-multilingual

**コード検索も可能なユニークな多言語モデル。**

```bash
# 設定
RAG_MODEL=granite-embedding-107m
EMBEDDING_MODEL=ibm/granite-embedding-107m-multilingual
```

| 項目 | 値 |
|------|-----|
| **サイズ** | 428MB |
| **次元数** | 1024 |
| **速度 (ARM64)** | 10-15秒/text (推定) |
| **速度 (x86_64)** | 3-5秒/text (推定) |
| **日本語精度** | Good ⭐⭐⭐⭐ |
| **総合評価** | ⭐⭐⭐⭐ (特定用途) |

**メリット:**
- ✅ **コード検索に対応**
- ✅ 1024次元
- ✅ 多言語対応

**デメリット:**
- ⚠️ サイズの割に速度が出ない可能性
- ⚠️ テキスト検索品質は他の特化モデルに劣る場合がある

**推奨用途:**
- ソースコードを検索対象に含むRAGシステム

---

## 📊 性能比較表

### ARM64 (Rosetta 2) 環境

| モデル | サイズ | 次元 | 速度(推定) | 日本語精度 | 推奨度 |
|--------|--------|------|------------|--------------|--------|
| **ruri-v3-30m** | 148MB | 768 | ~2秒 | Excellent | ⭐⭐⭐⭐⭐ |
| **multilingual-e5-small** | 120MB | 384 | 2-3秒 | Good | ⭐⭐⭐⭐ |
| **all-MiniLM-L6-v2** | 90MB | 384 | 1.5秒 | Limited | ⭐⭐⭐ |
| **multilingual-e5-base** | 280MB | 768 | 5-8秒 | Very Good | ⭐⭐⭐⭐ |
| **granite-embedding-107m**| 428MB | 1024 | 10-15秒 | Good | ⭐⭐⭐⭐ |
| **bge-m3** | 1.1GB | 1024 | 120秒 | Excellent | ⭐ |

### x86_64 ネイティブ環境

| モデル | サイズ | 次元 | 速度(推定) | 日本語精度 | 推奨度 |
|--------|--------|------|------------|--------------|--------|
| **all-MiniLM-L6-v2** | 90MB | 384 | 0.5秒 | Limited | ⭐⭐⭐ |
| **ruri-v3-30m** | 148MB | 768 | ~0.7秒 | Excellent | ⭐⭐⭐⭐⭐ |
| **multilingual-e5-small** | 120MB | 384 | 0.8秒 | Good | ⭐⭐⭐⭐ |
| **multilingual-e5-base** | 280MB | 768 | 2秒 | Very Good | ⭐⭐⭐⭐⭐ |
| **granite-embedding-107m**| 428MB | 1024 | 3-5秒 | Good | ⭐⭐⭐⭐ |
| **bge-m3** | 1.1GB | 1024 | 10-15秒 | Excellent | ⭐⭐⭐⭐⭐ |

---

## 🎯 シナリオ別推奨

### シナリオ1: 開発効率と日本語品質を最優先

**推奨:** `cl-nagoya/ruri-v3-30m`

```bash
RAG_MODEL=ruri-v3-30m
RAG_BATCH_SIZE=8
RAG_NUM_THREADS=4
EMBEDDING_SERVICE_TIMEOUT=60
```
**理由:** テストが2分程度で完了し、最高の日本語品質と開発効率を両立。

---

### シナリオ2: 多言語対応が必須

**推奨:** `intfloat/multilingual-e5-small`

```bash
RAG_MODEL=multilingual-e5-small
RAG_BATCH_SIZE=8
RAG_NUM_THREADS=4
EMBEDDING_SERVICE_TIMEOUT=60
```
**理由:** 多くの言語を扱う必要があり、かつ開発効率を重視する場合の鉄板構成。

---

### シナリオ3: コード検索が必要

**推奨:** `ibm/granite-embedding-107m-multilingual`

```bash
RAG_MODEL=granite-embedding-107m
RAG_BATCH_SIZE=2
RAG_NUM_THREADS=4
EMBEDDING_SERVICE_TIMEOUT=180
```
**理由:** テキストと合わせてソースコードも検索対象とする場合に唯一の選択肢。

---

## 🔄 モデル切り替え手順

### Step 1: config/rag.phpに新モデル追加

```php
'available_models' => [
    // ... 既存のモデル
    
    'ruri-v3-30m' => [
        'name' => 'cl-nagoya/ruri-v3-30m',
        'dimension' => 768,
        'description' => 'Fast and lightweight model with excellent Japanese performance (recommended for dev).',
    ],

    'multilingual-e5-small' => [
        'name' => 'intfloat/multilingual-e5-small',
        'dimension' => 384,
        'description' => 'Lightweight multilingual model.',
    ],
    
    'multilingual-e5-base' => [
        'name' => 'intfloat/multilingual-e5-base',
        'dimension' => 768,
        'description' => 'Balanced multilingual model with high quality.',
    ],

    'granite-embedding-107m' => [
        'name' => 'ibm/granite-embedding-107m-multilingual',
        'dimension' => 1024,
        'description' => 'Unique multilingual model that also supports code search.',
    ],
],
```

### Step 2: .envを変更

```bash
# 例: ruri-v3-30mに切り替え
sed -i '' 's/RAG_MODEL=.*/RAG_MODEL=ruri-v3-30m/' .env
```

### Step 3: docker-compose.ymlを変更

```bash
# 例: ruri-v3-30mに切り替え
sed -i '' 's|EMBEDDING_MODEL=.*|EMBEDDING_MODEL=cl-nagoya/ruri-v3-30m|' docker-compose.yml
```

### Step 4, 5, 6: (変更なし)

---

## 💡 最終推奨

### 現在の状況（開発環境 ARM64）

**即座に切り替えるべき:** `cl-nagoya/ruri-v3-30m`

**理由:**
1. ✅ **40-60倍高速**な開発サイクル（BGE-M3比）
2. ✅ **高い日本語品質**（JMTEBベンチマーク）
3. ✅ **768次元**による表現力
4. ✅ 軽量・省メモリ

**切り替え時間:** 5-10分
**効果:** テスト時間 15-20分 → **約2分**

---

### 将来（本番環境 x86_64）

**推奨:** `BAAI/bge-m3`, `intfloat/multilingual-e5-large`, または `sbintuitions/Sarashina-Embedding-v2-1B`

**理由:**
- x86_64環境では大規模モデルも実用的な速度で動作。
- `bge-m3`や`e5-large`は多言語で最高品質。
- `Sarashina-Embedding-v2-1B`は日本語において最高品質を追求する場合の選択肢。

---

## 📝 まとめ

| 環境 | 推奨モデル | 理由 |
|------|-----------|------|
| **開発 (ARM64)** | **ruri-v3-30m** | **速度、サイズ、日本語品質の最高バランス** |
| **テスト (ARM64)** | multilingual-e5-base | 品質を重視しつつ、多言語対応が必要な場合 |
| **本番 (x86_64)** | bge-m3 または Sarashina-v2 | 最高品質（用途に応じて選択） |

**次のアクション:**
```bash
# ruri-v3-30mに切り替え（強く推奨）
./bin/switch-model.sh ruri-v3-30m
```
