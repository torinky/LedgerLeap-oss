# 権限判定ロジックのリファクタリング計画書

**日付:** 2025年9月23日
**作成者:** Gemini
**ステータス:** 計画承認済み・実装準備完了

## 1. 背景と問題点

### 1.1. 発覚した問題

マルチテナント実装後のUI確認において、`super user` であっても以下の権限不具合が発生していることが確認された。

1.  **アイコンの表示間違い:** フォルダに全ての権限を付与しても、「管理」権限のアイコンではなく「書き込み」権限のアイコンが表示される。
2.  **コンテンツの非表示:** 権限があるはずの台帳レコードの内容が、権限がないかのように伏せ字で表示される。

### 1.2. 原因調査の経緯

当初、ポリシーやキャッシュ、PHPのOPcacheなど、様々な要因を疑い調査を進めた。しかし、最終的にデバッグログを権限チェック処理の各所に仕込んで追跡した結果、以下の事実が判明した。

1.  権限チェックの要である `UserService::hasFolderPermission` メソッドが、データベースから権限情報を一件も取得できていなかった (`Granted Permissions from DB: None`)。
2.  これにより、権限チェックは常に `false` を返し、前述のUI不具合を引き起こしていた。
3.  ユーザーによるDBの直接確認により、`role_folder_permissions` テーブルに `tenant_id` カラムが存在し、その値がすべて `null` のまま保存されていることが確定した。

この `tenant_id` が `null` であることが、`BelongsToTenant` トレイトを持つ `RoleFolderPermission` モデルのクエリが結果を返さない根本原因であると結論付けた。

## 2. 設計方針の決定

### 2.1. 課題

`role_folder_permissions` テーブルの `tenant_id` が `null` のまま保存されている。

### 2.2. 当初案

中央管理画面から権限を保存するロジック (`FolderPermissionRelationManager`) を修正し、`tenant_id` を正しく書き込むように変更する。

### 2.3. 最終方針の採択

しかし、本システムの設計において **`folders.id` は全テナントを通じて一意である** ことが保証されている。この前提に立つと、`role_folder_permissions` テーブルに `tenant_id` を持たせることは、データの冗長化に繋がる。

そこで、将来の保守性向上と、よりクリーンなデータ構造を目指すため、以下の最終方針を採択する。

**新方針:** `role_folder_permissions` テーブルから `tenant_id` カラムを完全に削除する。権限判定は、都度 `folders` テーブルを `JOIN` して、その `folders.tenant_id` を使ってテナントを特定する方式に変更する。

## 3. 詳細な実装計画

### Step 1: 既存マイグレーションの修正

-   **方針:** 本システムは現在開発中であるため、可読性を優先し、差分マイグレーションは作成しない。
-   **対象:** `database/migrations/` 配下にある、`role_folder_permissions` テーブルを作成しているマイグレーションファイル。
-   **作業:** 上記ファイルから、`$table->string('tenant_id');` および関連する外部キー制約の行を直接削除する。これにより、新規環境構築時に `tenant_id` カラムが作成されなくなる。

### Step 2: モデルの修正

-   **対象:** `app/Models/RoleFolderPermission.php`
-   **作業:** `tenant_id` カラムの存在を前提とする `use \Stancl\Tenancy\Database\Concerns\BelongsToTenant;` トレイトを削除する。

### Step 3: 権限チェックロジックのリファクタリング

-   **対象:** `app/Services/UserService.php` の `hasFolderPermission` メソッド。
-   **作業:** `role_folder_permissions` テーブルを `where('tenant_id', ...)` で検索する現在のロジックを、`folders` テーブルを `JOIN` して `folders.tenant_id` で絞り込む新しいロジックに書き換える。キャッシュ機構はパフォーマンスのため維持する。

**変更後コード（イメージ）:**
```php
// ...
$query = RoleFolderPermission::query()
    ->join('folders', 'role_folder_permissions.folder_id', '=', 'folders.id')
    ->where('folders.tenant_id', $tenantId)
    ->whereIn('role_folder_permissions.role_id', $roleIds)
    // ...
```

### 3.1. テスト計画

今回のリファクタリングによるデグレードを防ぎ、将来の安定性を確保するため、以下のテスト計画を実施する。

#### 3.1.1. 影響を受ける既存テストの確認

リファクタリング完了後、以下のテストスイートを実行し、すべてパスすることを確認する。これらのテストは、権限判定ロジックに直接的・間接的に依存しているため、変更による影響がないことを保証するために不可欠である。

-   `tests/Unit/Services/UserServiceTest.php`
-   `tests/Unit/Policies/LedgerPolicyTest.php`
-   `tests/Unit/Policies/LedgerDefinePolicyTest.php`
-   `tests/Feature/Livewire/RecordsTableQueryTest.php`
-   `tests/Feature/TenantIsolationTest.php`

#### 3.1.2. 新規追加テストプラン

今回の不具合を恒久的に検出するため、以下のテストケースを `UserServiceTest.php` および、必要に応じて `RecordsTable` のフィーチャーテストに新たに追加する。

##### ユニットテスト (`UserServiceTest.php`)

-   **目的:** `hasFolderPermission` が `tenant_id` を持たない `role_folder_permissions` テーブルと `folders` テーブルをJOINし、正しく権限を判定できることを検証する。
-   **シナリオ:**
    1.  `Super Admin` ロールを持つユーザーを作成する。
    2.  テナントA、テナントBを作成する。
    3.  テナントAに「フォルダA」、テナントBに「フォルダB」を作成する。
    4.  `role_folder_permissions` テーブルに、`Super Admin` ロールと「フォルダA」を紐付け、`ADMIN` 権限を直接登録する。
    5.  **確認項目1:** `hasFolderPermission($user, $folderA, ADMIN)` が `true` を返すことを確認する。
    6.  **確認項目2:** `hasFolderPermission($user, $folderA, READ)` が `true` を返すこと（権限の包含関係が有効であること）を確認する。
    7.  **確認項目3:** `hasFolderPermission($user, $folderB, ADMIN)` が `false` を返すこと（他テナントのフォルダには影響しないこと）を確認する。

##### フィーチャーテスト (例: `RecordsTableTest.php`)

-   **目的:** 実際のUI表示において、リファクタリング後の権限ロジックが正しく反映されていることを検証する。
-   **シナリオ:**
    1.  上記ユニットテストと同様のデータ（ユーザー、ロール、テナント、フォルダ、権限）を準備する。
    2.  `Super Admin` ユーザーとしてログインし、台帳一覧ページにアクセスする。
    3.  **確認項目1:** 「フォルダA」に紐づく台帳のコンテンツが、伏せ字にならずに正しく表示されていることをアサートする。
    4.  **確認項目2:** 「フォルダA」の権限アイコンが、「管理」権限を示す正しい表示になっていることをアサートする。
    5.  **確認項目3:** 「フォルダB」に紐づく台帳は表示されていない、あるいはアクセスできないことをアサートする。



## 4. 関連ドキュメント

-   [新マルチテナント実装計画書 (最終版)](./2025-08-30_new-multi-tenant-implementation-plan-final.md)
