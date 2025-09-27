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

- **ステータス:** 計画中
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

- **ステータス:** 計画中
- **目的:** LedgerLeapのAPIエンドポイントに対応するOpenAPI (Swagger) JSONファイルを生成し、外部のLLMアプリケーション（MCP）がツールとして利用できるようにする。
- **関連コンポーネント:**
    - `darkaonline/l5-swagger` パッケージ
    - `app/Http/Controllers/Controller.php` (ベースとなるアノテーション)
    - `app/Http/Controllers/Api/V1/*.php` (各エンドポイントのアノテーション)

- **実装計画詳細:**
    1.  **`l5-swagger` のインストールと設定:**
        - `composer require "darkaonline/l5-swagger"` を実行してパッケージをインストールする。
        - `php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"` を実行して設定ファイルを公開する (`config/l5-swagger.php`)。
        - `config/l5-swagger.php` を編集し、以下を設定する。
            - `paths.docs`: `storage/api-docs`
            - `documentations.default.api.title`: "LedgerLeap API"
            - `documentations.default.scan`: `app/Http/Controllers/Api/V1`

    2.  **ベースアノテーションの追加 (`Controller.php`):**
        - `app/Http/Controllers/Controller.php` に、API全体の情報、サーバー、認証方式を定義する。これにより、各コントローラでの記述をDRYに保つ。
        ```php
        /**
         * @OA\Info(
         *      version="1.0.0",
         *      title="LedgerLeap API",
         *      description="LedgerLeap API for LLM integration"
         * )
         * @OA\Server(
         *      url=L5_SWAGGER_CONST_HOST,
         *      description="LedgerLeap API Server"
         * )
         * @OA\SecurityScheme(
         *      securityScheme="sanctum",
         *      type="http",
         *      scheme="bearer",
         *      bearerFormat="JWT",
         *      description="Enter token in format (Bearer <token>)"
         * )
         */
        ```

    3.  **各コントローラへのアノテーション追加 (具体例):**
        - **検索API (`SearchController`):** `@OA\Get` を使用し、クエリパラメータを `@OA\Parameter` で定義する。
        ```php
        /**
         * @OA\Get(
         *     path="/api/v1/search",
         *     summary="Search ledgers",
         *     tags={"Search"},
         *     security={{"sanctum":{}}},
         *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
         *     @OA\Parameter(name="tags", in="query", required=false, @OA\Schema(type="string")),
         *     @OA\Response(response=200, description="Successful operation", @OA\JsonContent(ref="#/components/schemas/LedgerSearchResponse")),
         *     @OA\Response(response=401, description="Unauthenticated")
         * )
         */
        ```
        - **台帳作成API (`LedgerController`):** `@OA\Post` を使用し、リクエストボディを `@OA\RequestBody` で定義する。
        ```php
        /**
         * @OA\Post(
         *     path="/api/v1/ledgers",
         *     summary="Create a new ledger",
         *     tags={"Ledger"},
         *     security={{"sanctum":{}}},
         *     @OA\RequestBody(
         *         required=true,
         *         @OA\JsonContent(ref="#/components/schemas/StoreLedgerRequest")
         *     ),
         *     @OA\Response(response=201, description="Successfully created", @OA\JsonContent(ref="#/components/schemas/LedgerResource")),
         *     @OA\Response(response=422, description="Validation error")
         * )
         */
        ```

    4.  **再利用可能なスキーマの定義:**
        - APIで頻繁に使用されるリクエストボディやレスポンスの構造は、`app/Http/Requests` や `app/Http/Resources` 内に `@OA\Schema` として定義することで、アノテーションの再利用性を高める。
        ```php
        // In a dedicated file or a relevant class like a FormRequest
        /**
         * @OA\Schema(
         *     schema="StoreLedgerRequest",
         *     type="object",
         *     required={"ledger_define_id", "folder_id", "content"},
         *     @OA\Property(property="ledger_define_id", type="integer", example=1),
         *     @OA\Property(property="folder_id", type="integer", example=5),
         *     @OA\Property(property="content", type="object", example={"1": "Title", "2": "2025-09-25"}),
         *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"tag1", "tag2"})
         * )
         */
        ```

- **テスト・機能確認方法:**
    1.  **ドキュメント生成テスト (ローカル/CI):**
        - コマンド `vendor/bin/sail artisan l5-swagger:generate` を実行し、エラーなく完了することを確認する。
        - 上記コマンド実行後、`git status` を実行し、`storage/api-docs/api-docs.json` に差分がないことを確認する。差分がある場合は、アノテーションの修正漏れやドキュメントの更新漏れを示唆するため、コミット前に修正を必須とする。
    2.  **OpenAPI仕様検証 (ローカル/CI):**
        - `api-docs.json` がValidなOpenAPI仕様であることを、リンターツール（例: `speccy lint` や `spectral lint`）を使って検証する。
        ```bash
        # 例: spectralをDockerで実行
        docker run --rm -v $(pwd)/storage/api-docs:/defs stoplight/spectral lint "/defs/api-docs.json"
        ```
    3.  **Swagger UIによる手動確認 (ローカル):**
        - `http://localhost/api/documentation` にアクセスし、以下の点を確認する。
            - 全てのAPIエンドポイントが意図通りに表示されているか。
            - 各エンドポイントの`summary`, `description`, `parameters`, `requestBody`, `responses` が正しく表示されているか。
            - 右上の "Authorize" ボタンから `Bearer <token>` 形式でAPIトークンを設定できるか。
            - "Try it out" 機能を用いて、実際にAPIをいくつか実行し、正常なレスポンス (`200`, `201`) およびエラーレスポンス (`401`, `422`) が返ってくることを確認する。
    4.  **PHPUnitによる自動テスト (CI):**
        - `l5-swagger:generate` コマンドの実行をテストケースに含め、コマンドが成功することをアサートする。
        - 生成された `api-docs.json` を読み込み、特定のエンドポイント（例: `/api/v1/search`）やスキーマ定義（例: `LedgerResource`）が存在することを `assertArrayHasKey` などでアサートする。これにより、主要なAPI定義が誤って削除されることを防ぐ。

- **考慮事項:**
    - **アノテーションの詳細度:** LLMがAPIを正しく解釈し、適切なリクエストを生成できるよう、パラメータやレスポンスの型、必須/任意、説明などを可能な限り詳細に記述する。特にEnumや固定値を持つパラメータは、`enum` や `example` を活用して明記する。
    - **メンテナンス性:** APIの仕様変更が発生した場合は、必ず対応するアノテーションも更新し、`l5-swagger:generate` を再実行する運用を徹底する。CI/CDパイプラインにドキュメント生成と検証ステップを組み込むことを推奨する。
    - **既存仕様との整合性:** このAPI仕様書に記載されている内容と、生成されるOpenAPIドキュメントの内容に齟齬がないように維持する。

### 3.6. 外部MCPとの連携 (ステップ1.5以降)

- **目的:** 生成したOpenAPIドキュメントを利用して、Gemini CLIのような外部LLMアプリケーション（MCP）とLedgerLeap APIを連携させ、自然言語による操作を実現する。

- **連携設定方法 (Gemini CLI / Google AI Studioを想定):**
    1.  **OpenAPIドキュメントの提供:**
        - **方法A (推奨: URL提供):** `api-docs.json` をWebからアクセス可能なURLで提供する。そのために、`routes/api.php` にドキュメントを返すだけのシンプルなルートを追加し、`l5-swagger` の設定と合わせて `/api/openapi.json` のようなエンドポイントでアクセスできるように構成する。
        - **方法B (ファイルアップロード):** Google AI StudioやDifyのようなUIを持つツールの場合、ローカルで生成した `storage/api-docs/api-docs.json` ファイルを管理画面から直接アップロードする。
    2.  **認証情報の設定:**
        - LedgerLeapの管理者画面から、MCP連携専用のAPIトークンを発行する。
        - MCPを実行する環境で、取得したAPIトークンを環境変数（例: `LEDGERLEAP_API_TOKEN`）として設定する。
        - MCP側のツール設定で、OpenAPI定義の `securityScheme` (`sanctum`) に基づき、リクエスト時に `Authorization: Bearer ${LEDGERLEAP_API_TOKEN}` ヘッダーが付与されるように設定する。この具体的な設定方法はMCPの仕様に依存する。
    3.  **ツール（Function）の登録:**
        - MCPのインターフェース（Google AI Studio, Vertex AI, Difyなど）で、「新しいツールを追加」といった操作を行う。
        - 提供方法としてURLまたはファイルアップロードを選択し、OpenAPIドキュメントを指定する。
        - MCPがドキュメントをパースし、`search`, `createLedger` のような呼び出し可能な関数（ツール）として認識したことを確認する。

-   **接続後の試験方法 (シナリオテスト):**
    - **準備:**
        - テスト用のAPIトークンを発行し、MCPの環境変数に設定する。
        - テスト用のデータ（台帳、フォルダ、タグ）をLedgerLeap内にいくつか登録しておく。
        - MCPのデバッグモードやVERBOSEモードを有効にし、LLMがどのツールをどのようなパラメータで呼び出そうとしているかがコンソールに表示されるようにする。
    - **テストシナリオ1: 検索機能 (RAGのRetrieval部分)**
        - **プロンプト:** `「2025年のプロジェクト計画」に関する台帳を検索して、タイトルと更新日時を教えて。`
        - **期待される動作:**
            1.  LLMがプロンプトを解釈し、`search` APIを呼び出すべきだと判断する。
            2.  `GET /api/v1/search?q=2025年のプロジェクト計画` のようなAPIコールが実行される。
            3.  LedgerLeapが検索結果のJSONを返す。
            4.  LLMがJSONレスポンスを解釈し、「台帳『〇〇』が見つかりました。最終更新は...です。」のように自然言語で回答する。
        - **確認ポイント:** MCPのログで、意図した通りのAPIリクエストが送信されていること。
    - **テストシナリオ2: 作成機能**
        - **プロンプト:** `台帳の種類が「日報」で、フォルダIDが10番の台帳を作成したい。内容は「今日はOpenAPIの連携テストを実施した。」で、タグは「テスト」と「API」を付けて。`
        - **期待される動作:**
            1.  LLMがプロンプトを解釈し、`createLedger` APIを呼び出すべきだと判断する。（事前に `ledger-defines` APIを呼び出して「日報」のIDを確認する、より高度な挙動も考えられる）
            2.  仕様に沿ったリクエストボディを持つ `POST /api/v1/ledgers` のAPIコールが実行される。
            3.  LedgerLeapが `201 Created` と作成されたリソースを返す。
            4.  LLMがレスポンスを解釈し、「ID: xxx で日報を作成しました。」のように報告する。
        - **確認ポイント:** MCPのログで正しいリクエストボディが送信されていること。LedgerLeapのUIまたはDBで、実際にデータが作成されていること。
    - **テストシナリオ3: 曖昧な指示に対する対話の継続**
        - **プロンプト:** `議事録を作って。`
        - **期待される動作:**
            1.  LLMが、台帳作成に必要な情報（`ledger_define_id`, `folder_id`, `content`など）が不足していると判断する。
            2.  「議事録を作成しますね。会議の名称と、どのフォルダに保存しますか？」のように、APIを呼び出すために必要な情報をユーザーに追加で質問する。
        - **確認ポイント:** LLMが自律的に対話を継続し、APIを呼び出すために必要な情報を収集しようとすること。
