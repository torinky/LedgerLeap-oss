# Embeddingコンテナのオフライン起動

**作成日:** 2026年4月5日  
**最終更新:** 2026年4月5日

---

## 背景・問題

`embedding` コンテナは起動時に `SentenceTransformer` ライブラリが
HuggingFace Hub へネットワークアクセスし、モデルのリビジョン確認・不在ファイルチェック等を行います。

**外部接続がない環境（イントラネット本番サーバー・CI環境・オフラインラボ等）で
コンテナを起動しようとすると、以下のループが発生して起動できない事象が確認されました:**

```
# コンテナログに繰り返し出力される例
ConnectionError: ('Connection aborted.', RemoteDisconnected('Remote end closed connection without response'))
requests.exceptions.HTTPError: 403 Client Error ...
```

モデルファイル自体はローカルキャッシュ（`./storage/app/embedding/`）に完全に存在していても、
`SentenceTransformer('cl-nagoya/ruri-v3-310m')` というモデル名指定だけでは
HuggingFace Hub へのアクセスが省略されません。

---

## 解決策：キャッシュ自動検出によるオフライン起動

`docker/embedding/app.py` に **キャッシュ自動検出ロジック** を実装しました。

### 動作フロー

```
コンテナ起動
    │
    ▼
_is_model_cached() で config.json の存在を確認
    │
    ├─ キャッシュなし（初回 / モデル未ダウンロード）
    │       │
    │       ▼
    │   local_files_only = False
    │   → HuggingFace Hub へ接続してダウンロード
    │   → キャッシュを ./storage/app/embedding/ に保存
    │
    └─ キャッシュあり（2回目以降）
            │
            ▼
        local_files_only = True
        → ネットワーク不要でローカルから直接起動
```

### 初回構築時の動作

モデルキャッシュが存在しない場合は **自動的にHuggingFace Hubへ接続** してモデルをダウンロードします。
ダウンロード先は `./storage/app/embedding/`（Dockerボリュームでコンテナ内 `/app/models` にマウント）です。

```
./storage/app/embedding/
└── models--cl-nagoya--ruri-v3-310m/
    ├── blobs/          # 実体ファイル（model.safetensors, tokenizer.json など）
    ├── refs/main       # 最新コミットSHA
    └── snapshots/<sha>/  # ファイルへのシンボリックリンク
```

### 2回目以降の動作

`config.json` のキャッシュ存在確認（`huggingface_hub.try_to_load_from_cache`）で
ローカルキャッシュを検出し、`local_files_only=True` を指定してモデルをロードします。
この場合 **一切のネットワークアクセスは発生しません**。

---

## EMBEDDING_OFFLINE 環境変数による制御

| 値 | 動作 |
|----|------|
| **未設定（デフォルト）** | 自動検出: キャッシュあり→オフライン / なし→HFからダウンロード |
| `EMBEDDING_OFFLINE=0` | 強制オンライン（常にHF Hubに接続して最新確認。更新時に使用） |
| `EMBEDDING_OFFLINE=1` | 強制オフライン（キャッシュが存在しない場合は起動失敗） |

### .env への設定例

```bash
# デフォルト（通常はコメントアウトのまま）
# EMBEDDING_OFFLINE=

# モデルを最新版に強制更新したい場合
EMBEDDING_OFFLINE=0

# 完全オフライン環境で誤ったダウンロードを防止したい場合
EMBEDDING_OFFLINE=1
```

> **注意:** `EMBEDDING_OFFLINE=1` 設定でキャッシュが存在しない場合、
> コンテナは `ERROR` 状態になりヘルスチェックが失敗します。
> 依存する `queue` サービスも `depends_on` で起動待機するため、
> スタック全体が起動しなくなります。

---

## 技術的な実装詳細

### キャッシュ検出の仕組み

```python
# docker/embedding/app.py

from huggingface_hub import try_to_load_from_cache

def _is_model_cached(model_name: str, cache_folder: str) -> bool:
    result = try_to_load_from_cache(
        repo_id=model_name,
        filename="config.json",   # 軽量ファイルで存在確認
        cache_dir=cache_folder,
    )
    return isinstance(result, str)  # 文字列（パス）が返れば存在
```

`try_to_load_from_cache` の戻り値:
- `str` — ローカルキャッシュのファイルパス（存在する）
- `None` — キャッシュなし（HFには存在するがローカルにない）
- `_CACHED_NO_EXIST` — HFにも存在しないことがキャッシュされている

### モデルロード時の指定

```python
model = SentenceTransformer(
    model_name_to_load,
    device=device,
    cache_folder='/app/models',
    local_files_only=local_files_only,   # ← 自動決定
)
```

### ログ出力例

**キャッシュあり（通常の2回目以降）:**
```
Cache detected: /app/models/models--cl-nagoya--ruri-v3-310m/snapshots/.../config.json
Offline mode: AUTO → cache found, starting in offline mode (no network needed)
```

**キャッシュなし（初回）:**
```
No cache found for model: 'cl-nagoya/ruri-v3-310m'
Offline mode: AUTO → no cache found, downloading from HuggingFace Hub (internet required)
```

---

## トラブルシューティング

### 症状: 初回起動でモデルダウンロードに失敗する

**原因:** 外部接続がない環境で初めて起動しようとしている

**対処法:**
1. 外部接続が可能な環境でコンテナを一度起動してキャッシュを作成する
2. `./storage/app/embedding/` にキャッシュが作成されたことを確認する
3. 外部接続のない環境にプロジェクトを持ち込む（キャッシュごと転送する）

```bash
# キャッシュ作成の確認
ls ./storage/app/embedding/models--cl-nagoya--ruri-v3-310m/
# → blobs/, refs/, snapshots/ が存在すれば OK
```

### 症状: `EMBEDDING_OFFLINE=1` 設定で ERROR になる

**原因:** キャッシュが存在しないのに強制オフラインが指定されている

**対処法:**
```bash
# 強制オフラインを一時解除してダウンロード
EMBEDDING_OFFLINE=0 docker compose up embedding

# ダウンロード完了後、設定を削除（autoモードに戻す）
# .env の EMBEDDING_OFFLINE= をコメントアウト
```

### 症状: モデルを最新版に更新したい

**対処法:**
```bash
# 1. 強制オンラインモードで起動
echo "EMBEDDING_OFFLINE=0" >> .env

# 2. キャッシュをクリアして再ダウンロード
rm -rf ./storage/app/embedding/models--cl-nagoya--ruri-v3-310m/
./vendor/bin/sail restart embedding

# 3. ダウンロード完了後、.env の EMBEDDING_OFFLINE 設定を削除
```

### 症状: `adapter_config.json` が繰り返し参照されている

**説明:** `.no_exist/` ディレクトリ配下のファイルは「HFにこのファイルが存在しない」という
ネガティブキャッシュです。オフライン起動後にこのキャッシュが更新される場合がありますが、
`local_files_only=True` の状態ではHFへの接続は発生しないため **問題ではありません**。

---

## 変更されたファイル

| ファイル | 変更内容 |
|---------|---------|
| `docker/embedding/app.py` | `_is_model_cached()` と `_resolve_local_files_only()` 関数を追加。`SentenceTransformer` に `local_files_only` パラメータを追加 |
| `docker-compose.yml` | `embedding` サービスの環境変数コメントに `EMBEDDING_OFFLINE` の制御方法を記載 |
| `.env.example` | `EMBEDDING_OFFLINE` 変数のコメントとデフォルト値を追加 |

---

## 関連ドキュメント

- [RAG Embedding Model Switching Guide](./model-switching-guide.md)
- [環境構築スクリプト](../development/environment-setup.md)
- [AI・検索機能ガイド](../ai-and-search-guide.md)
