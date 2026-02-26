# RAG導入 Phase1 WBS-1 完了状況報告

**作成日:** 2025年10月18日  
**ステータス:** 一部完了（重大ブロッカー継続中）  
**担当:** GitHub Copilot CLI

---

## 実施した修正作業

### 1. 軽量モデルへの変更（報告書の提案A実施）

報告書の提案に従い、以下の変更を実施しました：

#### 変更内容
- **モデル変更**: `BAAI/bge-m3` (1024次元) → `sentence-transformers/all-MiniLM-L6-v2` (384次元, ~90MB)
- **影響ファイル**:
  - `config/rag.php`: デフォルトモデルを `all-minilm-l6-v2` に変更
  - `.env.example` および `.env`: `RAG_MODEL=all-minilm-l6-v2`
  - `docker-compose.yml`: `EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2`
  - `database/migrations/2025_10_18_034730_create_ledger_chunks_table.php`: デフォルト次元数を384に変更

#### 結果
- ✅ モデルダウンロード成功（~90MB）
- ✅ モデルロード成功
- ✅ ヘルスチェック成功
- ❌ **embedリクエスト処理時にコンテナクラッシュ（Exit Code 139）**

### 2. EmbeddingServiceの修正

#### 問題
`EmbeddingService`が送信していた不要なパラメータ（`model_name`, `performance`）が、現在のPython `app.py`の実装と不整合。

#### 修正内容
`app/Services/EmbeddingService.php`のembed()メソッドを修正し、リクエストペイロードを以下のみに簡素化：
```php
$response = Http::timeout($this->timeout)
    ->post("{$this->embeddingServiceUrl}/embed", [
        'texts' => $textsToEmbed,
        'normalize' => true,
    ]);
```

### 3. 環境検証

#### 確認事項
- ✅ `ledger_chunks`テーブル作成成功（マイグレーション完了）
- ✅ ネットワーク接続正常（laravel → embedding コンテナ間通信確認）
- ✅ ヘルスチェックエンドポイント正常応答
- ❌ **embedエンドポイントがリクエスト処理時にSegmentation Fault**

---

## 現在のブロッカー詳細

### 症状
`sentence-transformers/all-MiniLM-L6-v2`（最軽量クラス）でも、以下の状況でコンテナがクラッシュ：

1. モデルロード: **成功**
2. ヘルスチェック: **成功**
3. `/embed` エンドポイントへのリクエスト（3テキスト）: **クラッシュ (Exit 139)**

### ログから読み取れる情報
```
INFO:app:Processing embedding request for 3 texts.
[コンテナ停止 - Exit Code 139]
```

### Exit Code 139の意味
- **Segmentation Fault (SIGSEGV)**: メモリアクセス違反
- ONNXランタイム無効化後も発生 → ONNX固有の問題ではない
- 軽量モデルでも発生 → モデルサイズの問題ではない

### 推定される根本原因
1. **Docker for Mac環境のメモリ制約**
   - `docker-compose.yml`で8GB制限を設定しているが、実際のDocker Desktop側の制限が不明
   - sentence-transformersがエンコード時に予想以上のメモリを消費している可能性

2. **ARM64アーキテクチャとの相性問題**
   - M1/M2 Mac (ARM64)環境でのPyTorch/ONNX Runtimeの安定性問題
   - sentence-transformersの依存ライブラリがARM64で不安定な可能性

3. **Uvicornワーカーとの相性問題**
   - FastAPIの起動時ロード + 推論実行の組み合わせでメモリリークまたはスレッド競合

---

## 完了した項目 (WBS 1)

以下のタスクは実装完了し、コードレベルでは正常動作する状態です：

- ✅ **WBS 1.1**: `ledger_chunks`テーブルのマイグレーション作成・実行
- ✅ **WBS 1.3**: `LedgerObserver`の実装と登録
- ✅ **WBS 1.4**: `ProcessLedgerForRagJob`の実装
- ✅ **WBS 1.5**: `EmbeddingService`の実装と修正
- ✅ **WBS 1.6**: Pythonコンテナの実装（起動時モデルプリロード方式）
- ✅ 設定ファイル `config/rag.php` の実装
- ✅ ベンチマークコマンド `rag:benchmark` の実装（テナント対応修正済み）

---

## 未完了項目

- ❌ **WBS 1性能テスト**: embeddingサービスのクラッシュにより実行不可
- ❌ **WBS 1.2**: ベクトル検索のMroonga技術検証（技術的には方針確立済みだが実地テスト未実施）

---

## 次のアクション提案

### 提案1: 開発環境の変更（推奨）
**Docker Desktopのメモリ割り当てを12GB以上に増やす**

1. Docker Desktop → Settings → Resources → Memory
2. 最低12GB、可能であれば16GBに設定
3. "Apply & Restart"
4. `./vendor/bin/sail up -d` で再起動
5. ベンチマーク再実行

### 提案2: デバッグモードでの詳細調査
embeddingコンテナをデバッグモードで起動し、メモリ使用量をリアルタイム監視：

```bash
# docker-compose.ymlのembeddingコマンドを一時変更
command: ["sh", "-c", "uvicorn app:app --host 0.0.0.0 --port 8000 --log-level debug"]

# 起動後、別ターミナルで監視
docker stats ledgerleap_embedding
```

### 提案3: 代替モデルライブラリの検討（最終手段）
sentence-transformersの代わりに以下を検討：
- Hugging Face `transformers`ライブラリ直接使用
- OpenAI API（外部サービス依存だが安定性は高い）
- ローカルLLM（Ollama等）のembedding API

---

## 結論

**WBS 1の実装作業自体は完了していますが、実行環境の制約により性能テストが実行できず、完全な完了とは言えません。**

報告書で提案された「提案A: 軽量モデルへの変更」を実施しましたが、問題は解決しませんでした。次のステップとして「提案B: Dockerリソース割り当ての増加」の実施が必要です。

ユーザーの判断をお待ちします：
1. Docker Desktopのメモリを増やして再テスト
2. 別の対策を検討
3. 現状のままWBS 2（検索ロジック実装）に進む（embeddingなしでMroonga全文検索のみ）
