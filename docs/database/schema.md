# LedgerLeap データベーススキーマ概要

**最終更新:** 2026年1月3日  
**Phase 1-5実装完了:** 添付ファイル機能統合（2025年12月-2026年1月）

## 1. 概要

LedgerLeapが使用する主要なデータベーステーブルの構造とテーブル間の関連についての概要を記述します。

**記載範囲:**
- 主要テーブルの構造とリレーション
- 全文検索（Mroonga）の仕様と制約
- テスト実装時の注意点

**記載しない内容:**
- モデルクラスの詳細 → `docs/models/`
- マイグレーションの実装 → `database/migrations/`
- 機能説明 → `docs/function/`

## 2. 主要テーブルER図 (Mermaid.js)

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
        text column_define "JSON column definitions"
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
        int column_id
        string tenant_id
        string filename
        string hashedbasename
        string mime
        string original_mime_type
        string path
        bigint size
        string status "Enum: PENDING, TIKA_PROCESSING, COMPLETED, etc."
        longtext vlm_markdown "VLM抽出Markdown (Phase 2追加)"
        json vlm_structured_data "VLM構造化データ (Phase 2追加)"
        string vlm_model
        decimal vlm_confidence "0.0000-1.0000"
        int vlm_processing_time_ms
        timestamp vlm_processed_at
        timestamp vlm_failed_at
        timestamp ocr_processed_at
        timestamp ocr_failed_at
        timestamp tika_processed_at
        timestamp processing_finalized_at "Phase 3追加"
        string finalized_source "vlm|ocr|tika (Phase 3追加)"
        longtext content "最終化後の採用テキスト (Phase 3追加)"
        boolean optimized
        text error_message
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
        text properties "properties in JSON format"
        string batch_uuid "UUID"
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

## 3. 全文検索 (Mroonga) に関する仕様と注意点

`ledgers` テーブルの `content` および `content_attached` カラムは、Mroongaを利用した全文検索のために特別な設定がされています。開発およびテストを行う際には、以下の点に注意してください。

### 3.1. スキーマとデータ構造

-   **カラム定義:** `content` と `content_attached` は `longtext` 型ですが、Mroongaの `flags "COLUMN_VECTOR"` コメントによって、**ベクターカラム**として扱われます。これにより、JSON配列の各要素がインデックス化の対象となります。
-   **データ形式:** これらのカラムには、Laravelのカスタムキャスト (`AsColumnArrayJson`) を通じてPHPの配列がJSON配列として保存されます。注意点として、配列の要素には単純な文字列だけでなく、**PHPでシリアライズされた配列 (`___serialized___...`) が含まれる**ことがあり、データ構造は複雑です。

### 3.2. content の正規化プロセス

台帳の各項目（カラム）は、保存前に以下のプロセスで正規化されます。これにより、**カラムIDが配列インデックスと一致**し、`$ledger->content[$columnId]` での直接アクセスが可能になります。

1. **Livewireでの管理**: カラムIDをキーとした連想配列 `[1 => 'value', 3 => 'value']`
2. **正規化処理**: カラムIDの欠番を空文字で埋める → `[0 => '', 1 => 'value', 2 => '', 3 => 'value']`
3. **DB保存**: `array_values()` で連番配列に変換 → JSON: `["", "value", "", "value"]`
4. **DB読み取り**: 連番配列として復元 → `[0 => '', 1 => 'value', 2 => '', 3 => 'value']`

**重要な注意点:**
- この正規化は**0から始まる連番配列の場合のみ**正しく動作します。
- カラムIDが1から始まる場合、インデックス0に空要素が必要です（例: `[0 => [], 1 => 'value']`）。
- テストやシーダーでデータを作成する際は、必ず0から始まる連番配列として準備してください。詳細は [Testing-Best-Practices.md](../development/Testing-Best-Practices.md) を参照してください。

### 3.3. 複合インデックスの制約（重要）

Mroongaでは、**複合インデックス（複数カラムを跨ぐインデックス）が正常に機能しません。** 全文検索クエリを書く際は、以下のルールを厳守してください。

-   **単一カラムインデックスの利用:** `content` と `content_attached` それぞれに個別の `FULLTEXT` インデックスを使用して検索してください。
-   **複合検索の実現:** 複数のカラムを対象とする場合は、`OR` で結合します。

```sql
-- ○ 正解（単独インデックスのOR結合）
SELECT * FROM ledgers WHERE 
  MATCH(content) AGAINST('キーワード') OR 
  MATCH(content_attached) AGAINST('キーワード');

-- × 動作しない（複合インデックス）
SELECT * FROM ledgers WHERE MATCH(content, content_attached) AGAINST('キーワード');
```

-   **`Ledger::scopeSearch`:** モデルに定義されたこのメソッドを使用すると、自動的に最適なOR結合クエリが生成されます。

### 3.4. Mroonga対応の自動型変換（重要）

**問題:** Mroongaのベクターカラム処理において、数値キーのJSON配列内に整数値がある場合、副作用で二重配列化や文字の分断が発生し、Eloquentでの取得時にデコードエラー（null）になることがあります。

**解決策:** `AsColumnArrayJson` カスタムキャストの `setContent()` メソッドで、**整数・浮動小数点数を自動的に文字列に変換**して保存しています。これにより、開発者は副作用を意識せず、UIやテストからの入力をそのまま扱えます。

### 3.5. AsColumnArrayJsonキャストの制約（data_get 不適合）

`AsColumnArrayJson` は内部で独自のシリアライゼーションを使用しているため、Laravelの `data_get()` や `Arr::get()` ヘルパーが正しく動作しません。

-   **対策:** 常に `$ledger->content[$id]` や `$ledger->content_attached[$id][$filename]` の形式で**直接配列アクセス**を行ってください。

### 3.6. テスト実装時の極めて重要な注意点

-   **`RefreshDatabase` トレイトとの非互換性:** Mroongaのインデックス更新はコミット後に行われるため、`RefreshDatabase` トレイトを使用したテスト（トランザクションロールバック方式）では全文検索が機能しません。
-   **必須の対策:** 全文検索機能を含むフィーチャーテストを記述する際は、必ず **`DatabaseMigrations` トレイトを使用**してください。
-   **インデックス更新の待機:** データ作成からインデックスの更新完了までにごくわずかな遅延が発生する可能性があるため、テストが不安定な場合は `sleep(1);` を入れることを検討してください。

---

## 4. 主要テーブルの説明

システムを構成する核心テーブルと、その論理的なグループ分けを解説します。

### 4.0. テーブルグループ概観

- **台帳・テンプレート関連**: `ledgers`, `ledger_defines`, `ledger_diffs`
- **フォルダ・権限管理**: `folders`, `role_folder_permissions`, `roles`, `permissions`
- **ユーザー・組織**: `users`, `organizations`, `user_organizations`
- **添付ファイル・通知・その他**: `attached_files`, `notifications`, `tags`, `taggables`, `activity_log`, `jobs`

### 4.1. ユーザーと組織

*   **`users`**:
    *   目的: システムの全ユーザー情報を格納します。認証、ユーザープロファイル情報が含まれます。
    *   主要カラム: `id`, `name`, `email`, `password`。
*   **`organizations`**:
    *   目的: ユーザーが所属する組織（部署、チームなど）の情報を格納します。階層構造を持つことができます。
    *   主要カラム: `id`, `name`, `parent_id` (自己参照による階層化)。
*   **`user_organizations`**:
    *   目的: `users` と `organizations` の多対多の関係を定義する中間テーブル。ユーザーがどの組織に所属し、主要な所属組織がどれかを示します。
    *   主要カラム: `user_id`, `organization_id`, `is_primary`。

### 4.2. フォルダと台帳定義

*   **`folders`**:
    *   目的: 台帳定義 (`ledger_defines`) を格納・整理するためのフォルダ。階層構造を持ち、フォルダ単位での権限設定の基盤となります。
    *   主要カラム: `id`, `title`, `parent_id` (自己参照), `creator_id`, `modifier_id`。
*   **`ledger_defines`**:
    *   目的: 台帳のテンプレート（カラム構成、ワークフロー設定など）を定義します。
    *   主要カラム: `id`, `title`, `column_define` (JSON形式でカラム定義を格納。`number` 型の場合、`min`, `max`, `step`, `unit` などの属性を含む), `folder_id`, `workflow_enabled`。

### 4.3. 台帳データと履歴

*   **`ledgers`**:
    *   目的: 台帳レコードの最新データを格納します。`content` カラムはJSON形式で柔軟なデータを保持します。`status` カラムでワークフローの状態を管理します。
    *   主要カラム: `id`, `ledger_define_id`, `content` (JSON), `content_attached` (JSON, 添付ファイル検索用インデックス), `status`, `creator_id`, `modifier_id`, `latest_diff_id`, `version`, `activity_score` (活動スコア), `composite_score` (複合スコア)。
    *   **スコアリング関連カラム:**
        *   `activity_score` (DECIMAL 5,2): 直近の操作頻度を反映した活動スコア (0-100)
        *   `composite_score` (DECIMAL 5,2): 活動・新鮮度・重要度を統合した複合スコア (0-100)
*   **`ledger_diffs`**:
    *   目的: 台帳レコードの変更履歴（スナップショット）を格納します。ワークフローの各ステップ（点検依頼、承認など）や編集時のデータ変更が記録されます。
    *   主要カラム: `id`, `ledger_id`, `content` (JSON, 変更時のデータ), `column_define` (JSON, 変更時の定義), `status` (変更時のステータス), `creator_id`, `modifier_id`, `inspector_id`, `approver_id`, `version`, `comments`。

### 4.4. 添付ファイル（Phase 1-5で大幅拡張）

*   **`attached_files`**:
    *   目的: `ledgers` レコードに添付されたファイルのメタデータと処理状態を格納します。Phase 1-5（2025年12月-2026年1月）でVLM/OCR統合に伴い大幅に拡張されました。
    *   **主要なカラム:**
        *   `vlm_markdown`: VLM抽出結果（Markdown形式、RAG統合用）
        *   `ocr_processed_at`: OCR処理完了日時
        *   `finalized_source`: 最終的に採用されたテキストソース（'vlm' | 'ocr' | 'tika'）
        *   `content`: 最終化後の採用テキスト（Mroonga全文検索対象）
    *   **重要:** エンジン選択優先順位は VLM（最優先） > OCR（次点） > Tika（フォールバック）です。詳細は [AttachedFileモデルの詳細](../models/AttachedFile.md) を参照してください。

### 4.5. 権限管理

*   **`roles`**: Spatie Roles。
*   **`permissions`**: Spatie Permissions。
*   **`role_folder_permissions`**: フォルダごとの詳細権限管理。
    *   主要カラム: `id`, `role_id`, `folder_id`, `permission` (閲覧・書き込み・点検・承認・管理)。

### 4.6. タグと通知

*   **`tags`**, **`taggables`**: タグ管理。
*   **`notifications`**, **`notification_types`**: ワークフロー連動通知。

### 4.7. 非同期処理とログ

*   **`jobs`**, **`job_batches`**: VLM/OCR処理などの非同期タスク管理。
*   **`activity_log`**: 全操作の監査証跡（Spatie）。

---

## 5. 関連ドキュメント

### データモデル
- **[AttachedFileモデル](../models/AttachedFile.md)** - 添付ファイルの詳細仕様
- **[Ledgerモデル](../models/Ledger.md)** - 台帳データの詳細仕様

### アーキテクチャ
- **[VLM-OCR技術選定](../architecture/vlm-ocr-technology-selection.md)** - 添付ファイル処理の技術選定
- **[非同期処理](../architecture/QueueProcessing.md)** - ジョブフローとエラーハンドリング

### 開発ガイド
- **[テストのベストプラクティス](../development/Testing-Best-Practices.md)** - Mroonga対応テストの書き方
- **[VLM/OCR開発者ガイド](../development/vlm-ocr.md)** - VLM/OCR機能の実装ガイド

