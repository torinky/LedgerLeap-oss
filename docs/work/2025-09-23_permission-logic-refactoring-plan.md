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

---

## 5. リファクタリング後の課題：キャッシュの不整合

上記のリファクタリングを実施した結果、基本的な権限判定ロジックは正常化したものの、権限を**変更**した際の挙動に新たな問題が発覚した。

### 5.1. 発覚した問題

-   **症状:** 中央管理画面でフォルダ権限を変更しても、その結果が即座にUIに反映されない。
-   **詳細な再現手順:**
    1.  ルートフォルダ（`/`）の権限を変更する。
    2.  台帳一覧ページに戻っても、権限の表示（アイコン等）が変更前のままである。
    3.  しかし、一度別のフォルダ階層（例: `/Subfolder1`）に移動してからルートフォルダに戻ると、変更が反映される。
    4.  台帳一覧のコンテンツ表示（伏せ字かどうか）も、権限変更の1回前の状態が反映されてしまう。
    5.  `vendor/bin/sail artisan optimize:clear` などのコマンドで手動でキャッシュをクリアすると、全ての表示が正常に戻る。

### 5.2. 原因調査の経緯と考察

この「手動クリアで直る」「特定のコンポーネントだけ更新が遅れる」という症状から、原因は**複数のキャッシュ機構の不整合**にあると結論付けた。

1.  **仮説1：カスタムキャッシュのクリア漏れ**
    -   **試行:** `RoleFolderPermissionObserver` を修正し、`UserService` が利用するカスタムキャッシュ (`folder_permissions` タグ) を権限変更時にクリアするようにした。
    -   **結果:** 状況は改善しなかった。

2.  **仮説2：Spatie権限キャッシュのクリア漏れ**
    -   **試行:** `RoleFolderPermissionObserver` に、`spatie/laravel-permission` パッケージが内部で持つ独自の権限キャッシュをクリアする処理 (`PermissionRegistrar::forgetCachedPermissions()`) を追加した。
    -   **結果:** これでも問題は完全には解決せず、特にルートフォルダや台帳一覧の更新が遅れる症状が残った。

3.  **根本原因の結論:**
    このアプリケーションには、少なくとも以下の複数のキャッシュ層が存在する。
    -   Spatie/laravel-permission のキャッシュ
    -   `UserService` のカスタムキャッシュ
    -   `TenantAccessService` のカスタムキャッシュ
    -   Livewire コンポーネントが内部で持つ状態キャッシュ
    -   Laravel のビューキャッシュやアプリケーションキャッシュ

    現在の `RoleFolderPermissionObserver` によるキャッシュクリア処理は、これらのうち一部しかカバーできていない。そのため、権限を変更しても、一部のコンポーネントは古いキャッシュ（特にSpatieのキャッシュや、コンポーネント自身のキャッシュ）を参照し続けてしまい、UI上の不整合が発生している。

### 5.3. 今後の対応方針

#### 5.3.1. 包括的なキャッシュ戦略の策定

-   **目的:** 権限変更時に、関連する可能性のある全てのキャッシュを一括で、かつ効率的に無効化する仕組みを確立する。
-   **調査項目:**
    1.  `Livewire\Component` のライフサイクルとキャッシュ機構の調査。
    2.  `RecordsTable`, `FolderTree` などの主要コンポーネントが、内部でどのようにデータをキャッシュまたはプロパティとして保持しているかの確認。
    3.  関連する全サービスクラス (`UserService`, `TenantAccessService`, `PermissionService`) のキャッシュ利用状況の再レビュー。
-   **ゴール:** 上記調査に基づき、`RoleFolderPermissionObserver` を改修し、権限変更が全てのUIコンポーネントに即時反映されるようにする。

#### 5.3.2. テスト計画の保留

-   セクション `3.1. テスト計画` で立案したユニットテストおよびフィーチャーテストは、このキャッシュ不整合問題が**完全に解決した後に実装する**ものとする。
-   **理由:** 現在の不安定なキャッシュ状態では、テストが意図せず成功または失敗する可能性があり、信頼性のあるテストを記述することが困難なため。キャッシュ問題の解決後、改めてテストを実装し、ロジックの正しさとキャッシュの正常な動作の両方を恒久的に保証する。
