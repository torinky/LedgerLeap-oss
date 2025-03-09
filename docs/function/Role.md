# ロール管理機能

## 概要

ロール管理機能は、LedgerLeap
において、ユーザーに付与するロールを管理するための機能です。ロールは、複数の権限をまとめたものであり、ユーザーや組織にロールを付与することで、そのユーザーや組織に権限を与えることができます。ロールの作成、編集、削除、権限の紐づけ、グローバル通知の設定を管理します。ロールは、通常の権限の他に、フォルダー権限を設定できます。フォルダーに紐づく種類（台帳、台帳の定義、フォルダー）へのアクセスには、フォルダー権限と通常の権限の両方が必要となります。フォルダー権限は、
`App\Models\RoleFolderPermission`で管理します。

## 機能詳細

### ロールの追加

* `App\Models\Role` に新しいレコードを作成することで、ロールを追加できます。
* `App\Filament\Resources\RoleResource` でロールを登録します。
* ロールには、`name`(ロール名)、`guard_name`(ガードネーム)、`label`(ラベル)、`permissions`(権限)を登録します。
    * `label`は、`name`と同じ値が利用されます。
        * `guard_name`は、権限のチェックをする時に使用します。基本的には、`web`とします。

### ロールの編集

* `App\Models\Role` の既存レコードを更新することで、ロール情報を編集できます。
* `App\Filament\Resources\RoleResource` でロール情報を編集します。
* ロールの`name`(ロール名)、`guard_name`(ガードネーム)、`label`(ラベル)、`permissions`(権限)などを編集します。

### ロールの削除

* `App\Models\Role` の既存レコードを削除することで、ロールを削除できます。
* `App\Filament\Resources\RoleResource` でロールを削除します。
* 削除したロールは、ユーザーや組織に紐づけることができなくなります。

### ロールと権限の紐づけ

* ロールには、複数の権限を紐づけることができます。
* `App\Models\Permission` で管理されている権限を、`App\Models\Role`に紐づけることで実現します。
* `App\Filament\Resources\RoleResource` で管理します。

### フォルダー権限の設定

* ロールには、通常の権限の他に、フォルダー権限を設定できます。
* フォルダーに紐づく種類（台帳、台帳の定義、フォルダー）へのアクセスには、フォルダー権限と通常の権限の両方が必要となります。
* `App\Models\RoleFolderPermission`で、フォルダーとロールの紐付けを管理します。
* `App\Enums\FolderPermissionType`を参照して、設定します。
* `App\Filament\Resources\RoleResource`から、`NotificationSettingsRelationManager`で設定します。

### グローバル通知の設定

* グローバル通知は、ロールに対して設定します。
* `App\Filament\Resources\RoleResource` で、グローバル通知を設定します。
* グローバル通知は、ルートフォルダー（IDが1）に対して設定します。

### 組織やユーザーとロール

* ロールは組織やユーザーに紐づけることができます。
* 組織やユーザーに紐づけることで、その組織やユーザーにロールを付与することができます。

## 関連ファイル

* `App\Models\Role`: ロール情報を管理するモデル。
* `App\Models\Permission`: 権限を管理するモデル。
* `App\Models\RoleFolderPermission`: フォルダー権限とグローバル通知を管理するモデル。
* `App\Filament\Resources\RoleResource`: ロールを管理するリソース。
* `App\Models\Organization`: 組織を管理するモデルです。
* `App\Models\User`:  ユーザーを管理するモデルです。
* `App\Enums\FolderPermissionType`: フォルダー権限のタイプを定義します。
* `App\Filament\Resources\RoleResource\RelationManagers\NotificationSettingsRelationManager`: ロールのフォルダー通知を設定します。
