# Organizationモデル

## モデルの目的
システム内の組織（部署やチームなど）を表します。組織階層の管理や、組織単位での権限付与などに利用されます。

## 関連テーブル
`organizations` テーブル

## 主要な属性

*   **`$fillable`**:
    *   `org_id`: 組織ID (ユニーク識別子)
    *   `name`: 組織名
    *   `description`: 組織の説明
    *   `parent_id`: 親組織のID (階層構造のため)
*   **その他主要な属性**:
    *   `id`: 一意なID (Primary Key)
    *   `_lft`, `_rgt`: `kalnoy/nestedset` による入れ子集合モデルのための内部属性。

## リレーションシップ

*   **`users()`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\User`
    *   説明: この組織に所属するユーザーのリスト。中間テーブル `user_organizations` を使用し、`is_primary` ピボット属性を持つ。
*   **`parent()` (from `NodeTrait`)**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\Organization` (self)
    *   説明: 親組織。
*   **`children()` (from `NodeTrait`)**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\Organization` (self)
    *   説明: 子組織のリスト。
*   **`roles()` (from `HasRoles` trait)**:
    *   タイプ: `MorphToMany`
    *   相手モデル: `Spatie\Permission\Models\Role`
    *   説明: この組織に直接割り当てられたロール。
*   **`permissions()` (from `HasRoles` trait)**:
    *   タイプ: `MorphToMany`
    *   相手モデル: `Spatie\Permission\Models\Permission`
    *   説明: この組織に直接割り当てられたパーミッション。

## 主要なスコープやメソッド

*   **`getAllPermissions()`**:
    *   説明: 組織自身が持つパーミッションと、全ての親組織から継承したパーミッションを結合して返します。
*   **`getAllRoles()`**:
    *   説明: 組織自身が持つロールと、全ての親組織から継承したロールを結合して返します。
*   **`hasPermissionWithInheritance($permission)`**:
    *   説明: 指定されたパーミッションを、組織自身または親組織のいずれかが持っている場合に `true` を返します。
*   **`hasRoleWithInheritance($role)`**:
    *   説明: 指定されたロールを、組織自身または親組織のいずれかが持っている場合に `true` を返します。
*   **`getDirectRoles()`**:
    *   説明: この組織に直接割り当てられているロールのみを返します。
*   **`getInheritedRoles()`**:
    *   説明: 親組織から継承したロールのみを返します（この組織に直接割り当てられているロールは除く）。
*   **`getDirectPermissions()`**:
    *   説明: この組織に直接割り当てられているパーミッションのみを返します。
*   **`getInheritedPermissions()`**:
    *   説明: 親組織から継承したパーミッションのみを返します（この組織に直接割り当てられているパーミッションは除く）。
*   **`getAllUniquePermissions()`**:
    *   説明: `getAllPermissions()` と同様ですが、パーミッションを一意にして返します。
*   **`getDirectPermissionsViaRoles()`**:
    *   説明: この組織に直接割り当てられたロールを通じて得られるパーミッションを返します。
*   **`getInheritedPermissionsViaRoles()`**:
    *   説明: 親組織から継承したロールを通じて得られるパーミッションを返します。
*   **`getAllUniquePermissionsViaRoles()`**:
    *   説明: この組織が持つ全てのロール（直接および継承）を通じて得られるユニークなパーミッションを返します。
*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

## その他

*   `NodeTrait` (`kalnoy/nestedset`) を利用して、組織の階層構造（親子関係）を効率的に管理しています。
*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `HasRoles` トレイトを利用して、Spatie Laravel Permission によるロールベースのアクセス制御を組織レベルで行います。
*   `SoftDeletes` トレイトを利用しており、論理削除に対応しています。
*   `HasTreeView` トレイトのコメントアウトが見られますが、過去に使用されていたか、将来的に使用する予定があった可能性があります。（要確認）
