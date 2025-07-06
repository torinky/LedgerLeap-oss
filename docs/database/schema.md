# LedgerLeap データベーススキーマ概要

## 概要説明
LedgerLeapが使用する主要なデータベーステーブルの構造とテーブル間の関連についての概要を記述します。このドキュメントは、システム全体のデータ構造を理解するための一助となることを目的としています。

## 主要テーブルER図 (Mermaid.js)

```mermaid
erDiagram
    users {
        int id PK
        string name
        string email
        timestamp email_verified_at
        string password
        string remember_token
        datetime created_at
        datetime updated_at
    }

    organizations {
        int id PK
        string name
        text description
        int parent_id FK
        datetime created_at
        datetime updated_at
    }

    user_organizations {
        int user_id PK, FK
        int organization_id PK, FK
        boolean is_primary
    }

    folders {
        int id PK
        string title
        int parent_id FK
        int creator_id FK
        int modifier_id FK
        datetime created_at
        datetime updated_at
    }

    ledger_defines {
        int id PK
        string title
        text column_define JSON
        int folder_id FK
        int creator_id FK
        int modifier_id FK
        boolean workflow_enabled
        datetime created_at
        datetime updated_at
    }

    ledgers {
        int id PK
        int ledger_define_id FK
        text content JSON
        text content_attached JSON
        string status
        int creator_id FK
        int modifier_id FK
        int latest_diff_id FK
        int version
        datetime created_at
        datetime updated_at
    }

    ledger_diffs {
        int id PK
        int ledger_id FK
        text content JSON
        text column_define JSON
        int ledger_define_id FK
        string status
        int creator_id FK
        int modifier_id FK
        int inspector_id FK
        int approver_id FK
        datetime requested_at
        datetime inspected_at
        datetime approved_at
        datetime returned_at
        text comments
        int version
        datetime created_at
        datetime updated_at
    }

    roles {
        int id PK
        string name
        string guard_name
        text description
        datetime created_at
        datetime updated_at
    }

    permissions {
        int id PK
        string name
        string guard_name
        text description
        datetime created_at
        datetime updated_at
    }

    model_has_roles {
        int role_id PK, FK
        string model_type PK
        bigint model_id PK
    }

    role_has_permissions {
        int permission_id PK, FK
        int role_id PK, FK
    }

    role_folder_permissions {
        int id PK
        int role_id FK
        int folder_id FK
        string permission
        int notification_type_id FK
    }

    tags {
        int id PK
        string name
        string slug
        datetime created_at
        datetime updated_at
    }

    taggables {
        int tag_id PK, FK
        string taggable_type PK
        bigint taggable_id PK
    }

    attached_files {
        int id PK
        int ledger_id FK
        string file_path
        string file_name
        string mime_type
        bigint size
        datetime created_at
        datetime updated_at
    }

    notifications {
        string id PK
        string type
        morphs notifiable
        text data
        datetime read_at
        datetime created_at
        datetime updated_at
    }

    notification_types {
        int id PK
        string name
        string description
    }

    jobs {
        bigint id PK
        string queue
        text payload
        tinyint attempts
        int reserved_at
        int available_at
        int created_at
    }

    job_batches {
        string id PK
        string name
        int total_jobs
        int pending_jobs
        int failed_jobs
        text failed_job_ids
        text options
        int cancelled_at
        int created_at
        int finished_at
    }

    activity_log {
        bigint id PK
        string log_name
        text description
        morphs subject
        string event
        morphs causer
        text properties JSON
        uuid batch_uuid
        datetime created_at
        datetime updated_at
    }

    users ||--o{ user_organizations : "has"
    organizations ||--o{ user_organizations : "has"
    organizations ||--o{ organizations : "parent"
    users ||--o{ folders : "created"
    users ||--o{ folders : "modified"
    folders ||--o{ folders : "parent"
    folders ||--o{ ledger_defines : "contains"
    users ||--o{ ledger_defines : "created"
    users ||--o{ ledger_defines : "modified"
    ledger_defines ||--o{ ledgers : "defines"
    users ||--o{ ledgers : "created"
    users ||--o{ ledgers : "modified"
    ledgers ||--o{ ledger_diffs : "history"
    ledgers ||--o{ attached_files : "has"
    ledger_defines ||--o{ ledger_diffs : "defined_by"
    users ||--o{ ledger_diffs : "created_by_user"
    users ||--o{ ledger_diffs : "modified_by_user"
    users ||--o{ ledger_diffs : "inspected_by_user"
    users ||--o{ ledger_diffs : "approved_by_user"
    ledgers ||--o{ ledger_diffs : "latest_diff"
    roles ||--o{ model_has_roles : "applies_to_models"
    permissions ||--o{ role_has_permissions : "grants"
    roles ||--o{ role_has_permissions : "has"
    roles ||--o{ role_folder_permissions : "defines_access_for"
    folders ||--o{ role_folder_permissions : "accessed_by"
    notification_types ||--o{ role_folder_permissions : "specifies_notification"
    tags ||--o{ taggables : "applied_to"
    users ||--o{ activity_log : "caused_by"
    notification_types ||--o{ notifications : "categorizes"
```

## 主要テーブルの説明

*   **`users`**:
    *   目的: システムの全ユーザー情報を格納します。認証、ユーザープロファイル情報が含まれます。
    *   主要カラム: `id`, `name`, `email`, `password`。
*   **`organizations`**:
    *   目的: ユーザーが所属する組織（部署、チームなど）の情報を格納します。階層構造を持つことができます。
    *   主要カラム: `id`, `name`, `parent_id` (自己参照による階層化)。
*   **`user_organizations`**:
    *   目的: `users` と `organizations` の多対多の関係を定義する中間テーブル。ユーザーがどの組織に所属し、主要な所属組織がどれかを示します。
    *   主要カラム: `user_id`, `organization_id`, `is_primary`。
*   **`folders`**:
    *   目的: 台帳定義 (`ledger_defines`) を格納・整理するためのフォルダ。階層構造を持ち、フォルダ単位での権限設定の基盤となります。
    *   主要カラム: `id`, `title`, `parent_id` (自己参照), `creator_id`, `modifier_id`。
*   **`ledger_defines`**:
    *   目的: 台帳のテンプレート（カラム構成、ワークフロー設定など）を定義します。
    *   主要カラム: `id`, `title`, `column_define` (JSON形式でカラム定義を格納。`number` 型の場合、`min`, `max`, `step`, `unit` などの属性を含む), `folder_id`, `workflow_enabled`。
*   **`ledgers`**:
    *   目的: 台帳レコードの最新データを格納します。`content` カラムはJSON形式で柔軟なデータを保持します。`status` カラムでワークフローの状態を管理します。
    *   主要カラム: `id`, `ledger_define_id`, `content` (JSON), `content_attached` (JSON, 添付ファイル検索用インデックス), `status`, `creator_id`, `modifier_id`, `latest_diff_id`, `version`。
*   **`ledger_diffs`**:
    *   目的: 台帳レコードの変更履歴（スナップショット）を格納します。ワークフローの各ステップ（点検依頼、承認など）や編集時のデータ変更が記録されます。
    *   主要カラム: `id`, `ledger_id`, `content` (JSON, 変更時のデータ), `column_define` (JSON, 変更時の定義), `status` (変更時のステータス), `creator_id`, `modifier_id`, `inspector_id`, `approver_id`, `version`, `comments`。
*   **`roles`**:
    *   目的: (Spatie/laravel-permission) ユーザーに割り当てる役割（ロール）を定義します。パーミッションをグループ化します。
    *   主要カラム: `id`, `name`, `guard_name`, `description`。
*   **`permissions`**:
    *   目的: (Spatie/laravel-permission) システム内の個別の操作権限（パーミッション）を定義します。
    *   主要カラム: `id`, `name`, `guard_name`, `description`。
*   **`model_has_roles`**:
    *   目的: (Spatie/laravel-permission) `User` や `Organization` などのモデルと `roles` の多対多の関係（ポリモーフィック）を定義する中間テーブル。
    *   主要カラム: `role_id`, `model_type`, `model_id`。
*   **`role_has_permissions`**:
    *   目的: (Spatie/laravel-permission) `roles` と `permissions` の多対多の関係を定義する中間テーブル。
    *   主要カラム: `permission_id`, `role_id`。
*   **`role_folder_permissions`**:
    *   目的: ロールとフォルダに対する詳細な権限（読み取り、書き込み、点検、承認、通知設定など）を管理します。
    *   主要カラム: `id`, `role_id`, `folder_id`, `permission` (権限の種類), `notification_type_id`。
*   **`tags`**:
    *   目的: 台帳定義などに付与できるタグを定義します。
    *   主要カラム: `id`, `name`, `slug`。
*   **`taggables`**:
    *   目的: `tags` と他のモデル（例: `LedgerDefine`）との多対多の関係（ポリモーフィック）を定義する中間テーブル。
    *   主要カラム: `tag_id`, `taggable_type`, `taggable_id`。
*   **`attached_files`**:
    *   目的: `ledgers` レコードに添付されたファイルのメタデータ（パス、ファイル名、MIMEタイプ、サイズなど）を格納します。
    *   主要カラム: `id`, `ledger_id`, `file_path`, `file_name`, `mime_type`, `size`。
*   **`notifications`**:
    *   目的: (Laravel標準) システム内で発生した通知（ワークフロー関連、お知らせなど）を格納します。
    *   主要カラム: `id`, `type` (通知クラス名), `notifiable_type`, `notifiable_id`, `data` (JSON), `read_at`。
*   **`notification_types`**:
    *   目的: システム内で送信される通知の種類を定義・管理します（例: 点検依頼通知、承認完了通知）。
    *   主要カラム: `id`, `name`, `description`。
*   **`jobs` / `job_batches`**:
    *   目的: (Laravel Queue) 非同期処理のためのジョブおよびバッチジョブの情報を格納します。メール送信や重い処理などに使用されます。
    *   主要カラム (`jobs`): `id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`。
    *   主要カラム (`job_batches`): `id`, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`。
*   **`activity_log`**:
    *   目的: (spatie/laravel-activitylog) システム内の主要なモデルに対する操作ログ（作成、更新、削除など）を記録します。
    *   主要カラム: `id`, `log_name`, `description`, `subject_type`, `subject_id`, `event`, `causer_type`, `causer_id`, `properties` (JSON)。
