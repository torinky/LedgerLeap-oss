# Roleモデル

## モデルの目的
Spatie Laravel Permission パッケージの `Role` モデルを拡張し、システム内の役割（ロール）を定義します。ユーザーや組織、フォルダなどに割り当てられ、特定の権限（パーミッション）をグループ化して管理するために使用されます。

## 関連テーブル
`roles` テーブル (Spatie Laravel Permission のデフォルト)

## 主要な属性

*   **`$fillable`**:
    *   `name`: ロール名 (ユニークであるべき)
    *   `guard_name`: ガード名 (通常は `web` や `api`)
    *   `description`: ロールの説明
*   **その他主要な属性 (Spatie Role から継承)**:
    *   `id`: 一意なID (Primary Key)
    *   `permissions`: このロールに割り当てられたパーミッションのコレクション。

## リレーションシップ

*   **`permissions()` (from Spatie Role)**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `Spatie\Permission\Models\Permission` (または `App\Models\Permission`)
    *   説明: このロールに割り当てられたパーミッション。
*   **`users()` (from Spatie Role)**:
    *   タイプ: `MorphToMany`
    *   相手モデル: `App\Models\User` (設定により変更可能)
    *   説明: このロールが割り当てられているユーザー。
*   **`tags()`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\Tag`
    *   説明: このロールに関連付けられたタグ。中間テーブル `role_tag` を使用。
*   **`organizations()`**:
    *   タイプ: `MorphToMany`
    *   相手モデル: `App\Models\Organization`
    *   説明: このロールが割り当てられている組織。`model_has_roles` テーブルを使用。
*   **`folders()`**:
    *   タイプ: `MorphToMany`
    *   相手モデル: `App\Models\Folder`
    *   説明: このロールが割り当てられているフォルダ。`model_has_roles` テーブルを使用。
*   **`readableFolders()` / `writableFolders()` / `manageableFolders()`**:
    *   タイプ: `BelongsToMany` (実質的には `accessibleFolders` のエイリアス)
    *   相手モデル: `App\Models\Folder`
    *   説明: 特定の権限（読み取り、書き込み、管理）でアクセス可能なフォルダのリスト。`role_folder_permissions` テーブル経由。
*   **`accessibleFolders($permission = null)`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\Folder`
    *   説明: 指定された権限 (`FolderPermissionType`) を持つ、または権限指定なしの場合は通知以外の何らかのアクセス権を持つフォルダのリスト。中間テーブル `role_folder_permissions` を使用。
*   **`folderPermissions()`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\Folder`
    *   説明: このロールが持つフォルダごとの権限設定。中間テーブル `role_folder_permissions` を使用し、`permission` ピボット属性を持つ。
*   **`notificationSettings()`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\Folder`
    *   説明: このロールに関連するフォルダごとの通知設定。中間テーブル `role_folder_permissions` を使用し、`notification_type_id` ピボット属性を持つ。
*   **`roleFolderPermissions()`**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\RoleFolderPermission`
    *   説明: このロールに直接関連する `RoleFolderPermission` モデルのリスト。
*   **`notificationType()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\NotificationType`
    *   説明: (コメントによれば `RoleFolderPermission` と `NotificationType` を経由する関連。直接的な関連の意図は要確認)

## 関連するEnum

*   **`App\Enums\FolderPermissionType`**:
    *   説明: `accessibleFolders` メソッドなどでフォルダアクセス権限の種類を指定する際に使用されます。

## 主要なスコープやメソッド

*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

## その他

*   `Spatie\Permission\Models\Role` を継承しています。
*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `Notifiable` トレイトを利用しており、通知関連の機能が利用可能です。
*   `booted()` メソッド内で、`updated` および `deleted` イベント時に、このロールを持つ全ユーザーの `WritableFolderRepository` キャッシュをクリアするロジックが定義されています。これは、ロールの変更がユーザーのアクセス可能フォルダに影響を与えるためです。
