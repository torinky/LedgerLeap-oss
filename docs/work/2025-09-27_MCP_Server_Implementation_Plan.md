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

### 3.1. 調査フェーズ：公式パッケージ `laravel/mcp` の発見と方針転換

`laravel-boost`の`artisan boost:mcp`コマンドの存在から、LaravelコミュニティにAPIをMCPサーバー化するための共通のアプローチが存在する可能性を想定し、既存の解決策を調査した。

#### 3.1.1. 調査結果：`laravel/mcp` パッケージの特定

-   **結論:** 調査の結果、Laravel公式から **`laravel/mcp`** という、まさに今回の目的（LaravelアプリケーションをMCPサーバー化する）に合致するパッケージが提供されていることが判明した。
-   **機能概要:**
    -   AIクライアント（Gemini CLIなど）がLaravelアプリケーションと対話するための標準的なプロトコルを提供する。
    -   アプリケーションの機能を「ツール」としてAIに公開する仕組みを持つ。
    -   通信方法はStdio（標準入出力）をサポートしており、`artisan`コマンドとしてローカルサーバーを起動する機能も備わっている。
-   **`laravel-boost`との関連:** `laravel/mcp`のドキュメントには「ローカルサーバーは、Laravel BoostのようなローカルAIアシスタント統合の構築に最適」との記述があり、`laravel-boost`自体がこのパッケージを利用して実装されている可能性が極めて高い。

#### 3.1.2. 方針転換：リバースエンジニアリングから公式パッケージ活用へ

-   当初の計画にあった「`laravel-boost`のリバースエンジニアリング」は、公式パッケージの発見により**不要と判断**する。
-   今後の実装は、**`laravel/mcp` パッケージを導入し、その機能を利用してMCPサーバーを構築する**方針へと転換する。これにより、より効率的かつ標準に準拠した実装が見込める。

---

### 3.2. 実装フェーズ：`laravel/mcp` を利用したMCPサーバー機能の実装

`laravel/mcp`パッケージの導入と公式ドキュメントの精査結果を踏まえ、実装計画を以下のように再定義する。

#### 3.2.1. ステップ1: `laravel/mcp` パッケージのインストール

-   `composer require laravel/mcp` を実行し、パッケージをプロジェクトに導入する。
-   `php artisan vendor:publish --tag=ai-routes` を実行し、MCPサーバーの定義ファイル `routes/ai.php` を公開する。

#### 3.2.2. ステップ2: MCPサーバーの定義とArtisanコマンドの作成

-   `php artisan make:mcp-server LedgerLeapServer` を実行し、MCPサーバーの定義クラス (`app/Mcp/Servers/LedgerLeapServer.php`) を作成する。
-   `routes/ai.php` に、作成した`LedgerLeapServer`をローカルサーバー（Artisanコマンド）として登録する。このとき、コマンド名を `ledgerleap:mcp` に設定する。

#### 3.2.3. ステップ3: 各APIエンドポイントに対応するMCPツールの作成

公式ドキュメントを精査した結果、`laravel/mcp`にはOpenAPI仕様を自動的に解釈してツールを生成する機能は存在しないことが判明した。そのため、**各APIエンドポイントに対応するMCPツールを個別に作成する**方針へと変更する。

1.  **検索ツール (`SearchLedgersTool`) の作成:**
    -   `php artisan make:mcp-tool SearchLedgersTool` を実行。
    -   `SearchLedgersTool`クラスで、`search` APIが受け取るパラメータ（`q`, `tags`, `folder_id`等）を`JsonSchema`を用いて定義し、バリデーションルールを設定する。
    -   `handle`メソッド内で、DIコンテナ経由で`LedgerService`をインジェクトし、受け取った引数を渡して`searchLedgersForApi()`メソッドを呼び出す。
    -   `LedgerService`からの戻り値を、`Laravel\Mcp\Response`オブジェクトでラップして返す。

2.  **台帳定義リスト取得ツール (`GetLedgerDefinesTool`) の作成:**
    -   `php artisan make:mcp-tool GetLedgerDefinesTool` を実行。
    -   このツールは引数を取らないため、スキーマ定義は不要。
    -   `handle`メソッド内で、`LedgerDefine`モデルを直接、または`LedgerDefineService`（もし存在すれば）経由で全件取得する。
    -   取得した結果を`Response`オブジェクトでラップして返す。

3.  **台帳作成ツール (`CreateLedgerTool`) の作成:**
    -   `php artisan make:mcp-tool CreateLedgerTool` を実行。
    -   `createLedger` APIが受け取るリクエストボディ（`ledger_define_id`, `folder_id`, `content`, `tags`）の構造を`JsonSchema`で厳密に定義する。
    -   `handle`メソッド内で、`LedgerService`の`createLedger()`メソッドを呼び出す。この際、認証ユーザーの情報を取得し、`created_by`として渡す必要がある。MCPリクエストのコンテキストから認証ユーザーを取得する方法をドキュメントで確認し、実装する。
    -   作成されたリソース情報を`Response`オブジェクトでラップして返す。

4.  **作成したツールを`LedgerLeapServer`に登録:**
    -   `app/Mcp/Servers/LedgerLeapServer.php`の`$tools`プロパティに、上記で作成した3つのツールクラスを登録する。

#### 3.2.4. ステップ4: `.gemini/settings.json`への登録とテスト

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
-   「`@ledgerleap-api qをキーワードに台帳を検索して`」のようなプロンプトを入力し、`SearchLedgersTool`が正しく呼び出され、結果が返ってくるか、一連の動作をテストする。
-   同様に、台帳定義の取得、台帳の作成についても、自然言語での指示によって対応するツールが正しく実行されることを確認する。
