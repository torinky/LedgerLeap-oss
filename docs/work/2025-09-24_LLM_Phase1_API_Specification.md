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

- **エンドポイント:** `GET /api/v1/search`
- **目的:** 外部アプリケーションがLedgerLeap内の情報を柔軟かつ強力に検索（Retrieval）できるようにする。RAGの精度を左右する重要なAPI。
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

- **エンドポイント:** `POST /api/v1/ledgers`
- **目的:** 外部アプリケーションからの指示に基づき、プログラム経由で新しい台帳レコードを作成する。
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
        - 存在しない `ledger_define_id` や `folder_id` を指定した場合、`404 Not Found` が返されること。
        - ユーザーが書き込み権限を持たない `folder_id` を指定した場合、`403 Forbidden` が返されること。
