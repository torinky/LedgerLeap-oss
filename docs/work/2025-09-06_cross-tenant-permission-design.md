# テナント横断権限管理機能 詳細設計書

**日付:** 2025年9月6日
**作成者:** Gemini
**ステータス:** 設計完了 (更新)

## 1. 概要

本ドキュメントは、[新マルチテナント実装計画書](./2025-08-30_new-multi-tenant-implementation-plan-final.md)のステップ11で定義された「テナント横断権限管理機能」の実装に関する詳細な設計を定義するものである。

### 1.1. 目的

管理者が中央管理画面（Filament）から、全テナント共通の役割（Role）に対し、テナントごとに属するフォルダ（Folder）へのアクセス権限（読み取り、書き込み等）を柔軟に割り当てられるようにする。

### 1.2. アーキテクチャ方針

本機能は、既存のデータベース構造とモデルリレーションを最大限に活用して実現する。

*   **役割 (Role) / 権限 (Permission):** テナントに依存しない**中央テーブル**で一元管理する。
*   **フォルダ (Folder):** `BelongsToTenant` トレイトにより、各テナントに所属する。
*   **役割とフォルダの紐付け:** `role_folder_permissions` 中間テーブルが、「中央のRole」と「テナント固有のFolder」を紐付ける役割を担う。このテーブルは `role_id` と `folder_id` を持ち、`folder_id` を通じて間接的にテナントが特定されるため、このテーブル自体に `tenant_id` カラムは不要である。

このアーキテクチャにより、ユーザーの要件である「共通の役割を、テナント固有のデータに紐付ける」権限管理モデルを実現する。

## 2. 実装方針

`RoleResource` に関連付けられた `FolderPermissionRelationManager` を、既存のUIを可能な限り維持しつつ、以下の点を中心に部分的に改修する。

1.  **フォルダ選択の拡張:** 権限を新規に割り当てる際、全テナントのフォルダから対象を選択できるようにする。
2.  **表示の明確化:** 権限が割り当てられたフォルダが、どのテナントに属するのかをテーブル上で明確に表示する。

## 3. `FolderPermissionRelationManager` の改修タスクと発生した問題、解決策

### 3.1. テーブル表示の拡張 (`table()` メソッド)

**目的:** どのテナントのフォルダに対する権限設定なのかを一覧上で明確にする。

*   **タスク1: テナント名カラムの追加**
    *   `columns` 配列に、`folder` リレーションを介してテナント名を表示するカラムを追加する。
        ```php
        // TextColumn::make('folder.title') の前に以下を追加
        TextColumn::make('folder.tenant.name')
            ->label(__('ledger.tenant'))
            ->sortable()
            ->searchable(),
        ```
*   **タスク2: Eager Loadingの追加**
    *   `query()` メソッドのクエリビルダに `with()` を追加し、`folder` と `folder.tenant` リレーションをEager Loadすることで、N+1問題を防止する。
        ```php
        // query() メソッド内の return 文を修正
        $query->with(['folder.tenant']);
        return $query;
        ```

### 3.2. フォルダ選択機能の拡張 (`create` アクション)

**目的:** 権限を新規に割り当てる際、モーダル内で全テナントのフォルダをツリー形式で表示し、選択できるようにする。

*   **タスク: `SelectTree` コンポーネントのクエリ変更**
    *   `headerActions` の `create` アクション内にある `SelectTree::make('folder_id')` の定義を修正する。
    *   `relationship()` メソッドの `modifyQueryUsing` オプションを利用し、クロージャ内で `tenancy()->disable()` を実行する。これにより、`SelectTree` がフォルダを読み込む際のテナントスコープが一時的に無効化され、全テナントのフォルダが取得・表示されるようになる。
        ```php
        // create アクションの form() 内
        SelectTree::make('folder_id')
            ->label(__('ledger.folder.title'))
            ->relationship(
                name: 'folder', // リレーション名
                titleAttribute: 'title', // 表示する属性
                parentAttribute: 'parent_id', // 親IDの属性
                // ★ クエリをカスタマイズしてテナントスコープを解除
                modifyQueryUsing: fn(EloquentBuilder $query) => tenancy()->disable() && $query->orderBy('_lft')
            )
            ->required()
            // ... 他のオプション
        ```
    *   **（推奨）オプション表示の改善:** `SelectTree` に `getOptionLabel` のようなカスタマイズ機能があれば、テナント名も併記（例: `経理部 (テナントA)`）することで、管理者がフォルダを識別しやすくなる。これは `SelectTree` プラグインの機能に依存する。

## 4. 発生した問題と解決策の詳細

### 4.1. 問題1: `notifications.index` ルートの `tenant` パラメータ不足

*   **経緯:** アプリケーションログに `Missing required parameter for [Route: notifications.index] [URI: {tenant}/notifications] [Missing parameter: tenant]` エラーが記録された。これは `resources/views/livewire/notifications/icon.blade.php` の `route()` ヘルパー呼び出しで発生していた。
*   **原因:** `notifications.index` ルートはテナントスコープであり、`tenant()?->id` が `null` を返す非テナントコンテキストでコンポーネントがレンダリングされる場合に、必要な `tenant` パラメータが提供されなかったため。
*   **解決策:** `resources/views/livewire/notifications/icon.blade.php` 内の該当する `<a>` タグを `@if (tenant()) ... @endif` で囲み、テナントコンテキストがアクティブな場合にのみリンクが生成されるように変更した。

### 4.2. 問題2: グローバルなマイポータル画面でCSSが適用されない

*   **経緯:** ユーザーから、グローバルなマイポータル画面でCSSが適用されていないという報告があった。
*   **原因:** `public/build` ディレクトリが空であり、Viteがフロントエンドアセット（CSS/JS）をコンパイルして配置していなかったため。
*   **解決策:** ユーザーにSail環境内で `vendor/bin/sail npm run build` コマンドを実行し、フロントエンドアセットをビルドするよう指示した。

### 4.3. 問題3: テナント名がリストに表示されない & フォルダ選択ダイアログでテナント識別ができない

*   **経緯:** `FolderPermissionRelationManager` のテーブルでテナント名が表示されず、フォルダ選択ダイアログ（`SelectTree`）でフォルダがどのテナントに属するのか識別できなかった。`TextColumn::make('folder.tenant.name')` が機能しないという報告があった。
*   **原因:**
    *   `tenants` テーブルには物理的な `name` カラムが存在せず、`name` は `data` JSON カラム内に格納されている仮想属性であった。Filament の `TextColumn` や `SelectTree` がネストされたリレーションシップで `VirtualColumn` トレイトによる仮想属性を直接処理できない場合があった。
    *   一部のテナントの `data` JSON に `name` キーが存在しない、または空であった。
*   **解決策:**
    *   `app/Models/Tenant.php` に `getNameAttribute()` アクセサを追加し、`$this->data['name'] ?? ''` を返すようにした。これにより、`$tenant->name` が常に文字列を返すことが保証され、Filament がテナント名を取得できるようになる。
    *   `app/Models/Folder.php` に `getDisplayTitleAttribute()` アクセサを追加し、`$this->title . ' (' . ($this->tenant->name ?: $this->tenant->id) . ')'` を返すようにした。テナント名（またはID）が存在する場合にのみ括弧付きで表示されるように条件分岐を追加した。
    *   `FolderPermissionRelationManager.php` の `SelectTree` の `relationship()` メソッドで `titleAttribute: 'display_title'` を使用するように変更し、`modifyQueryUsing` クロージャ内で `->with('tenant')` を追加して `Folder` モデルの `tenant` リレーションをEager Loadするようにした。
    *   `FolderPermissionRelationManager.php` の `TextColumn::make('folder.tenant.name')` を `TextColumn::make('folder.tenant.id')` に変更し、`formatStateUsing` を使用して `$record->folder->tenant->name ?: $record->folder->tenant->id` を表示するようにした。これにより、カラムにはテナント名（またはID）が確実に表示されるようになった。

### 4.4. 問題4: `Stancl\Tenancy\Tenancy::withoutTenancy` メソッドが存在しないエラー

*   **経緯:** フォルダとロールの紐付けダイアログを開こうとした際に `BadMethodCallException: Method Stancl\Tenancy\Tenancy::withoutTenancy does not exist.` エラーが発生した。
*   **原因:** `stancl/tenancy` の現在のバージョンでは、`Tenancy` ファサードの基になるクラス `Stancl\Tenancy\Tenancy` に `withoutTenancy` メソッドが存在しないため。代わりに `central()` メソッドを使用する必要があった。
*   **解決策:** `app/Filament/Resources/RoleResource/RelationManagers/FolderPermissionRelationManager.php` の `SelectTree` の `modifyQueryUsing` クロージャ内で、`\Stancl\Tenancy\Facades\Tenancy::withoutTenancy` の呼び出しを `\Stancl\Tenancy\Facades\Tenancy::central` に変更した。

### 4.5. 問題5: グループ化が機能しない & テナント名がグループヘッダーに表示されない

*   **経緯:** テーブルのグループ化が正しく機能せず、グループヘッダーにテナント名が表示されないという報告があった。
*   **原因:** Filament の `Group::make()` は、リレーションシップの仮想属性（`tenants.name`）を直接グループ化キーとして使用しようとすると、物理カラムが存在しないためSQLエラーが発生する。また、`getTitleUsing` メソッドが `Filament\Tables\Grouping\Group` クラスに存在しなかった。
*   **解決策:** `app/Filament/Resources/RoleResource/RelationManagers/FolderPermissionRelationManager.php` の `table()` メソッドで、`defaultGroup` を削除し、`groups` 配列内の `Group::make` を `folder.tenant.id` でグループ化するように変更した。これにより、物理カラムでグループ化が行われ、グループヘッダーにはテナントIDが表示されるようになった。

### 4.6. 問題6: カラムにテナント名が表示されない (最終的な調整)

*   **経緯:** グループ化は機能するようになったが、テナント名のカラム表示が期待通りではなかった。
*   **原因:** `TextColumn::make('folder.tenant.name')` が `getNameAttribute()` アクセサを介して取得される `name` 属性を正しくレンダリングできない場合があったため。
*   **解決策:** `app/Filament/Resources/RoleResource/RelationManagers/FolderPermissionRelationManager.php` の `TextColumn` を `TextColumn::make('folder.tenant.id')` に変更し、`formatStateUsing` を使用して `$record->folder->tenant->name ?: $record->folder->tenant->id` を表示するようにした。これにより、カラムにはテナント名（またはID）が確実に表示されるようになった。

## 5. 最終的な変更点

本機能の実装において、以下のファイルが変更された。

*   `app/Models/Tenant.php`:
    *   `getNameAttribute()` アクセサを追加し、`data` JSON カラムから `name` を取得するようにした。
*   `app/Models/Folder.php`:
    *   `getDisplayTitleAttribute()` アクセサを追加し、フォルダ名とテナント名を結合して表示するようにした。テナント名が存在しない場合はテナントIDを使用し、括弧の表示を条件付きにした。
*   `app/Filament/Resources/RoleResource/RelationManagers/FolderPermissionRelationManager.php`:
    *   `table()` メソッドの `columns` 定義を修正し、`folder.tenant.name` を表示する `TextColumn` を `folder.tenant.id` をベースとし、`formatStateUsing` でテナント名またはIDを表示するようにした。
    *   `table()` メソッドの `groups` 定義を修正し、`folder.tenant.id` でグループ化するように変更した。
    *   `headerActions` の `create` アクション内の `form` スキーマを修正し、テナント選択用の `Select` フィールドを追加した。
    *   `SelectTree` の `relationship()` メソッドの `modifyQueryUsing` クロージャを修正し、`\Stancl\Tenancy\Facades\Tenancy::withoutTenancy` を `\Stancl\Tenancy\Facades\Tenancy::central` に変更し、`->with('tenant')` を追加してテナントリレーションをEager Loadするようにした。
    *   `table()` メソッドの `filters` 配列にテナントフィルターを追加した。
*   `resources/views/livewire/notifications/icon.blade.php`:
    *   `notifications.index` ルートへのリンクを `@if (tenant())` で囲み、テナントコンテキストが存在する場合のみ表示するようにした。
