# LedgerDefineモデル

## モデルの目的
システム内で利用される「台帳のテンプレート」を定義します。どのようなカラム（項目）を持つ台帳なのか、各カラムの型や設定、ワークフローの有効無効などを管理します。

## 関連テーブル
`ledger_defines` テーブル

## 主要な属性

*   **`$fillable`**:
    *   `title`: 台帳定義の名称
    *   `column_define`: カラム定義 (JSON形式で、各カラムの名前、型、ID、説明、オプションなどを格納)
    *   `folder_id`: この台帳定義が属するフォルダのID
    *   `creator_id`: 作成者のユーザーID
    *   `modifier_id`: 更新者のユーザーID
    *   `create_description`: 台帳作成画面用の説明文
    *   `list_description`: 台帳一覧画面用の説明文
    *   `detail_description`: 台帳詳細画面用の説明文
    *   `version`: 台帳定義のバージョン
    *   `recommended_inspector_id`: 推奨される検査担当者のユーザーID
    *   `recommended_approver_id`: 推奨される承認者のユーザーID
    *   `recommended_inspector_role_id`: 推奨される検査担当者のロールID
    *   `recommended_approver_role_id`: 推奨される承認者のロールID
*   **`$casts`**:
    *   `column_define`: `App\Casts\AsColumnDefinesArrayJson::class` (JSON文字列をカラム定義オブジェクトの配列として扱う)
    *   `workflow_enabled`: `boolean` (ワークフローが有効かどうかのフラグ)
*   **その他主要な属性**:
    *   `id`: 一意なID (Primary Key)

## リレーションシップ

*   **`ledgers()`**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\Ledger`
    *   説明: この定義に基づいて作成された全ての台帳レコード。
*   **`tags()`**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\Tag`
    *   説明: この台帳定義に関連付けられたタグ。
*   **`folder()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\Folder`
    *   説明: この台帳定義が属するフォルダ。
*   **`creator()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: この台帳定義を作成したユーザー。
*   **`modifier()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: この台帳定義を最後に更新したユーザー。
*   **`roles()` (from `HasModelRoles` trait)**:
    *   タイプ: `MorphToMany`
    *   相手モデル: `Spatie\Permission\Models\Role`
    *   説明: この台帳定義に直接関連付けられたロール。
*   **`recommendedInspector()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: 推奨される検査担当者。
*   **`recommendedApprover()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: 推奨される承認者。
*   **`recommendedInspectorRole()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `Spatie\Permission\Models\Role` (設定ファイル `permission.models.role` に基づく)
    *   説明: 推奨される検査担当者のロール。
*   **`recommendedApproverRole()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `Spatie\Permission\Models\Role` (設定ファイル `permission.models.role` に基づく)
    *   説明: 推奨される承認者のロール。

## 主要なスコープやメソッド

*   **`scopeSearchTags($query, $keywords)`**:
    *   説明: 指定されたキーワードに一致するタグを持つ台帳定義を検索します。
*   **`getMaxColumnIdAttribute()` (アクセサ)**:
    *   説明: `column_define` 内で現在使用されている最大のカラムIDを返します。
*   **`normalizeByColumnDefine($content)`**:
    *   説明: 与えられたコンテンツ配列 (`$content`) を、この台帳定義の `column_define` に基づいて正規化します。具体的には、定義に存在するがコンテンツに不足しているカラムにデフォルト値（チェックボックス型なら空配列、その他は空文字列）を補い、カラムIDの昇順に並べ替えた後、数値添字配列として返します。
*   **`hasPermissionTo($permission): bool` (from `HasModelRoles` trait)**:
    *   説明: この台帳定義に関連付けられたロールが、指定されたパーミッションを持っているかどうかを判定します。
*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

## その他

*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `SoftDeletes` トレaitを利用しており、論理削除に対応しています。
*   `HasModelRoles` トレイトを利用して、モデルレベルでのロールベースのアクセス制御を可能にしています。
*   `AsColumnDefinesArrayJson` カスタムキャストは、JSON形式で保存されているカラム定義データをPHPのオブジェクト配列として透過的に扱えるようにするものです。

## 関連するトレイト
*   **`App\Traits\HasModelRoles`**:
    *   モデルにロールを割り当て、モデル固有の権限管理を行う機能を提供します。`Spatie\Permission\Traits\HasRoles` と似ていますが、モデルインスタンス自体にロールを紐付ける点が異なります。
