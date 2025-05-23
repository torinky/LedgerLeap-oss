# Permissionモデル

## モデルの目的
Spatie Laravel Permission パッケージの `Permission` モデルを拡張し、システム内の個別の操作権限を定義します。ロール (`Role`) に割り当てられ、ユーザーが特定の操作を行えるかどうかを判定するために使用されます。

## 関連テーブル
`permissions` テーブル (Spatie Laravel Permission のデフォルト)

## 主要な属性

*   **`$fillable`**:
    *   `name`: パーミッション名 (ユニークであるべき、通常は `.` 区切りで命名規則に従うことが多い。例: `posts.create`)
    *   `guard_name`: ガード名 (通常は `web` や `api`)
    *   `description`: パーミッションの説明
*   **その他主要な属性 (Spatie Permission から継承)**:
    *   `id`: 一意なID (Primary Key)

## リレーションシップ

*   **`roles()` (from Spatie Permission)**:
    *   タイプ: `BelongsToMany`
    *   相手モデル: `Spatie\Permission\Models\Role` (または `App\Models\Role`)
    *   説明: このパーミッションが割り当てられているロール。
*   **`users()` (from Spatie Permission)**:
    *   タイプ: `MorphToMany`
    *   相手モデル: `App\Models\User` (設定により変更可能)
    *   説明: このパーミッションが直接割り当てられているユーザー。

## 主要なスコープやメソッド

*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

## その他

*   `Spatie\Permission\Models\Permission` を継承しています。
*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `HasFactory` トレイトを利用しており、ファクトリによるテストデータ生成が可能です。
