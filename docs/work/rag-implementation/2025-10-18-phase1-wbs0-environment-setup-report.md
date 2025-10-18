# RAG導入 Phase1 WBS-0 実行環境準備 完了報告

**作成日:** 2025年10月18日
**ステータス:** 完了
**担当:** Gemini

> **📖 関連ドキュメント:**
> - [2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md) - 実装計画

---

## 1. 作業概要

実装計画書に基づき、WBS ID 0「実行環境準備」のタスクを完了しました。
この作業の目的は、セマンティック検索の根幹となるエンベディングモデルを実行するための、独立したPythonコンテナ環境を構築することです。

## 2. 実施した作業詳細

### 2.1. タスク 0.1: Docker Compose設定更新（Pythonコンテナ追加）

-   `docker-compose.yml` に、FastAPIベースのPythonアプリケーションを実行する `embedding` サービスを新たに追加しました。
-   `docker/embedding/` ディレクトリを作成し、以下のファイルを配置しました。
    -   `Dockerfile`: Python 3.11-slimをベースとし、必要なライブラリをインストールする設定。
    -   `requirements.txt`: `fastapi`, `sentence-transformers`, `onnxruntime` 等のPython依存関係を定義。
    -   `app.py`: テキストを受け取り、ベクトル化して返すためのAPIエンドポイント（`/embed`）と、ヘルスチェック用エンドポイント（`/health`）を持つFastAPIアプリケーション。

### 2.2. タスク 0.2: エンベディングモデルのダウンロードと配置

-   `./vendor/bin/sail build` および `./vendor/bin/sail up -d` コマンドを実行し、定義した `embedding` コンテナを含む全サービスをビルド・起動しました。
-   コンテナの初回起動時に、`app.py` 内の `SentenceTransformer` が、指定されたモデル `BAAI/bge-m3` をHugging Face Hubから自動的にダウンロードすることを確認しました。
-   ダウンロードされたモデルファイルは、`docker-compose.yml` の `volumes` 設定（`./storage/app/embedding:/app/models`）に基づき、ホストOSの `storage/app/embedding` ディレクトリに永続的にキャッシュされます。これにより、コンテナ再起動時のダウンロードが不要になります。

### 2.3. タスク 0.3: ONNX Runtime環境のセットアップ

-   `requirements.txt` に `onnxruntime` を含めることで、コンテナビルド時にONNX Runtimeがインストールされるようにしました。
-   `app.py` では、環境変数 `USE_ONNX=true` に基づいてONNXを利用する旨のログが出力されることを確認しました。
    -   *注: 計画通り、現時点でのONNX最適化処理はプレースホルダーであり、具体的な最適化は今後のタスクで実装します。*

## 3. 発生した問題と解決策

### 問題: ヘルスチェックのタイムアウト

コンテナの初回起動時、`BAAI/bge-m3` モデル（約2.27GB）のダウンロードとメモリへのロードに想定以上の時間がかかりました。その結果、`docker-compose.yml` に設定していたデフォルトのヘルスチェック（30秒間隔、3回リトライ）がタイムアウトし、コンテナのステータスが `unhealthy` となる事象が発生しました。

### 解決策: ヘルスチェック設定の緩和

モデルのロード時間を許容するため、`docker-compose.yml` の `embedding` サービスにおける `healthcheck` 設定を以下のように更新しました。

-   `start_period: 300s`: コンテナ起動後、ヘルスチェックを開始するまでの猶予期間を5分間設定。
-   `interval: 60s`: チェック間隔を60秒に延長。
-   `retries: 5`: リトライ回数を5回に増加。

この変更を適用してコンテナを再作成したところ、モデルのロード完了後にヘルスチェックが成功し、コンテナは正常に `healthy` 状態へ移行しました。

## 4. 最終的な状態

-   `embedding` コンテナは `healthy` 状態で安定稼働しています。
-   `http://localhost:8000/health`（コンテナ内から）にアクセスすると、正常なレスポンスが返却されます。
-   エンベディングモデルのファイル群は、ホストOSの `storage/app/embedding` ディレクトリ配下に永続化されています。
-   WBS-0の全タスクは完了し、次のバックエンド基盤構築（WBS-1）に着手できる状態です。

## 5. 関連ファイル

-   `docker-compose.yml`
-   `docker/embedding/Dockerfile`
-   `docker/embedding/requirements.txt`
-   `docker/embedding/app.py`
