# LedgerLeap MCPサーバー実装計画

**日付:** 2025年9月27日

## 1. 概要

### 1.1. 目的

本ドキュメントは、LedgerLeapアプリケーション自体に**MCP（Managed Code Platform）サーバー機能**を実装し、`gemini` CLIから`laravel-boost`や`serena`と同等のネイティブなツールとして認識・利用できるようにするための、調査・開発計画を定義する。

これにより、OpenAPI仕様を手動で`function_declarations`に変換する手間をなくし、よりシームレスで高度なLLM連携を実現することを目的とする。

### 1.2. 関連ドキュメント

- **[LLM連携フェーズ1 API技術仕様書](./2025-09-24_LLM_Phase1_API_Specification.md):** MCPサーバーが公開するAPIの仕様。
- **[LLM連携機能 開発ロードマップ](./2025-09-23_LLM_Integration_Roadmap.md):** プロジェクト全体のロードマップ。

## 2. ゴール

- LedgerLeapアプリケーションが、`artisan ledgerleap:mcp`のようなコマンドでMCPサーバーとして起動できる。
- `.gemini/settings.json`にLedgerLeapをMCPサーバーとして登録できる。
- `gemini` CLIが、起動時にLedgerLeapのAPI（`search`, `createLedger`等）を自動的にツールとして認識する。
- ユーザーが自然言語で指示した際に、`gemini` CLIがLedgerLeapのAPIを直接呼び出し、タスクを実行できる。

## 3. 開発計画

この計画は、**「調査フェーズ」**と**「実装フェーズ」**の2段階で進める。`laravel-boost`の`artisan boost:mcp`コマンドの実装をリバースエンジニアリングし、同様の仕組みをLedgerLeapに構築する。

---

### 3.1. 調査フェーズ：Laravel APIをMCPサーバー化する既存アプローチの特定

`laravel-boost`の`artisan boost:mcp`コマンドの存在は、LaravelコミュニティにAPIをMCPサーバー化するための共通のアプローチやパッケージが存在する可能性を示唆している。そのため、`laravel-boost`の独自実装をリバースエンジニアリングする前に、まず既存の解決策を調査する。

#### 3.1.1. 調査項目1: 既存のMCPサーバー化パッケージの調査 (最優先)

-   **目的:** LedgerLeap APIをMCPサーバーとして公開するための、再利用可能な既存パッケージを発見し、実装コストを最小化する。
-   **調査計画:**
    1.  Packagist, GitHub, Google等で「laravel mcp server」「laravel gemini tool」「artisan mcp」といったキーワードで検索し、関連するパッケージやライブラリをリストアップする。
    2.  `laravel/mcp` や `laravel/boost` パッケージが、他のプロジェクトでも汎用的に利用できるスタンドアロンなコンポーネントとして提供されているか、その依存関係とドキュメントを確認する。
    3.  もし再利用可能なパッケージが見つかった場合、その導入方法、設定、ツール定義の方法（OpenAPI仕様を自動で読み込む機能の有無など）を評価し、LedgerLeapへの適用可否を判断する。

#### 3.1.2. 調査項目2: `laravel-boost`のリバースエンジニアリング (フォールバックプラン)

調査項目1で再利用可能なパッケージが見つからなかった場合に限り、`laravel-boost`の実装を直接分析する。

-   **エントリーポイントの特定と起動シーケンスの解明:**
    -   `artisan boost:mcp`コマンドの`handle`メソッドを読み解き、サーバーの初期化、メインループ、終了処理のシーケンスを理解する。
    -   責務を分担しているクラス群の構造を把握する。

-   **通信プロトコルの解明:**
    -   標準入出力を介したJSONメッセージの正確なスキーマ（`id`, `method`, `params`など）を特定する。
    -   特に、ツール定義の要求/応答、ツール実行の要求/応答のメッセージ形式を解明する。

-   **ツール定義の動的生成・公開方法の解明:**
    -   `laravel-boost`がArtisanコマンド等を、`gemini` CLIが解釈可能な`function_declarations`形式に動的に変換しているロジックを解明する。

---

### 3.2. 実装フェーズ：調査結果を元に、LedgerLeapにMCPサーバー機能を実装する

調査で得られた知見に基づき、LedgerLeapに同様の機能を実装する。

#### 3.2.1. ステップ1: `artisan ledgerleap:mcp` コマンドの作成

-   `php artisan make:command LedgerLeapMcpServer`を実行し、MCPサーバーの土台となるArtisanコマンドを作成する。

#### 3.2.2. ステップ2: 通信メインループの実装

-   コマンドの`handle`メソッド内に、`laravel-boost`を参考に、標準入力からのJSONリクエストを待ち受ける無限ループを実装する。
-   受信したJSONをパースし、リクエストの種類（ツール定義要求 or ツール実行要求）を判別するディスパッチロジックを実装する。

#### 3.2.3. ステップ3: ツール定義（OpenAPI仕様）の提供ロジックの実装

-   `gemini` CLIからツール定義を要求された際に、`storage/api-docs/api-docs.json`の内容を読み込む。
-   読み込んだOpenAPI仕様を、調査フェーズで解明したプロトコル形式（`function_declarations`の配列など）に変換する。
-   変換したツール定義をJSONとして標準出力に書き出し、`gemini` CLIに応答する。

#### 3.2.4. ステップ4: ツール実行（API呼び出し）ロジックの実装

-   `gemini` CLIからツール実行リクエスト（例: `search_ledgers`）を受け取る。
-   リクエストされた関数名とパラメータを解析する。
-   関数名に対応する`LedgerService`のメソッド（例: `searchLedgersForApi`）を、DIコンテナ経由で解決し、パラメータを渡して直接呼び出す。
-   サービスの実行結果（成功または例外）を、調査フェーズで解明したプロトコル形式のJSONに変換し、標準出力に書き出して`gemini` CLIに応答する。

#### 3.2.5. ステップ5: `.gemini/settings.json`への登録とテスト

-   `.gemini/settings.json`に、`laravel-boost`の設定を参考に、`ledgerleap-api`エントリを追加する。
    ```json
    "ledgerleap-api": {
      "command": "./vendor/bin/sail",
      "args": [
        "artisan",
        "ledgerleap:mcp"
      ]
    }
    ```
-   `gemini` CLIを再起動し、プロンプトで`@ledgerleap-api`が利用可能になっていることを確認する。
-   「`@ledgerleap-api 台帳を検索して`」のようなプロンプトを入力し、LedgerLeap APIがツールとして正しく呼び出され、結果が返ってくるか、一連の動作をテストする。
