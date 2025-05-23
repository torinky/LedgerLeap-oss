# Userモデル

## モデルの目的
システムを利用するユーザーを表します。認証、認可、ユーザー情報の管理を行います。

## 関連テーブル
`users` テーブル

## 主要な属性

*   **`$fillable`**:
    *   `name`
    *   `email`
    *   `password`
    *   `login_landing_page`
*   **`$casts`**:
    *   `email_verified_at`: `datetime`
    *   `password`: `hashed`
    *   `login_landing_page`: `App\Enums\LoginLandingPage::class`
    *   `pending_inspection_count`: `integer`
    *   `pending_approval_count`: `integer`
*   **その他主要な属性**:
    *   `id`: ユーザーの一意なID (Primary Key)
    *   `remember_token`: 「Remember me」機能のためのトークン

## リレーションシップ

*   **`organizations()`**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `App\Models\Organization`
    *   説明: ユーザーが所属する組織のリスト。中間テーブル `user_organizations` を使用し、`is_primary` ピボット属性を持つ。
*   **`primaryOrganization()`**:
    *   タイプ: `BelongsToMany` (実質的には `HasOne` のような振る舞い)
    *   相手モデル: `App\Models\Organization`
    *   説明: ユーザーの主要な所属組織（一つ）。`user_organizations` テーブルの `is_primary` が `true` の組織。
*   **`roles()` (from `HasRoles` trait)**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `Spatie\Permission\Models\Role`
    *   説明: ユーザーに割り当てられたロール。
*   **`permissions()` (from `HasRoles` trait)**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `Spatie\Permission\Models\Permission`
    *   説明: ユーザーに直接割り当てられたパーミッション。
*   **`folder()`**:
    *   タイプ: (リレーションではないが便宜上記載)
    *   相手モデル: `App\Models\Folder`
    *   説明: グローバル通知のためにルートフォルダを返します。

## 関連するEnum

*   **`App\Enums\LoginLandingPage`**:
    *   説明: ログイン後の最初の表示ページの種類を定義します (例: マイポータル、フォルダ一覧)。

## 主要なスコープやメソッド

*   **`canAccessPanel(Panel $panel): bool`**:
    *   説明: Filament Admin Panel へのアクセス権限を判定します。現状は常に `true` を返します。
*   **`assignRole(...$roles): static`**:
    *   説明: `Spatie\Permission\Traits\HasRoles` の `assignRole` をオーバーライド。ロール割り当て後にキャッシュ（`WritableFolderRepository`, `UserService` のパーミッションキャッシュ）をクリアします。
*   **`removeRole($role)`**:
    *   説明: `Spatie\Permission\Traits\HasRoles` の `removeRole` をオーバーライド。ロール削除後にキャッシュをクリアします。
*   **`setPrimaryOrganization(Organization $organization)`**:
    *   説明: 指定された組織をユーザーの主要な所属組織として設定します。
*   **`getAllUniqueRoles()`**:
    *   説明: ユーザーが持つ全てのユニークなロールを取得します（`UserService`経由）。
*   **`getAllUniquePermissions()`**:
    *   説明: ユーザーが持つ全てのユニークなパーミッションを取得します（`UserService`経由）。
*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

## その他

*   `FilamentUser` インターフェースを実装しており、Filament Admin Panelで利用可能です。
*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `HasRoles` トレイトを利用して、Spatie Laravel Permission によるロールベースのアクセス制御を行います。
*   `SoftDeletes` トレイトを利用しており、論理削除に対応しています。
*   `boot()` メソッド内で、`saved` および `deleted` イベント時に `WritableFolderRepository` のキャッシュを操作するロジックが定義されています。
