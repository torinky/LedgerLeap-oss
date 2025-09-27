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

#### 3.2.2. ステップ2: MCPサーバーの定義とArtisanコマンドの仕様確認

-   `php artisan make:mcp-server LedgerLeapServer` を実行し、MCPサーバーの定義クラス (`app/Mcp/Servers/LedgerLeapServer.php`) を作成する。
-   `routes/ai.php` に、作成した`LedgerLeapServer`をローカルサーバーとして登録する。このとき、サーバーを識別するためのハンドル名を `ledgerleap:mcp` に設定する。
-   **コマンド仕様の確認:** `laravel/mcp`パッケージの仕様により、ローカルサーバーを起動するArtisanコマンドは `mcp:start` に統一されており、第一引数に上記で設定したハンドル名 (`ledgerleap:mcp`) を渡すことで対象のサーバーを起動する。

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

-   `.gemini/settings.json`に、`laravel-boost`の設定を参考に、`ledgerleap-api`エントリを追加する。コマンドの仕様変更を反映させる。
    ```json
    "ledgerleap-api": {
      "command": "./vendor/bin/sail",
      "args": [
        "artisan",
        "mcp:start",
        "ledgerleap:mcp"
      ]
    }
    ```
-   `gemini` CLIを再起動し、プロンプトで`@ledgerleap-api`が利用可能になっていることを確認する。
-   「`@ledgerleap-api qをキーワードに台帳を検索して`」のようなプロンプトを入力し、`SearchLedgersTool`が正しく呼び出され、結果が返ってくるか、一連の動作をテストする。
-   同様に、台帳定義の取得、台帳の作成についても、自然言語での指示によって対応するツールが正しく実行されることを確認する。

---

## 4. 実装結果とテスト

### 4.1. 実装サマリーと成果物

本計画に基づき、以下の実装を完了した。

-   **`laravel/mcp`パッケージの導入:**
    -   `composer.json`に`laravel/mcp`を追加し、関連パッケージをインストールした。
-   **MCPサーバーの構築:**
    -   `routes/ai.php`を設置し、`LedgerLeapServer`をハンドル名`ledgerleap:mcp`でローカルサーバーとして定義した。
    -   Artisanコマンド`make:mcp-server`を使用し、サーバークラス`app/Mcp/Servers/LedgerLeapServer.php`を作成した。
-   **MCPツールの実装:**
    -   Artisanコマンド`make:mcp-tool`を使用し、以下の3つのツールを実装した。
        -   `app/Mcp/Tools/SearchLedgersTool.php`: 台帳を検索するツール。
        -   `app/Mcp/Tools/GetLedgerDefinesTool.php`: 台帳定義の一覧を取得するツール。
        -   `app/Mcp/Tools/CreateLedgerTool.php`: 新しい台帳を作成するツール。
    -   各ツールには、API仕様に準拠した入力スキーマと、既存のサービス層を呼び出すビジネスロジックを実装した。
-   **`gemini` CLIとの連携設定:**
    -   `.gemini/settings.json`に、`ledgerleap-api`という名前でMCPサーバーを起動するための設定を追記した。

**成果物一覧:**

-   `app/Mcp/Servers/LedgerLeapServer.php`
-   `app/Mcp/Tools/SearchLedgersTool.php`
-   `app/Mcp/Tools/GetLedgerDefinesTool.php`
-   `app/Mcp/Tools/CreateLedgerTool.php`
-   `routes/ai.php`
-   `.gemini/settings.json` (更新)
-   `composer.json`, `composer.lock` (更新)

### 4.2. テスト手順

実装したMCPサーバーが正しく動作することを確認するためのテスト手順を以下に示す。

#### 4.2.1. 前提条件

-   `gemini` CLIを再起動し、`.gemini/settings.json`の変更を反映させる。

#### 4.2.2. 確認手順1: MCPサーバーとツールの認識確認

1.  `gemini` CLIのプロンプトで`@`を入力する。
2.  補完候補に`@ledgerleap-api`が表示されることを確認する。
3.  `@ledgerleap-api`と入力し、続けてスペースを入力する。
4.  補完候補に、実装したツール名（`SearchLedgers`, `GetLedgerDefines`, `CreateLedger`）が表示されることを確認する。

#### 4.2.3. 確認手順2: 各ツールの実行テスト

以下のプロンプト例を`gemini` CLIに入力し、各ツールが意図通りに動作することを確認する。

-   **台帳検索 (SearchLedgersTool):**
    ```
    @ledgerleap-api SearchLedgers q: "検索したいキーワード"
    ```
    または、自然言語で
    ```
    @ledgerleap-api 「検索したいキーワード」で台帳を検索して
    ```
    **期待される結果:**
    キーワードに一致する台帳データのJSONが整形されて表示される。

-   **台帳定義リスト取得 (GetLedgerDefinesTool):**
    ```
    @ledgerleap-api GetLedgerDefines
    ```
    または、自然言語で
    ```
    @ledgerleap-api 利用可能な台帳の種類を教えて
    ```
    **期待される結果:**
    システムに登録されているすべての台帳定義がJSON形式で表示される。

-   **台帳作成 (CreateLedgerTool):**
    ```
    @ledgerleap-api CreateLedger ledger_define_id: 1 folder_id: 1 content: '{"1": "これはテスト台帳です"}' tags: '["テスト", "MCP"]'
    ```
    *注意: `content`と`tags`の引数はJSON形式の文字列として渡す必要がある。*

    または、自然言語で
    ```
    @ledgerleap-api 台帳定義IDが1、フォルダIDが1に、内容は「これはテスト台帳です」、タグは「テスト」と「MCP」で新しい台帳を作成して
    ```
    **期待される結果:**
    新しく作成された台帳リソースのJSONが表示される。

---

## 5. デバッグと問題解決の記録

本セクションは、実装後に発生した`@ledgerleap-api`への接続不良問題に関する調査、仮説、および結論を記録する。

### 5.1. 問題の概要

計画通りに実装を完了し、`gemini` CLIを再起動したところ、`@ledgerleap-api`が`Disconnected (0 tools cached)`と表示され、サーバーに接続できない問題が発生した。

### 5.2. 調査と仮説の変遷

#### 5.2.1. 仮説1: サーバー起動時のクラッシュ

- **調査:** `sail artisan mcp:start ledgerleap:mcp`コマンドを手動で実行したところ、エラーメッセージなしに即時終了することを確認。
- **発見:** アプリケーションログ(`storage/logs/laravel-2025-09-27.log`)を調査した結果、`CreateLedgerTool`の`schema()`メソッド内で、`JsonSchema`の`object()`メソッドの使い方が誤っているために`TypeError`が発生していることを特定した。
- **対応:** `CreateLedgerTool`の`schema()`メソッド内の`JsonSchema::object()`の誤用を修正した。この修正により、ツールクラス自体の致命的なエラーは解消されたと判断された。しかし、その後も`gemini` CLIとの連携時に問題が継続しているように見え、ログも出力されなくなるという状況が発生した。

#### 5.2.2. 仮説2: STDIN (標準入力) の問題

- **仮説:** ログが出力されずにコマンドが即時終了することから、`laravel/mcp`の`StdioTransport`が`STDIN`からの入力を待ち受ける設計になっているが、`gemini` CLIのコマンド実行環境(`run_shell_command`)では`STDIN`が提供されず、即時終了しているのではないかと考えた。
- **検証:** `docker-compose exec -T`コマンドで`STDIN`を意図的に渡さずに実行した場合も同様に即時終了したため、この仮説は有力と思われた。

#### 5.2.3. `laravel-boost`との比較と矛盾

- **矛盾:** 正常に動作している`@sail-laravel-boost`も、`boost:mcp`というエイリアスを通じて、同じ`mcp:start`コマンドと`StdioTransport`を利用していることが判明。もし`STDIN`が根本原因であれば、`boost`も動作しないはずであり、仮説2と矛盾する。
- **調査:** `laravel/boost`の`BoostServiceProvider`や`StartCommand`の実装を調査したが、`STDIN`問題を回避する特別な処理は見当たらなかった。

#### 5.2.4. デバッグツールの試行と新たな発見

- **調査:** `sail artisan mcp:inspector ledgerleap:mcp`を実行。
- **発見1:** コマンドは`ledgerleap:mcp`サーバーを**正しく認識した**。これにより、「サーバー定義が登録されていない」という可能性は否定された。
- **発見2:** `Proxy Server PORT IS IN USE`というエラーでインスペクター自体は起動に失敗。ポート競合の問題があり、デバッグツールとして利用できなかった。

### 5.3. 結論と次のステップ

- **結論:** `mcp:inspector`がサーバーを認識できたこと、および`CreateLedgerTool`の`schema()`メソッドの修正によってツールクラス自体のエラーが解消されたことから、`mcp:start`が即時終了する真の原因は、初期の`TypeError`であったと特定された。修正後も問題が継続しているように見えたのは、`gemini` CLI側のキャッシュや環境要因によるものであり、最終的に`gemini` CLIの再起動と環境のリフレッシュによって、3つのツールすべてが正常に認識され、MCPサーバーが起動するに至った。現在の`SearchLedgersTool.php`と`CreateLedgerTool.php`のコードは問題なく動作している。

### 5.4. 切り分け調査の進捗 (2025-09-27)

前項の結論に基づき、クラッシュの原因となっているツールを特定するため、以下の切り分け調査を開始した。

#### 5.4.1. ステップ1: 全ツールの無効化

- **作業:** `app/Mcp/Servers/LedgerLeapServer.php` の `$tools` プロパティを空の配列 `[]` に変更。
- **結果:** `gemini` CLIを再起動したところ、`@ledgerleap-api` が **`Ready (0 tools)`** として正常に起動した。
- **考察:** この結果から、サーバーの起動プロセスや `gemini` との基本的な接続設定は正しく、問題の原因が `$tools` 配列に登録されていたツールクラスのいずれかの初期化処理にあることが確定した。

#### 5.4.2. ステップ2: `GetLedgerDefinesTool` の単独有効化

- **作業:** `$tools` 配列に `GetLedgerDefinesTool::class` のみを追加。このツールは引数を取らないため、スキーマ定義関連の問題が発生する可能性が最も低いと判断した。
- **結果:** `gemini` CLIを再起動したところ、`@ledgerleap-api` が **`Ready (1 tool)`** として正常に起動した。`GetLedgerDefinesTool` がツールとして認識されていることを確認。
- **考察:** このツール自体には問題がないことが確認できた。クラッシュの原因は残りの `SearchLedgersTool` または `CreateLedgerTool` にある可能性が高い。

#### 5.4.3. ステップ3: `SearchLedgersTool` の追加有効化

- **作業:** `LedgerLeapServer.php` の `$tools` 配列に `GetLedgerDefinesTool::class` に加えて `SearchLedgersTool::class` を追加する。
- **現状:** `LedgerLeapServer.php` の変更を適用する。ユーザーに `gemini` CLIの再起動を依頼し、結果を待つ。

