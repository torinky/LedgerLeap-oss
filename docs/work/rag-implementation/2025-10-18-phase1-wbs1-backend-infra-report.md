# RAG導入 Phase1 WBS-1 バックエンド基盤構築 完了報告（および性能テスト中間報告）

**作成日:** 2025年10月18日
**ステータス:** 一部完了（ブロッカー発生中）
**担当:** Gemini

> **📖 関連ドキュメント:**
> - [2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md) - 実装計画
> - [2025-10-18-phase1-wbs0-environment-setup-report.md](./2025-10-18-phase1-wbs0-environment-setup-report.md) - WBS-0完了報告

---

## 1. 作業概要

実装計画書に基づき、WBS ID 1「バックエンド基盤構築」の主要タスクを実装しました。しかし、実装後の性能テスト段階で深刻なブロッカーに直面しており、WBS 1 は**未完了**となります。本ドキュメントは、実装内容の報告と、発生している問題に関する詳細な中間報告を兼ねます。

## 2. 実施した作業詳細

### 2.1. 設定ファイルの導入（追加要件対応）

-   **目的:** RAG関連のすべての設定を1箇所で管理し、柔軟な機能切り替えと性能チューニングを可能にする。
-   **実装:**
    -   `config/rag.php` を新規作成しました。
    -   このファイルには、以下の設定項目を定義しました。
        -   `enabled`: セマンティック検索機能全体の有効/無効フラグ。
        -   `embedding_service`: PythonコンテナのURLやタイムアウト設定。
        -   `model`: アクティブなEmbeddingモデルの選択と、各モデルの次元数などの情報。
        -   `chunking`: チャンク化のサイズとオーバーラップ文字数。
        -   `performance`: ONNX Runtimeの性能に関わる詳細なパラメータ（グラフ最適化レベル、スレッド数、量子化の有効化など）。
    -   関連する環境変数を `.env.example` に追加し、環境ごとの設定変更を容易にしました。

### 2.2. WBS 1.1: `ledger_chunks` テーブルのマイグレーション作成

-   `artisan make:migration` コマンドで `create_ledger_chunks_table` マイグレーションを作成しました。
-   スキーマには、`ledger_id`, `chunk_text`, `embedding` などのカラムを計画通り定義しました。
-   `embedding` カラム（`BINARY`型）のサイズは、`config/rag.php` で選択されているアクティブなモデルの次元数 (`dimension`) から動的に計算されるように実装し、将来的なモデル変更に強い構造としました。

### 2.3. WBS 1.3: `LedgerObserver` の実装

-   `LedgerObserver` を作成し、`AppServiceProvider` に登録しました。
-   台帳モデルのライフサイクルイベント（`created`, `updated`, `deleted`）を監視し、`config('rag.enabled')` が `true` の場合に以下の非同期処理をトリガーするように実装しました。
    -   **`created` / `updated`:** `ProcessLedgerForRagJob` をキューにディスパッチし、台帳内容のチャンク化とベクトル化を実行します。（`updated`時は `content` または `content_attached` の変更時のみ）
    -   **`deleted`:** 関連する `ledger_chunks` レコードをDBから削除し、データの一貫性を保ちます。

### 2.4. WBS 1.4: `ProcessLedgerForRagJob` の実装

-   キューで非同期に実行される `ProcessLedgerForRagJob` を作成しました。
-   主なロジックは以下の通りです。
    1.  既存のチャンクを削除し、更新処理の冪等性を確保。
    2.  `content` (JSON) と `content_attached` (TEXT) の両方からテキストを抽出し、設定ファイルで定義されたサイズとオーバーラップでチャンクに分割。
    3.  `EmbeddingService` を通じて、全チャンクのベクトルデータを一括で取得。
    4.  取得したベクトルデータとチャンクテキストを `ledger_chunks` テーブルに一括で挿入し、DBへのI/Oを最適化。
    5.  エラーハンドリングと、`config/rag.php` で指定されたチャネルへのログ出力を実装。

### 2.5. WBS 1.5: `EmbeddingService` の実装

-   PHPアプリケーションとPythonコンテナ間の通信を責務とする `EmbeddingService` を作成し、DIコンテナにシングルトンとして登録しました。
-   `config/rag.php` からAPIのURLやタイムアウト値を読み込み、Laravelの `Http` ファサードを利用して `embedding` サービスへのリクエストを送信します。

### 2.6. WBS 1.6: Pythonコンテナの動的設定対応（追加タスク）

-   **目的:** Laravelの `config/rag.php` から、PythonコンテナのONNX Runtimeの挙動を動的に制御する。
-   **実装:**
    1.  `EmbeddingService` を修正し、`/embed` エンドポイントへのリクエストペイロードに `performance` 設定（`config('rag.performance')`）を含めるようにしました。
    2.  Python側の `app.py` を修正。リクエスト毎に `performance` 設定を受け取り、`onnxruntime.SessionOptions` を動的に構築する `get_model` 関数を実装しました。
    3.  `SentenceTransformer` の `model_kwargs` パラメータを使い、構築したセッションオプションを渡すことで、スレッド数や実行モードなどをリクエスト毎に変更可能にしました。
    4.  性能設定の組み合わせごとに、ロード済みのモデルオブジェクトをキャッシュする仕組みを導入し、リクエスト毎にモデルを再ロードするオーバーヘッドを回避しました。

## 3. 調査結果と技術的判断

### Mroongaにおけるベクトル検索の実現方式

-   **当初の想定:** Mroongaに、一般的なベクトルDBが持つようなベクトル検索専用のインデックス（例: HNSW）を作成するSQL構文が存在すると想定していました。

-   **調査結果:**
    1.  `google_web_search` で "mroonga vector index syntax" や "mroonga vector search example" を調査した結果、Mroongaが提供する `COLUMN_VECTOR` は、タグのようなスカラー値の配列を格納・検索するための機能であり、AIが生成する高次元の数値ベクトル（Embedding）の類似度検索を直接サポートするものではないことが判明しました。
    2.  しかし、Mroongaのベースエンジンである **Groonga** は、`vector_search` 関数によるベクトル検索機能をネイティブでサポートしています。
    3.  さらに "mroonga call groonga function" で調査を進めたところ、Mroongaが提供するUDF（ユーザー定義関数） **`mroonga_command()`** を利用すれば、SQL内から任意のGroongaコマンドを文字列として実行できることがわかりました。

-   **技術的判断:**
    -   この `mroonga_command()` を活用し、Groongaの `select` コマンドと `vector_search` 関数を組み合わせたクエリをLaravelから実行する方針を決定しました。これにより、**新たなミドルウェア（専用ベクトルDB）を導入することなく、既存の技術スタック内でベクトル検索を実現できる**という、本プロジェクトの重要な前提条件を満たせる見込みが立ちました。
    -   具体的な実行クエリのイメージ: `SELECT mroonga_command("select ledger_chunks --filter 'vector_search(embedding, [0.1, ...])' ...")`

## 4. 今後のWBSへの影響

今回の実装と調査結果に基づき、今後のWBSに以下の変更・考慮事項を提案します。

-   **WBS 1.2 (Mroongaベクトル検索の技術検証):**
    -   `mroonga_command()` を使うという具体的な実現方式が確立できたため、独立したSpikeタスクとしては**完了**とみなし、具体的なクエリ実装は後続の `RagSearchService` の実装タスク（WBS 2.2）に統合します。

-   **WBS 2.2 (ベクトル検索とスコア集計ロジック実装):**
    -   このタスクの具体的な作業として、`DB::raw()` などを用いて `mroonga_command()` を実行するクエリの構築が必須となります。
    -   `mroonga_command()` の戻り値はJSON文字列であるため、PHP側で `json_decode()` を行い、返されたチャンクIDとスコアをパースする処理の実装が必要になります。

-   **WBS 5.1 (バックエンド単体テスト作成):**
    -   `mroonga_command()` を含むクエリのテストは、実際のDB（Mroonga）に接続した状態での実行が不可欠です。したがって、テストケースでは `DatabaseMigrations` トレイト（`RefreshDatabase`ではない）を使用する必要があります。

## 5. WBS 1 性能テスト計画

### 5.1. 目的

WBS 1で構築したバックエンド基盤、特にチャンク化とエンベディング生成処理（`ProcessLedgerForRagJob`）の基本的な性能（スループット、レイテンシ）を測定し、現状のボトルネックを特定します。このテストは、後続のWBS 5.4（パフォーマンステスト）のベースラインとなります。

### 5.2. テスト対象と測定指標

-   **テスト対象:**
    -   `ProcessLedgerForRagJob`: チャンク化〜DB保存までの一連の処理。
    -   `EmbeddingService`: PHP-Python間のHTTP通信オーバーヘッド。
    -   `embedding`コンテナ: 純粋なベクトル化処理。
-   **測定指標:**
    -   **スループット:** 単位時間あたりに処理できるJob数。
    -   **レイテンシ:** 1つのJobが完了するまでの時間。
    -   **リソース使用率:** `embedding`コンテナのCPU・メモリ使用量。

### 5.3. テスト方法

1.  **ベンチマーク用Artisanコマンドの作成 (`php artisan rag:benchmark`)**
    -   指定された数のダミー台帳を作成する機能。
    -   作成した台帳に対して `ProcessLedgerForRagJob` をディスパッチする機能（同期/非同期の切り替え）。
    -   処理全体の実行時間を計測・表示する機能。
    -   コマンドオプション: `--ledgers=N` (台帳数), `--content-size=S` (文字数), `--sync` (同期実行フラグ)。

2.  **詳細なログ出力の活用**
    -   `ProcessLedgerForRagJob` と `EmbeddingService` 内に、処理の各ステップ（チャンク化開始、API呼び出し前後、DB保存後など）で `microtime(true)` を使ったタイムスタンプ付きのログを既に追加済みです。このログを分析し、処理のどの部分に時間がかかっているかを詳細に分析します。

3.  **リソース監視**
    -   テスト実行中に別ターミナルで `docker stats ledgerleap_embedding` コマンドを実行し、リソース使用状況を継続的に監視・記録します。

### 5.4. テストシナリオ

-   **シナリオ1 (ベースライン測定):**
    -   **コマンド:** `php artisan rag:benchmark --ledgers=10 --content-size=2000 --sync`
    -   **目的:** 単一Jobの平均処理時間と、その中での各ステップ（チャンク化、API通信、DB保存）の所要時間を確認する。

-   **シナリオ2 (スループット測定):**
    -   **コマンド:** `php artisan rag:benchmark --ledgers=100 --content-size=2000`
    -   **目的:** 100件のJobを非同期でキューに投入し、すべてが完了するまでの総時間と、`docker stats` でのリソース使用率のピークを確認する。

-   **シナリオ3 (パラメータチューニング評価):**
    -   **手順:** `.env` ファイルで `RAG_INTRA_OP_THREADS` をCPUコア数に合わせて変更（例: `RAG_INTRA_OP_THREADS=4`）。
    -   **コマンド:** シナリオ1とシナリオ2を再実行。
    -   **目的:** ベースラインとの性能差を比較し、パラメータチューニングの効果を評価する。

---

## 6. 性能テストの実行結果とデバッグ経緯

上記計画に基づき性能テストを実行しましたが、`embedding`サービスが正常に応答せず、テストを完了できませんでした。問題解決のために以下のデバッグを実施しました。

-   **現象:** `rag:benchmark` コマンドを実行すると、`cURL error 52: Empty reply from server` や `cURL error 28: Operation timed out` が発生。最終的には `cURL error 6: Could not resolve host` に至り、コンテナが不安定な状態であることが確認されました。

-   **原因切り分け:**
    1.  **`curl`での直接テスト:** `laravel` コンテナから `curl` で直接 `embedding` サービスにリクエストを送信しても、同様のエラーが再現されました。これにより、Laravel側のコードではなく、Pythonコンテナ側に問題があることを特定しました。
    2.  **インタラクティブシェルでの実行:** コンテナをデバッグモードで起動し、コンテナ内でPythonのREPL（`ipython`）を使い、手動でモデル `SentenceTransformer(...)` のロードを試みたところ、**時間はかかるものの正常に完了しました。**

-   **根本原因の特定:**
    -   インタラクティブ実行の成功から、問題はモデルロード処理そのものではなく、「**FastAPI/Uvicornのワーカープロセス管理と、`sentence-transformers`の重い同期的な初期化処理の相性の悪さ**」であると結論付けました。
    -   リクエストを受けてからモデルをロードする方式では、ロード中にワーカーが長時間ブロックされ、Uvicornのメインプロセスからハングしたと見なされ強制終了させられていました。これが `Empty reply` や `Connection reset` の原因です。

-   **対策と現状:**
    -   対策として、`app.py` を全面的に書き換え、リクエスト毎ではなく**FastAPIの起動時にモデルを一度だけプリロードする方式**に変更しました。
    -   しかし、この方式でもコンテナ起動時にモデルロード処理でメモリを大量に消費し、Dockerコンテナのメモリ制限を超えてクラッシュと再起動を繰り返す「**CrashLoopBackOff**」の状態に陥ってしまいました。`docker-compose.yml` のメモリ制限を8GBに増やしても、この状況は改善されませんでした。

## 7. 判明した問題と今後の対策（要処置）

### 7.1. 現状のブロッカー

**`embedding` コンテナが、モデルロード時のメモリ不足が原因で安定起動しない。**

Docker for Mac環境におけるリソース制限、特にメモリ割り当てが、`bge-m3` や `multilingual-e5-base` のような1GBを超えるモデルを安定してロードするには不十分である可能性が極めて高いです。

### 7.2. 対策（要処置）

このブロッカーを解消し、WBS 1を完了させて次のステップに進むため、以下の対策を提案します。

#### **提案A: さらに軽量なモデルへの変更（最優先）**

-   **処置内容:** 現在のモデル（~1.1GB）から、より軽量で実績のある **`all-MiniLM-L6-v2`**（約90MB, 384次元）にモデルを変更します。
-   **目的:** まずはリソースの制約が少ない環境で、パイプライン全体の疎通確認と機能実装を完了させることを最優先とします。
-   **影響:** 精度は `bge-m3` 等に劣る可能性がありますが、基本的なセマンティック検索の機能検証は可能です。WBS 1完了後、より高性能なモデルでの検証を再度計画します。
-   **具体的な作業:**
    1.  `config/rag.php` のモデル定義を `all-MiniLM-L6-v2` に変更。
    2.  `database/migrations/xxxx_create_ledger_chunks_table.php` の `embedding` カラムのサイズを `384 * 4 = 1536` に変更。

#### **提案B: Dockerリソース割り当ての増加**

-   **処置内容:** ユーザー自身でDocker Desktopの設定画面を開き、Docker Engineに割り当てるメモリを大幅に（例: 12GB ~ 16GB）増やしていただきます。
-   **備考:** これはユーザー環境に依存するため、開発チーム全体での標準的な解決策とはなりませんが、高性能モデルをローカルで試すための一時的な選択肢とはなり得ます。

### 7.3. 次のアクション

**提案A「さらに軽量なモデルへの変更」** を実行し、`curl`での検証を成功させ、計画していた性能テスト（シナリオ1）を完了させることを提案します。これにより、WBS 1を正式に完了とすることができます。

ユーザーの承認が得られ次第、モデル変更の作業に着手します。