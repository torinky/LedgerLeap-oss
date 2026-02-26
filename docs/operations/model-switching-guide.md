# RAG Embedding Model Switching Guide

**最終更新:** 2025年10月18日

---

## 概要

LedgerLeapのRAG機能では、複数の埋め込みモデルを切り替えることができます。このドキュメントでは、モデル切り替えの手順と注意点を説明します。

## モデル切り替えスクリプト

### 基本的な使い方

```bash
# 利用可能なモデル一覧を表示
./bin/switch-model.sh

# モデルを切り替え
./bin/switch-model.sh ruri-v3-310m
```

### スクリプトが実行する処理

1. **.envファイルの更新**
   - `RAG_MODEL`環境変数を更新
   - Laravelの`config/rag.php`がこの値を参照

2. **Laravel設定の検証**
   - `config/rag.php`の`available_models`に該当モデルが存在するか確認
   - ベクトル次元数を表示
   - データベーススキーマ（MEDIUMBLOB）が対応可能か確認

3. **docker-compose.ymlの更新**
   - `EMBEDDING_MODEL`環境変数を更新
   - `platform` (linux/arm64 または linux/amd64) を自動設定

4. **Dockerコンテナの再構築**
   - 既存の`embedding`コンテナを停止
   - 古いイメージを削除
   - 新しいモデルでイメージをビルド
   - コンテナを起動

5. **設定サマリーの表示**
   - 切り替え後の設定内容を表示
   - 次の手順を案内

---

## モデル切り替え時の設定更新

### 自動更新される設定

| ファイル | 項目 | 更新方法 |
|---------|------|----------|
| `.env` | `RAG_MODEL` | sedで直接書き換え |
| `docker-compose.yml` | `EMBEDDING_MODEL` | sedで直接書き換え |
| `docker-compose.yml` | `platform` | sedで直接書き換え |

### 自動対応済みの設定

| 設定 | 説明 | 対応方法 |
|------|------|----------|
| **ベクトル次元** | モデルごとに異なる（256〜1024次元） | `config/rag.php`で定義済み |
| **データベーススキーマ** | 任意の次元に対応 | `MEDIUMBLOB`カラムで最大16MB対応 |
| **Python依存関係** | モデル固有のライブラリ | 現在は共通の`requirements.txt`を使用 |

### 手動対応が必要な場合

**既存のチャンクデータ:**
- モデルを切り替えると、新旧のベクトル次元が異なる可能性がある
- 既存チャンクは古いモデルの次元でエンコードされている
- **解決策:** 再チャンク化コマンドを実行

```bash
# オプション1: 全台帳を再チャンク化（既存チャンクを強制削除）
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force

# オプション2: チャンクが存在しない台帳のみ処理
./vendor/bin/sail artisan rag:chunk-existing-ledgers --only-missing

# オプション3: 一部の台帳のみ処理（段階的実行）
./vendor/bin/sail artisan rag:chunk-existing-ledgers --limit=100 --offset=0
```

---

## モデルごとの特性

### 利用可能なモデル

| モデルキー | 次元 | サイズ | 推奨環境 | 特徴 |
|-----------|------|--------|----------|------|
| `ruri-v3-30m` | 256 | 30M | ARM64 | 超高速、日本語特化、省メモリ |
| `ruri-v3-310m` | 768 | 310M | ARM64 | 高速、日本語特化、バランス型 |
| `multilingual-e5-small` | 384 | 118M | Any | 軽量、多言語対応 |
| `all-minilm-l6-v2` | 384 | 80M | Any | 超高速、英語特化 |
| `multilingual-e5-base` | 768 | 278M | Any | バランス型、多言語 |
| `granite-embedding-107m` | 1024 | 107M | Any | コード検索対応 |
| `bge-m3` | 1024 | 1.2GB | x86_64 | 最高品質、ARM64では低速 |

### モデル選択の指針

**開発環境（ARM64 Mac）:**
- ✅ **推奨:** `ruri-v3-30m` - 高速で省メモリ
- ✅ **代替:** `ruri-v3-310m` - より高精度

**本番環境（x86_64 Linux）:**
- ✅ **推奨:** `multilingual-e5-base` - バランス型
- ✅ **高品質:** `bge-m3` - 最高精度（メモリ4GB必要）

**日本語メイン:**
- ✅ `ruri-v3-*` シリーズ（日本語に最適化）

**多言語対応:**
- ✅ `multilingual-e5-*` シリーズ
- ✅ `bge-m3`

**コード検索:**
- ✅ `granite-embedding-107m`

---

## モデル切り替えワークフロー

### 標準的な手順

```bash
# 1. 現在の設定を確認
./bin/switch-model.sh --list

# 2. モデルを切り替え
./bin/switch-model.sh ruri-v3-310m

# 3. モデルのロード待機（30-90秒）
docker logs -f ledgerleap_embedding

# 4. ヘルスチェック
docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health | jq

# 5. パフォーマンステスト（任意）
./bin/test-rag-performance.sh

# 6. 既存データの再チャンク化（必要に応じて）
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force
```

### 本番環境での切り替え手順

```bash
# 1. メンテナンスモード開始
./vendor/bin/sail artisan down

# 2. モデル切り替え実行
./bin/switch-model.sh multilingual-e5-base

# 3. 既存チャンクを削除（次元が異なる場合）
./vendor/bin/sail artisan db:table ledger_chunks --truncate

# 4. バックグラウンドで再チャンク化開始
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force &

# 5. メンテナンスモード終了（新規データは自動チャンク化される）
./vendor/bin/sail artisan up

# 6. キューワーカーを監視
./vendor/bin/sail artisan queue:work --verbose
```

---

## トラブルシューティング

### モデルが読み込まれない

**症状:**
```
docker logs ledgerleap_embedding
# "Model not found" エラー
```

**原因:**
- モデル名が間違っている
- HuggingFace Hubへの接続に失敗

**解決策:**
```bash
# 1. docker-compose.ymlのEMBEDDING_MODEL値を確認
grep EMBEDDING_MODEL docker-compose.yml

# 2. コンテナ内で手動ダウンロード試行
docker exec -it ledgerleap_embedding bash
python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('cl-nagoya/ruri-v3-310m')"
```

### ベクトル次元の不一致

**症状:**
```
Embedding dimension mismatch. Expected 768, got 384
```

**原因:**
- 既存チャンクが古いモデルでエンコードされている
- `.env`と実際のモデルが不一致

**解決策:**
```bash
# 1. 現在の設定を確認
grep RAG_MODEL .env
docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health | jq .dimension

# 2. 不一致の場合、再チャンク化
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force
```

### メモリ不足エラー

**症状:**
```
docker logs ledgerleap_embedding
# "Killed" または "Out of memory"
```

**原因:**
- モデルが大きすぎる（特にbge-m3）
- Docker Desktop/Engineのメモリ上限が低い

**解決策:**
```bash
# 1. より軽量なモデルに切り替え
./bin/switch-model.sh ruri-v3-30m

# 2. または、Dockerのメモリ上限を増やす
# Docker Desktop: Settings → Resources → Memory: 8GB以上
```

---

## 設定ファイルの詳細

### config/rag.php

```php
'model' => [
    'active' => env('RAG_MODEL', 'all-minilm-l6-v2'),
    
    'available_models' => [
        'ruri-v3-310m' => [
            'name' => 'cl-nagoya/ruri-v3-310m',
            'dimension' => 768,
            'description' => '...',
        ],
        // ... 他のモデル定義
    ],
],
```

**キーポイント:**
- `active`: `.env`の`RAG_MODEL`から読み込み
- `available_models`: 各モデルの設定（名前、次元、説明）
- `dimension`: `ProcessLedgerForRagJob`と`RagSearchService`が参照

### .env

```bash
RAG_ENABLED=true
RAG_MODEL=ruri-v3-310m

# オプション設定
RAG_CHUNK_SIZE=2000
RAG_CHUNK_OVERLAP=400
RAG_BATCH_SIZE=1
```

### docker-compose.yml

```yaml
embedding:
  platform: linux/arm64  # 自動設定
  environment:
    - EMBEDDING_MODEL=cl-nagoya/ruri-v3-310m  # 自動設定
```

---

## ベストプラクティス

### 開発環境でのテスト

1. **小さいモデルで動作確認**
   ```bash
   ./bin/switch-model.sh ruri-v3-30m
   ```

2. **少量データでテスト**
   ```bash
   ./vendor/bin/sail artisan rag:chunk-existing-ledgers --limit=10
   ```

3. **パフォーマンス測定**
   ```bash
   ./bin/test-rag-performance.sh
   ```

### 本番環境への適用

1. **ステージング環境で事前検証**
2. **メンテナンス時間帯に実施**
3. **段階的なロールアウト**（一部データで検証後、全体展開）
4. **ロールバック準備**（旧モデル設定をバックアップ）

### 定期的なメンテナンス

- **モデルの更新確認**: 四半期に1回、新しいモデルをチェック
- **パフォーマンスモニタリング**: エンベディング処理時間を記録
- **次元の統一**: データベース内のチャンクが同じ次元であることを確認

---

## よくある質問（FAQ）

**Q: モデルを切り替えると、既存の検索結果は変わりますか？**

A: はい。異なるモデルは異なるベクトル空間を生成するため、検索結果の順位や類似度スコアが変わります。モデル切り替え後は必ず再チャンク化してください。

**Q: 複数のモデルを同時に使用できますか？**

A: 現在の実装では1つのモデルのみサポートしています。Phase2以降で複数モデル対応を検討する可能性があります。

**Q: モデル切り替えにかかる時間は？**

A: 
- スクリプト実行: 5-10分（イメージビルド含む）
- モデルロード: 30-90秒（モデルサイズによる）
- 再チャンク化: データ量による（1000台帳で約1-2時間）

**Q: ARM64とx86_64でモデルの性能差は？**

A: 
- `ruri-v3-*`: ARM64で最適化済み
- `bge-m3`: x86_64推奨（ARM64では3-5倍遅い）
- その他: アーキテクチャ依存性は低い

---

## 参考資料

- [Phase1実装計画](../work/rag-implementation/2025-10-17-phase1-hybrid-search-plan.md)
- [RAG技術検討書](../work/rag-implementation/2025-10-16-rag-implementation-study.md)
- [RagSearchService基本実装](../work/rag-implementation/2025-10-18-wbs-2-1-2-2-completion-report.md)

---

**承認者:** _____________  
**日付:** _____________
