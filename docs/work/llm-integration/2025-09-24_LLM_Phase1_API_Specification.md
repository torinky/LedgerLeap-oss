# LedgerLeap LLM連携フェーズ1 API技術仕様書

**日付:** 2025年9月24日

## 1. 概要

### 1.1. 目的

本ドキュメントは、[LedgerLeap LLM連携機能 開発ロードマップ](./2025-09-23_LLM_Integration_Roadmap.md)の「フェーズ1: API基盤の構築」で定義された各APIの技術的な仕様を詳細に定義することを目的とする。

### 1.2. 関連ドキュメント

- [LedgerLeap LLM連携機能 開発ロードマップ](./2025-09-23_LLM_Integration_Roadmap.md)

## 2. 共通仕様

- **認証:** Laravel Sanctumを利用したAPIトークン認証を必須とする。リクエストヘッダーに `Authorization: Bearer <token>` を付与する。
- **エンドポイントベースURL:** `/api/v1`
- **レスポンス形式:** レスポンスはJSON形式とする。エラー発生時は、標準的なHTTPステータスコード（401, 403, 404, 422, 500など）と共に、エラー内容を示すJSONを返す。

---

## 3. API仕様詳細

### 3.1. API認証基盤 (ステップ1.1)

- **ステータス:** 実装完了 (2025-09-24)
- **目的:** 外部アプリケーション（LLMエージェント等）がLedgerLeapのAPIへ安全にアクセスするための認証メカニズムを確立する。
- **関連コンポーネント:**
    - `laravel/sanctum` パッケージ
    - `App\Models\User` モデル (`HasApiTokens` トレイト)
    - `App\Filament\Resources\UserResource` (トークン管理UI)
- **確認事項:**
    - `config/auth.php` の `api` ガードが `sanctum` を使用していること。
    - `routes/api.php` に定義されたルートが `auth:sanctum` ミドルウェアで保護されていること。
    - Filamentの管理画面から、ユーザーに紐づくAPIトークンの発行・権限設定（将来的な拡張スコープ）・失効が直感的に行えること。
- **テストケース:**
    - **Feature Test (PHPUnit):**
        - `Authorization` ヘッダーがないリクエストは `401 Unauthorized` となること。
        - 無効なトークンを使用したリクエストは `401 Unauthorized` となること。
        - 有効なトークンを使用したリクエストが成功すること（例: 認証ユーザー情報を返す `/api/user` エンドポイントへのアクセス）。
        - `UserResource` のテストで、トークンの発行と削除が正しく行えることを確認する。

### 3.2. 検索API (ステップ1.2)

- **ステータス:** 実装完了 (2025-09-24)
- **エンドポイント:** `GET /api/v1/search`
- **目的:** RAGのRetrievalを担う、高度な検索機能を提供する。
- **パラメータ:**
    - `q` (string, 任意): 全文検索キーワード。台帳の `content` および `content_attached` を対象とする。
    - `tags` (string, 任意): 絞り込み対象のタグ名をカンマ区切りで指定（例: `tag1,tag2`）。AND条件。
    - `folder_id` (integer, 任意): 指定したフォルダID配下の台帳を再帰的に検索対象とする。
    - `ledger_define_id` (integer, 任意): 指定した台帳定義IDを持つ台帳のみを検索対象とする。
    - `exclude_q` (string, 任意): 結果から除外するキーワード。
    - `exclude_tags` (string, 任意): 除外するタグ名をカンマ区切りで指定。
    - `mode` (string, 任意, デフォルト: `search`):
        - `search`: 検索結果のリストを返す。
        - `count`: 条件に一致する件数のみを返す。
    - `limit` (integer, 任意, デフォルト: 10): `mode=search` 時に取得する最大件数。
    - `offset` (integer, 任意, デフォルト: 0): `mode=search` 時にスキップする件数（ページネーション用）。
- **レスポンス (`mode=search`):**
    ```json
    {
        "data": [
            {
                "id": 1,
                "title": "台帳のタイトル",
                "content": { "...": "..." },
                "content_attached": "添付ファイルのテキスト...",
                "folder": { "id": 5, "name": "フォルダ名" },
                "tags": [ { "id": 1, "name": "tag1" } ],
                "updated_at": "YYYY-MM-DDTHH:MM:SSZ"
            }
        ],
        "meta": {
            "total": 123,
            "limit": 10,
            "offset": 0
        }
    }
    ```
- **レスポンス (`mode=count`):**
    ```json
    {
        "meta": {
            "total": 123
        }
    }
    ```
- **確認事項:**
    - 既存の `LedgerService::searchLedgers()` を改修し、上記のパラメータに対応させる必要がある。
    - 複数の絞り込み条件（`tags`, `folder_id`, `ledger_define_id`）と除外条件が、意図した通りのAND/NOT検索として機能すること。
    - 全文検索と各種条件を組み合わせた際のパフォーマンスが実用的であること。必要に応じてインデックスの追加を検討する。
- **テストケース:**
    - **Feature Test (PHPUnit):**
        - 各パラメータ（`q`, `tags`, `folder_id`, `ledger_define_id`）単体での絞り込みが正しく機能すること。
        - 複数のパラメータを組み合わせたAND検索が正しく機能すること。
        - 除外条件（`exclude_q`, `exclude_tags`）が正しく機能すること。
        - `mode=count` を指定した際に、正しい件数のみが返されること。
        - `limit` と `offset` を使ったページネーションが正しく機能すること。
        - ユーザーがアクセス権を持たないフォルダや台帳の情報は、結果に含まれないこと。

### 3.3. 台帳定義リストAPI (ステップ1.3)

- **ステータス:** 実装完了 (2025-09-24)
- **エンドポイント:** `GET /api/v1/ledger-defines`
- **目的:** 外部アプリケーションが、LedgerLeapで作成可能な台帳の種類と構造を動的に把握できるようにする。
- **パラメータ:** なし
- **レスポンス:**
    ```json
    {
        "data": [
            {
                "id": 1,
                "name": "議事録",
                "description": "会議の議事録を記録します。",
                "columns": [
                    { "id": 1, "name": "会議名", "type": "text", "options": null },
                    { "id": 2, "name": "日付", "type": "date", "options": null },
                    { "id": 3, "name": "種別", "type": "select", "options": ["定例", "臨時"] }
                ]
            }
        ]
    }
    ```
- **確認事項:**
    - カラムの `type` は、LLMが解釈しやすい汎用的な文字列（`text`, `textarea`, `date`, `number`, `select`, `checkbox`など）で表現されていること。
- **テストケース:**
    - **Feature Test (PHPUnit):**
        - システムに存在するすべてのアクティブな台帳定義が、期待される構造で返されること。
        - 非アクティブな台帳定義は結果に含まれないこと。

### 3.4. 台帳作成API (ステップ1.4)

- **ステータス:** 実装完了 (2025-09-27)
- **目的:** 外部アプリケーションからの指示に基づき、プログラム経由で新しい台帳レコードを作成する。
- **エンドポイント:** `POST /api/v1/ledgers`
- **関連コンポーネント:**
    - `App\Http\Controllers\Api\V1\LedgerController`
    - `App\Http\Requests\Api\V1\StoreLedgerRequest`
    - `App\Services\LedgerService`
    - `App\Policies\FolderPolicy`
    - `App\Models\Ledger`, `App\Models\Tag`
    - `App\Http\Resources\LedgerResource`
- **リクエストボディ:**
    ```json
    {
        "ledger_define_id": 1,
        "folder_id": 5,
        "content": {
            "1": "新しい会議の議事録",
            "2": "2025-09-24",
            "3": "定例"
        },
        "tags": ["新規プロジェクト", "重要"]
    }
    ```
    *Note: `content` オブジェクトのキーは、カラム定義のID (`columns.id`) とする。*
- **確認事項:**
    - リクエストのバリデーションは、専用の `FormRequest` クラス (`StoreLedgerApiRequest` など) で実装すること。
    - レコードの作成者 (`created_by`) は、認証されたユーザーのIDが自動的に設定されること。
    - `content` のキーは、順序に依存しないカラムIDを使用することで、定義の変更に対する堅牢性を高める。
- **テストケース:**
    - **Feature Test (PHPUnit):**
        - 必須項目（`ledger_define_id`, `folder_id`, `content`）を指定して、台帳が正しく作成されること（ステータスコード `201 Created`）。
        - タグも同時に指定して作成できること。
        - バリデーションエラー（必須項目不足、`content` のデータ型不一致など）が発生した場合、`422 Unprocessable Entity` とエラー詳細が返されること。
        - 存在しない `ledger_define_id` や `folder_id` を指定した場合、`422 Unprocessable Entity` が返されること。
        - ユーザーが書き込み権限を持たない `folder_id` を指定した場合、`403 Forbidden` が返されること。

### 3.5. OpenAPIドキュメント生成 (ステップ1.5)

- **ステータス:** 完了 (2025-09-27)
- **目的:** LedgerLeapのAPIエンドポイントに対応するOpenAPI (Swagger) JSONファイルを生成し、外部のLLMアプリケーション（MCP）がツールとして利用できるようにする。

#### 3.5.1. 実装の概要と経緯

当初の計画通り`darkaonline/l5-swagger`を導入し、アノテーションベースでのドキュメント生成を試みた。その過程でいくつかの技術的課題が発生したため、以下のように設定を最適化していった。

1.  **スキャン対象パスの最適化:**
    - 当初、スキャン対象を`app/Http/Controllers/Api/V1`に限定していたが、`@OA\Info`を記述したベースコントローラや、`@OA\Schema`を記述したリソース・リクエストクラスが読み込まれずエラーとなった。
    - この問題を解決するため、`config/l5-swagger.php`の`annotations`パスを、`app/Http/Controllers`, `app/Http/Requests`, `app/Http/Resources`の3つのディレクトリをスキャン対象とするように拡張した。

2.  **パースエラーの回避:**
    - 特定のファイル(`app/Http/Requests/Folder/UpdateRequest.php`)が原因でアノテーションのパースエラーが発生したため、`scanOptions.exclude`設定に当該ディレクトリを追加してスキャン対象から除外した。

3.  **サーバーURLの設定:**
    - `.env`ファイル経由でのホスト名設定がコマンド実行時にうまく反映されなかったため、最終的に`config/l5-swagger.php`の`constants.L5_SWAGGER_CONST_HOST`に、テナントのURL（例: `http://tenanta.localhost`）を直接書き込むことで対応した。

#### 3.5.2. アノテーション記述例

- **ベースアノテーション (`app/Http/Controllers/Controller.php`):**
    API全体の共通情報（タイトル、サーバー、認証方式）を定義。
    ```php
    /**
     * @OA\Info(version="1.0.0", title="LedgerLeap API", ...)
     * @OA\Server(url=L5_SWAGGER_CONST_HOST, ...)
     * @OA\SecurityScheme(securityScheme="sanctum", type="http", scheme="bearer", ...)
     */
    ```

- **エンドポイント定義 (`SearchController.php`など):**
    各APIエンドポイントのパス、メソッド、パラメータ、レスポンス等を定義。
    ```php
    /**
     * @OA\Get(
     *     path="/api/v1/search",
     *     summary="Search ledgers",
     *     tags={"Search"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="q", in="query", ...),
     *     @OA\Response(response=200, description="Successful operation", ...)
     * )
     */
    ```

- **スキーマ定義 (`LedgerResource.php`など):**
    リクエストボディやレスポンスで使われるデータ構造を、再利用可能なスキーマとして定義。
    ```php
    /**
     * @OA\Schema(
     *     schema="LedgerResource",
     *     type="object",
     *     title="Ledger Resource",
     *     @OA\Property(property="id", type="integer", ...),
     *     @OA\Property(property="define", type="object", ...)
     * )
     */
    ```
    - **[Tips]** デバッグの過程で、`@OA\Property`の`example`にオブジェクト形式 (`{"key": "value"}`) を直接記述するとパーサーがエラーを起こすことがあったため、`example`を記述しないか、JSONエンコードされた文字列として記述することで回避した。

#### 3.5.3. 公開エンドポイントの実装

生成された`api-docs.json`を外部から取得できるよう、`routes/api.php`に認証不要のルート`/api/openapi.json`を追加した。この際、テナントコンテキスト下でも正しく`storage`ディレクトリのパスを解決できるよう、`Storage`ファサードの代わりに`storage_path()`ヘルパーと`file_exists()`を組み合わせて実装した。

```php
// in routes/api.php
Route::get('/openapi.json', function () {
    $path = storage_path('api-docs/api-docs.json');
    if (!file_exists($path)) {
        abort(404, 'API documentation file not found.');
    }
    return response()->file($path, ['Content-Type' => 'application/json']);
});
```

### 3.6. Gemini (MCP) との連携 (ステップ1.6)

- **ステータス:** 連携完了 (2025-09-27)
- **目的:** 生成・公開したOpenAPIドキュメントを利用し、Gemini自身がLedgerLeap APIをツールとして認識・利用することで、自然言語による高度な台帳操作を実現する。

#### 3.6.1. Geminiへのツール登録

GeminiがLedgerLeap APIをツールとして利用するための設定は以下の通りです。この設定により、Geminiは指定されたURLからAPI仕様を読み込み、認証情報を使って各エンドポイントを呼び出すことが可能になります。

- **`gemini-cli-tool-config.json` の設定例:**
    ```json
    {
      "tool_declarations": [
        {
          "openapi_spec": {
            "url": "http://tenanta.localhost/api/openapi.json"
          },
          "auth_config": {
            "api_key_config": {
              "name": "authorization",
              "key": "Bearer <発行したAPIトークンをここに貼り付け>"
            }
          }
        }
      ]
    }
    ```
    *   **`openapi_spec.url`**: ステップ3.5で作成した、OpenAPIドキュメントを返すURLを指定します。
    *   **`auth_config`**: Laravel SanctumのBearerトークン認証を設定します。
        *   `name`: HTTPヘッダー名 `Authorization` を指定します。
        *   `key`: ヘッダーに設定する値の**プレフィックス**である `Bearer ` を指定し、その後ろに発行したAPIトークンを続けます。

#### 3.6.2. 連携後の実行シナリオ例

ツール登録後、Geminiはユーザーの自然言語による指示を解釈し、自律的にAPIを呼び出してタスクを実行します。

-   **シナリオ1: 検索機能 (RAGのRetrieval部分)**
    -   **ユーザー:** `「2025年のプロジェクト計画」に関する台帳を検索して、概要を教えて。`
    -   **Geminiの動作:**
        1.  プロンプトを解釈し、`search` APIを呼び出すべきだと判断します。
        2.  内部的に `GET /api/v1/search?q=2025年のプロジェクト計画` APIコールを実行します。
        3.  LedgerLeapから返されたJSONレスポンス（台帳のリスト）を解析します。
        4.  取得した情報に基づき、「台帳『〇〇』が見つかりました。その概要は...です。」のように、要約した回答を生成してユーザーに提示します。

-   **シナリオ2: 作成機能**
    -   **ユーザー:** `フォルダIDが10番に「日報」を作成したい。内容は「今日はAPIの連携テストを実施した。」で、タグは「テスト」と「API」を付けて。`
    -   **Geminiの動作:**
        1.  プロンプトを解釈し、`createLedger` APIを呼び出すべきだと判断します。
        2.  （**高度な動作**）まず `ledger-defines` APIを呼び出して「日報」のIDを取得します。
        3.  ユーザーの指示と取得したIDから、`POST /api/v1/ledgers` のリクエストボディを組み立てます。
        4.  APIコールを実行し、LedgerLeapにレコードを登録します。
        5.  成功レスポンス（HTTP 201）を受け取り、「ID: xxx で日報を作成しました。」のように結果をユーザーに報告します。

-   **シナリオ3: 曖昧な指示に対する対話の継続**
    -   **ユーザー:** `議事録を作って。`
    -   **Geminiの動作:**
        1.  `createLedger` APIを呼び出すには、`ledger_define_id`, `folder_id`, `content`などの情報が不足していると判断します。
        2.  APIのスキーマ定義に基づき、不足している情報を特定します。
        3.  「議事録を作成しますね。会議の名称と、どのフォルダに保存しますか？」のように、タスク遂行に必要な情報をユーザーに追加で質問します。
