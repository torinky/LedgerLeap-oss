# 権限管理機能

## 概要

権限管理機能は、LedgerLeap
において、リソースへのアクセスを制御し、セキュリティを確保するための重要な機能です。この機能により、各ユーザーがどのリソースに対してどのような操作を実行できるかを、ロールや権限によって細かく管理することができます。フォルダーへのアクセス権限、グローバル通知の設定を管理します。権限は、
`App\Models\Permission`と、`App\Models\RoleFolderPermission`で管理します。フォルダーへのアクセスは、
`App\Enums\FolderPermissionType` を参照して管理します。

## 機能詳細

### ロールと権限

* **ロール**:
    * ロールは、複数の権限をまとめたものであり、ユーザーに付与することで、そのユーザーに権限を割り当てることができます。
    * `App\Models\Role` で管理します。
    * ロールの作成、更新、削除は、`App\Filament\Resources\RoleResource`で行います。
    * ロールには、複数の権限を紐づけることができます。
    * `RolePermissionSeeder` に初期設定が定義されています。
        * 以下のロールが定義されています。
        * `super_admin`: すべての権限を持ちます。
        * `admin`: 管理業務を行うロールです。
        * `manager`: 組織の管理を行うロールです。
        * `user`: 一般ユーザーのロールです。
        * 運用に合わせて、追加や変更をします。
* **権限(`Permission`モデル)**:
    * 権限は、特定のリソースに対する操作の許可を表します。
    * `App\Models\Permission` で管理します。
    * 権限の作成、更新、削除は、`App\Filament\Resources\PermissionResource`で行います。
    * 権限は、`RolePermissionSeeder`に定義されています。
    * `App\Models\Permission` で管理する権限
        * `create_ledger_define`: 台帳定義の作成権限
        * `read_ledger_define`: 台帳定義の閲覧権限
        * `update_ledger_define`: 台帳定義の更新権限
        * `delete_ledger_define`: 台帳定義の削除権限
        * `create_ledger`: 台帳の作成権限
        * `read_ledger`: 台帳の閲覧権限
        * `update_ledger`: 台帳の更新権限
        * `delete_ledger`: 台帳の削除権限
        * `manage_activity`: アクティビティログの管理権限
        * `manage_user`: ユーザーの管理権限
        * `manage_organization`: 組織の管理権限
        * `manage_role`: ロールの管理権限
        * `manage_permission`: 権限の管理権限
    * 運用に合わせて、追加や変更をします。

### フォルダーへのアクセス制御(`RoleFolderPermission` モデル)

* フォルダーへのアクセス権限は、ロールごとに設定できます。
* `App\Models\RoleFolderPermission` で、フォルダーとロールの紐付けを管理します。
* `App\Models\RoleFolderPermission` の `permission` カラムは、`App\Enums\FolderPermissionType`
  を参照して、フォルダーへのアクセス権限と通知の有無を管理します。
* `App\Enums\FolderPermissionType`では、以下のアクセス権限と通知を管理します。
    * `READ`: 閲覧権限
    * `WRITE`: 書き込み権限
    * `ADMIN`: 管理者権限
    * `DELETE`: 削除権限
    * `NOTIFY_ON`: 通知を受け取る
    * `NOTIFY_OFF`: 通知を受け取らない
* `permission` には、`READ`, `WRITE`, `ADMIN`, `DELETE`を設定します。
* `notification_type_id`: 通知を受け取る時は設定します。
    * 通知タイプについては、通知のドキュメントを参照してください。
    * 通知を受け取るか、受け取らないかは、`NOTIFY_ON`, `NOTIFY_OFF`で管理します。
* フォルダー権限の設定、管理は、`App\Filament\Resources\RoleResource`から、`NotificationSettingsRelationManager`で行います。
* グローバル通知の設定、管理は、`App\Filament\Resources\RoleResource`で行います。

### グローバル通知の設定

* グローバル通知は、ロールに紐づけて管理します。
* グローバル通知の設定、管理は、`App\Filament\Resources\RoleResource`で行います。
* グローバル通知はフォルダーに紐づきません。
* グローバル通知は、ルートフォルダー（IDが1）を設定します。

## 関連ファイル

* `App\Models\Role`: ロールを管理するモデル。
* `App\Models\Permission`: 権限を管理するモデル。
* `App\Models\RoleFolderPermission`: フォルダー権限とグローバル通知を管理するモデル。
* `/database/seeders/RolePermissionSeeder.php`: 初期ロールと権限を設定するシーダー。
* `App\Enums\FolderPermissionType`: フォルダー権限のタイプを定義します。
* `App\Filament\Resources\RoleResource`: ロールを管理します。グローバル通知を設定します。
* `App\Filament\Resources\PermissionResource`: 権限を管理します。
* `App\Filament\Resources\RoleResource\RelationManagers\NotificationSettingsRelationManager`: ロールのフォルダー通知を設定します。
* `App\Models\NotificationType`: 通知のタイプを定義します。
