# ユーティリティコマンド

本ドキュメントは、LedgerLeapの開発や運用で利用するカスタムArtisanコマンドやシェルスクリプトについて解説します。

## 1. 環境構築・テスト準備

### `./bin/setup.sh`

-   **目的:** 新規開発者がプロジェクトをクローンした後、このスクリプトを一度実行するだけで、開発を開始するために必要な全てのセットアップを自動で行います。
-   **主な処理内容:**
    1.  `.env` ファイルのセットアップ
    2.  Dockerイメージのビルドとコンテナの起動
    3.  Composer/NPM依存関係のインストール
    4.  アプリケーションキーの生成とデータベースのマイグレーション

### `./bin/prepare-local-test-env.sh`

-   **目的:** ローカル開発環境において、機能テストや結合テストを実行するための準備を行います。
-   **内容:** テスト用データベースの作成、マイグレーション、および必要なテストアセットの配置を行います。内部的に `./bin/reset-test-db.sh` を呼び出します。

### `./bin/reset-test-db.sh`

-   **目的:** テスト用データベース（`ledgerleap_test` など）をドロップして再作成し、最新のマイグレーションを適用します。
-   **備考:** テストが失敗してDB状態が壊れた際や、スキーマ変更後のテスト実行前に使用します。

---

## 2. AI・モデル管理

### `./bin/switch-model.sh`

-   **目的:** アプリケーションが使用するLLM（GPT-4o, Claude 3.5 Sonnet等）や、埋め込みモデル（text-embedding-3-small等）の設定を対話的に切り替えます。
-   **動作:** `.env` ファイルの `AI_MODEL` や `EMBEDDING_MODEL` を書き換え、コンテナを再起動せずに反映可能な設定を更新します。

### `./bin/vlm-switch.sh`

-   **目的:** 添付ファイルの解析に使用するVLM（視覚言語モデル）サービスの種類（`paddleocr`, `gpt-4o`, `gemini-1.5-pro`等）を切り替えます。
-   **実行例:** `./bin/vlm-switch.sh gpt-4o`
-   **詳細:** [VLM/OCRエンジン統合ドキュメント](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/docs/development/vlm-ocr.md) を参照してください。

### `./bin/sync-ai-instructions.sh`

-   **目的:** プロジェクトルートの `.github/copilot-instructions.md` や各種スキル定義を、Gemini CLI や IDE プラグインが参照する形式に同期・変換します。
-   **用途:** AIアシスタントへの指示（Prompt Engineering）を更新した際に実行します。

### `ai:generate-skill-pack`

-   **目的:** 特定のドメイン（Livewire, 認可など）に特化したAI用コンテキストパックを生成します。
-   **コマンド:** `php artisan ai:generate-skill-pack {domain}`

---

## 3. テナント管理

### `app:setup-tenant`

-   **目的:** 新しいテナントを作成し、関連する初期設定を対話的に実行します。
-   **コマンド形式:** `php artisan app:setup-tenant {tenant_id} {name} {admin_email}`
-   **詳細:** ドメイン紐付け、DBマイグレーション、初期フォルダ作成、管理者割り当てを自動で行います。

---

## 4. 運用・外部連携

### `ad:sync`

-   **目的:** Active Directory (AD) からユーザーおよび組織情報を同期します。
-   **主な機能:**
    *   AD上のOU構造を組織（Organizations）として同期
    *   ユーザー属性（氏名、メールアドレス、所属組織）の更新
    *   ADから削除されたエンティティの無効化処理
-   **オプション:**
    *   `--dry-run`: 実際の更新を行わず、変更予定の内容を表示します。
    *   `--force`: 削除閾値などを無視して強制的に同期を実行します。
-   **詳細:** `docs/work/architecture/authentication/2025-11-29_ad_integration_implementation_log.md` に実装詳細があります。

### `workflow:send-summary`

-   **目的:** ワークフローの未処理タスク（点検・承認待ち）がある担当者に対して、集約通知を送信します。
-   **備考:** 通常はスケジューラによって毎朝実行されます。

---

## 5. RAG / ベクトル検索

### `rag:chunk-existing-ledgers`

-   **目的:** 既存の台帳データおよび添付ファイルに対して、RAG用のベクトルインデックスを生成・再構築します。
-   **詳細:** 埋め込みモデルの変更時や、チャンク分割アルゴリズムの調整時に使用します。

### `rag:chunk-status`

-   **目的:** テナントごとのベクトルインデックス作成状況（未処理件数、エラー件数）を一覧表示します。
-   **用途:** 大規模なデータインポート後の進捗確認に使用します。

### `rag:benchmark`

-   **目的:** RAG処理（検索＋生成）のパフォーマンスと精度を計測します。
-   **コマンド形式:** `php artisan rag:benchmark {--ledgers-count=10} {--content-size=small}`
-   **出力:** 検索時間、LLM応答時間、トークン使用量、および回答の精度スコア。

### `./bin/test-rag-performance.sh`

-   **目的:** シナリオベースのRAG性能テストを一括実行します。
-   **詳細:** 複数の質問セットを用いて、検索のヒット率や回答の妥当性を自動評価します。

---

## 6. データメンテナンス・修復

### `ledger:repair-json-columns`

-   **目的:** 一部のデータ移行プロセスで発生した「二重エンコードされたJSONカラム」を検知し、正しい形式に修復します。
-   **実行例:** `php artisan ledger:repair-json-columns --tenant=acme --dry-run`

### `ledger:regenerate-default-sort`

-   **目的:** 台帳レコードの `default_sort_value`（ソートパフォーマンス向上のための冗長カラム）を再計算して更新します。
-   **用途:** 並び順のロジックを変更した際や、データの整合性が崩れた場合に使用します。

### `ledger:finalize-processing`

-   **目的:** 添付ファイルの非同期処理（VLM/OCR解析）が完了した際、結果をドキュメントメタデータに書き込み、検索可能状態に移行させます。

---

## 7. 開発・テスト支援

### `mcp:create-test-ledger`

-   **目的:** 構造解析（Structure Analysis）テスト用のサンプル台帳と添付ファイルを生成します。
-   **用途:** 新しいVLMプロンプトの検証や、複雑なレイアウトの帳票テストに使用します。

### `demo:generate-mcp-token`

-   **目的:** デモ環境でAIエージェント（MCP）を利用するための期間限定アクセスキーを発行します。

### `mroonga:test`

-   **目的:** Mroonga（全文検索エンジン）が正しく動作しているか、インデックスが有効かをテスト検索によって検証します。
-   **備考:** 日本語の分かち書き（TokenMecab等）が正常に機能しているかの確認に有用です。
��ラム値など）のみ処理。
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

## 8. 翻訳管理

### `translations:compare`

-   **目的:** `lang/ja/ledger.php`（PHPファイル群）を正本として、`lang/ja.json` の `ledger.*` キーを**片方向で上書き同期（コンパイル）**します。`laravel-lang` など外部ライブラリが `ja.json` に書き込んだキーには一切干渉しません。
-   **コマンド形式:**
    ```bash
    php artisan translations:compare [--dry-run] [--force]
    ```
-   **オプション:**
    *   `--dry-run`: 変更内容をコンソールに表示するだけで、ファイルへの書き込みは行いません。CIや確認作業に使用します。
    *   `--force`: 確認プロンプトをスキップして、即座に `ja.json` を更新します。`composer.json` の `post-update-cmd` フックから自動実行される際に使用します。
-   **実行例:**
    ```bash
    # 変更内容を確認するだけ（ファイルは変更されない）
    ./vendor/bin/sail artisan translations:compare --dry-run

    # 確認プロンプトなしで即時反映（CI / post-update-cmd 向け）
    ./vendor/bin/sail artisan translations:compare --force
    ```
-   **翻訳追加の手順:**
    1.  `lang/ja/ledger/` 配下の適切なカテゴリのPHPファイル（例: `ui.php`, `workflow.php`, `notifications.php`）を編集します。
    2.  `php artisan translations:compare` を実行して `lang/ja.json` に反映します。
    3.  `lang/ja/ledger.php` を直接編集してはいけません（プロキシファイルであるため）。

> **注意:** `lang/ja/ledger.php` はプロキシアグリゲータです。直接編集せず、`lang/ja/ledger/` 配下のファイルを編集してください。
