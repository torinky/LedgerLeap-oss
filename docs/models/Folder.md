# Folderモデル

## モデルの目的
システム内の情報を階層的に整理するためのフォルダを表します。台帳定義 (`LedgerDefine`) を格納し、フォルダ単位での権限管理の基盤となります。

## 関連テーブル
`folders` テーブル

## 主要な属性

*   **`$fillable`**:
    *   `title`: フォルダ名
    *   `modifier_id`: 更新者のユーザーID
    *   `creator_id`: 作成者のユーザーID
    *   `parent_id`: 親フォルダのID (階層構造のため)
*   **その他主要な属性**:
    *   `id`: 一意なID (Primary Key)
    *   `_lft`, `_rgt`: `kalnoy/nestedset` による入れ子集合モデルのための内部属性。

## リレーションシップ

*   **`ledgerDefines()`**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\LedgerDefine`
    *   説明: このフォルダに直接含まれる台帳定義のリスト。
*   **`folders()`**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\Folder` (self)
    *   説明: このフォルダの直下の子フォルダのリスト。
*   **`tag()`**:
    *   タイプ: `HasMany` (実際には `LedgerDefine` を経由する想定か？ 要確認。モデルコード上では `ledger_define_id` を参照している)
    *   相手モデル: `App\Models\Tag`
    *   説明: このフォルダに関連付けられたタグ。(関連付けのキーが `ledger_define_id` になっている点は注意が必要)
*   **`creator()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: このフォルダを作成したユーザー。
*   **`modifier()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: このフォルダを最後に更新したユーザー。
*   **`parent()` (from `NodeTrait`)**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\Folder` (self)
    *   説明: 親フォルダ。
*   **`children()` (from `NodeTrait`)**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\Folder` (self)
    *   説明: 子フォルダのリスト。
*   **`roles()`**:
    *   タイプ: `MorphToMany` (Spatie\Permission\Traits\HasRolesと同様の多対多)
    *   相手モデル: `App\Models\Role` (SpatieのRoleモデル)
    *   説明: このフォルダに直接関連付けられたロール。`model_has_roles` テーブルを使用。
*   **`accessibleRoles(?FolderPermissionType $permission = null)`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\Role`
    *   説明: 指定された権限 (`FolderPermissionType`) を持つロールのリスト。中間テーブル `role_folder_permissions` を使用。通知関連の権限は除外される。
*   **`notificationSettings()`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\Role`
    *   説明: このフォルダに関する通知設定を持つロールのリスト。中間テーブル `role_folder_permissions` を使用し、`notification_type_id` と `permission` ピボット属性を持つ。

## 関連するEnum

*   **`App\Enums\FolderPermissionType`**:
    *   説明: フォルダに対する権限の種類（例: `write`, `read`, `manageable`, `notify_on`, `notify_off`）を定義します。

## 主要なスコープやメソッド

*   **`descendantLedgerDefinesCount()`**:
    *   説明: このフォルダ自身および全ての子孫フォルダに含まれる `LedgerDefine` の総数を返します。
*   **`descendantCount()`**:
    *   説明: このフォルダの全ての子孫フォルダの総数を返します。
*   **`treeList($nodes)` (static)**:
    *   説明: 与えられたフォルダノードのコレクションから、階層構造を表すプレフィックス付きのタイトルを持つドロップダウンリスト用の配列を生成します。結果はキャッシュされます。
*   **`getAllRoles()`**:
    *   説明: このフォルダ自身および全ての祖先フォルダに割り当てられたロールを結合してユニークなリストとして返します。
*   **`hasPermissionWithInheritance(Role $role, string $permission): bool`**:
    *   説明: 指定されたロールが、このフォルダまたはその祖先フォルダのいずれかから特定のパーミッションを継承しているかを確認します。結果はキャッシュされます。
*   **`hasDirectPermission(Role $role, string $permission): bool`**:
    *   説明: 指定されたロールが、このフォルダに直接特定のパーミッションを持っているかを確認します。
*   **`getDirectPermissions(Role $role): array`**:
    *   説明: 指定されたロールが、このフォルダに直接持っているパーミッションの配列を返します。
*   **`getAllPermissionsWithInheritance(Role $role): array`**:
    *   説明: 指定されたロールが、このフォルダおよびその祖先フォルダから継承する全てのユニークなパーミッションの配列を返します。結果はキャッシュされます。
*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

## その他

*   `NodeTrait` (`kalnoy/nestedset`) を利用して、フォルダの階層構造（親子関係）を効率的に管理しています。
*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `SoftDeletes` トレイトを利用しており、論理削除に対応しています。
*   `booted()` メソッド内で、モデルの `created`, `updated`, `deleted` イベント時にフォルダツリーや権限関連のキャッシュをクリアするロジックが定義されています。
*   `$guard_name` に `['web', 'api']` が設定されており、Spatie Laravel Permission のガードに対応しています。
*   `HasTreeView` トレイトのコメントアウトが見られます。過去に使用されていたか、将来的に使用する予定があった可能性があります。（要確認）
