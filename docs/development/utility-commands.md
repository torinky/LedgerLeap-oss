# ユーティリティコマンド

本ドキュメントは、LedgerLeapの開発や運用で利用するカスタムArtisanコマンドやシェルスクリプトについて解説します。

## 1. 環境構築スクリプト

### `./bin/setup.sh`

-   **目的:** 新規開発者がプロジェクトをクローンした後、このスクリプトを一度実行するだけで、依存関係のインストールからデータベースのマイグレーション、フロントエンドアセットのビルドまで、開発を開始するために必要な全てのセットアップを自動で行います。
-   **前提条件:** Docker Desktopがローカル環境にインストールされ、実行中であること。
-   **実行例:**
    ```bash
    # プロジェクトのルートディレクトリで実行
    ./bin/setup.sh
    ```
-   **主な処理内容:**
    1.  `.env` ファイルのセットアップ
    2.  Dockerイメージのビルドとコンテナの起動
    3.  Composer依存関係のインストール
    4.  アプリケーションキーの生成
    5.  データベースのマイグレーション
    6.  NPM依存関係のインストール
    7.  フロントエンドアセットのビルド

## 2. テナント管理

### `app:setup-tenant`

-   **目的:** 新しいテナントを作成し、関連する初期設定（ドメイン紐付け、DBマイグレーション、初期フォルダ作成、管理者割り当てなど）を対話的に実行します。
-   **コマンド形式:**
    ```bash
    php artisan app:setup-tenant {tenant_id} {name} {admin_email}
    ```
-   **引数:**
    *   `tenant_id`: テナントの一意なID（URLパスなどに使用）。
    *   `name`: テナントの表示名。
    *   `admin_email`: テナントの初期管理者として割り当てるユーザーのメールアドレス（中央DBに存在している必要があります）。
-   **実行例:**
    ```bash
    # 'acme' というIDで "ACME Corporation" テナントを作成し、admin@example.com を管理者に設定
    ./vendor/bin/sail artisan app:setup-tenant acme "ACME Corporation" admin@example.com
    ```

## 3. 定期実行コマンド（スケジューラ）

以下のコマンドは、通常 `app/Console/Kernel.php` に登録され、Laravelのスケジューラによって定期的に実行されます。手動で実行することも可能です。

### `workflow:send-summary`

-   **目的:** ワークフローの未処理タスク（点検・承認待ち）がある担当者に対して、タスクの件数を知らせる集約通知を送信します。
-   **実行例（手動）:**
## 4. RAG / ベクトル検索

### `rag:chunk-existing-ledgers`

-   **目的:** 既存の台帳データおよび添付ファイルに対して、RAG（検索拡張生成）用のベクトルインデックス（チャンク）を生成・再構築します。埋め込みモデルの変更時や、検索精度の改善（メタデータ注入など）を適用する際に使用します。
-   **コマンド形式:**
    ```bash
    php artisan rag:chunk-existing-ledgers {--target=all} {--force} {--limit=} {--only-missing}
    ```
-   **オプション:**
    *   `--target`: 処理対象を指定します（デフォルト: `all`）。
        *   `all`: 台帳本体と添付ファイルの両方を処理。
        *   `ledger`: 台帳本体（カラム値など）のみ処理。
        *   `files`: 添付ファイル（OCR/VLM結果）のみ処理。
    *   `--force`: 既存のチャンクがある場合でも、強制的に削除して再生成します。
    *   `--limit`: 処理する台帳の最大数を指定します（テスト用）。
    *   `--only-missing`: チャンクがまだ存在しない台帳のみを処理します。
-   **実行例:**
    ```bash
    # 全データのチャンクを強制的に再構築（推奨: モデル変更時など）
    ./vendor/bin/sail artisan rag:chunk-existing-ledgers --target=all --force

    # 添付ファイルのみ再処理（OCRエンジンの更新後など）
    ./vendor/bin/sail artisan rag:chunk-existing-ledgers --target=files --force

    # 未処理のデータのみチャンク生成（中断後の再開など）
    ./vendor/bin/sail artisan rag:chunk-existing-ledgers --only-missing
    ```

