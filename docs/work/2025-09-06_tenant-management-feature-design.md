# テナント管理機能 詳細設計書

**日付:** 2025年9月6日
**作成者:** Gemini
**ステータス:** 実装完了

## 1. 目的

管理者が中央管理画面（Filament）から、システム内に存在する全テナントを一覧・管理し、各テナント固有のリソースへシームレスにアクセスできるUI/UXを構築する。

本設計は、既存の `app:setup-tenant` Artisanコマンドの機能をGUIで実現し、今後のテナント管理の中心的な機能となることを目指す。

## 2. アーキテクチャ方針: `data` カラムの活用

`tenants` テーブルのスキーマは変更せず、`stancl/tenancy` パッケージの標準機能である `data` カラム（JSON型）に、テナント名 (`name`) や説明 (`description`) といったカスタム属性を格納する。

これにより、将来的な属性追加にもマイグレーションなしで柔軟に対応できる、拡張性の高い設計とする。

## 3. 既存機能の改修 (`app:setup-tenant` コマンド)

GUIでのテナント作成機能とロジックを共通化するため、まず既存のArtisanコマンドを新しい仕様に対応させる。

*   **対象ファイル:** `app/Console/Commands/SetupTenant.php`
*   **変更点:**
    1.  **シグネチャの変更:** コマンドがテナント名も受け取れるように、`$signature` を変更する。
        *   変更前: `app:setup-tenant {tenant_id} {admin_email}`
        *   変更後: `app:setup-tenant {tenant_id} {name} {admin_email}`
    2.  **ハンドルメソッドの変更:** `handle()` メソッド内で、`Tenant::create()` を呼び出す際に、`data` カラムに `name` を格納するように修正する。
        ```php
        // handle()内
        $name = $this->argument('name');
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => $name, // トップレベルのキーとして渡す
        ]);
        ```
*   **理由:** CUIから作成されるテナントにも、人間が識別可能な名前を付与するため。

## 4. 新規実装 (`TenantResource`)

Filamentリソースを新規に作成し、テナントのCRUD（作成・読み取り・更新・削除）機能を提供する。

### 4.1. リソース生成

*   **コマンド:** `php artisan make:filament-resource Tenant --generate` を実行する。

### 4.2. 一覧画面 (`ListTenants`) の実装

*   **対象ファイル:** `app/Filament/Resources/TenantResource.php`
*   **変更点:** `table()` メソッドを編集し、以下のカラムを表示する。
    *   `name` (テナント名): `data` JSONカラム内の `name` 属性を表示する。ソート・検索可能にする。
    *   `id` (テナントID)
    *   `description` (説明): `data` JSONカラム内の `description` 属性を表示する。ソート・検索可能にする。
    *   `created_at` (作成日時)
    *   各行に、`EditAction` および各テナント固有リソースへのリンクを配置する。

### 4.3. データとフォームのマッピング

フォームのフィールド (`name`, `description`) と、モデルの `data` カラム（JSON）をスムーズに連携させるため、Filamentのデータ変換機能を利用する。

*   **対象ファイル:** `app/Filament/Resources/TenantResource.php`
*   **実装方針:** `mutateFormDataBeforeCreate`, `mutateFormDataBeforeSave`, `mutateFormDataBeforeFill` の各メソッドをオーバーライドする。

    *   **`mutateFormDataBeforeCreate/Save` (保存時):**
        フォームからの入力 (`['name' => '...', 'description' => '...']`) を、`data` カラムに格納できる形式 (`['data' => ['name' => '...', 'description' => '...']]`) に変換する。
        ```php
        protected function mutateFormDataBeforeCreate(array $data): array
        {
            $data['data'] = [
                'name' => $data['name'],
                'description' => $data['description'],
            ];
            return $data;
        }
        ```

    *   **`mutateFormDataBeforeFill` (編集画面表示時):**
        モデルの `data` カラムから値を取り出し、フォームの各フィールド (`name`, `description`) に設定する。
        ```php
        protected function mutateFormDataBeforeFill(array $data): array
        {
            $data['name'] = $this->record->data['name'] ?? null;
            $data['description'] = $this->record->data['description'] ?? null;
            return $data;
        }
        ```

### 4.4. 作成・編集フォームの実装

*   **対象ファイル:** `app/Filament/Resources/TenantResource.php`
*   **変更点:** `form()` メソッドで以下の入力フィールドを定義する。
    *   `name` (テナント名): Text input, 必須。
    *   `id` (テナントID): Text input, 必須, ユニーク。作成時のみ入力可能とし、編集時は読み取り専用 (`disabled()`) にする。
    *   `description` (説明): Text area。
    *   `admin_email`: Text input, 必須, `users`テーブルに存在すること。作成時のみ表示 (`visibleOn('create')`)。

### 4.5. 作成ロジックの実装 (`CreateTenant` ページ)

*   **対象ファイル:** `app/Filament/Resources/TenantResource/Pages/CreateTenant.php`
*   **変更点:** `handleRecordCreation()` メソッドをオーバーライドし、**改修後の `app:setup-tenant` コマンドと全く同じ処理フローを実装する。**
    *   `Tenant::create()` を呼び出す際には、`mutateFormDataBeforeCreate` で変換された `$data` を使用する。
    *   これにより、GUIとCUIのどちらで作成しても、テナントの初期状態が完全に一致することを保証する。

## 5. 開発過程での課題と解決策

### 5.1. `data` カラムへの保存問題

*   **課題:** `stancl/tenancy` の `Tenant` モデルの `data` カラム（JSON型）に、フォームからの入力（テナント名、説明）が保存されない問題が発生した。
*   **経緯と試行錯誤:**
    *   当初、`TenantResource.php` の `form()` で `data.name` のようなドット記法を使用し、`mutateFormDataBeforeCreate` などのメソッドでデータを変換するアプローチを試みた。しかし、GUIからの保存がうまくいかず、DBの `data` カラムが `null` のままになる事象が発生した。
    *   `app:setup-tenant` コマンドのテストでも同様の問題が発生し、CUIからの保存もできていないことが判明した。
    *   `Tenant::create(['id' => $tenantId, 'data' => ['name' => $name]])` のような `data` キーを明示的に含む形式、`$tenant->put('name', $name)`、`$tenant->update(['data' => ['name' => $name]])` など、様々な方法を試したが、いずれも `data` カラムへの保存が成功しなかった。
    *   この間、`Tenant` モデルの `$fillable` や `$casts`、アクセサ/ミューテタの定義が `stancl/tenancy` の `HasData` トレイトの挙動を妨げている可能性が浮上した。
*   **最終的な解決策:**
    *   **`stancl/tenancy` の `HasData` トレイトの挙動を理解:** `stancl/tenancy` の `Tenant` モデルは、`id` 以外の属性については、`$tenant->name = 'My Tenant';` のようにプロパティとして値を代入すると、その値が自動的に `data` カラムのJSONデータとして保存されることが判明した。これは `HasData` トレイトが `__get()` や `__set()` といったマジックメソッドで処理しているためである。
    *   **`App\Models\Tenant.php` の修正:** `App\Models\Tenant.php` から、`$fillable` の `data` や `$casts`、および以前追加したアクセサ/ミューテタをすべて削除し、`stancl/tenancy` のデフォルトの挙動に完全に任せるようにした。
    *   **`app/Console/Commands/SetupTenant.php` の修正:** `Tenant::create(['id' => $tenantId])` でテナントを作成した後、`$tenant->name = $name;` と `$tenant->save();` を実行するように修正した。
    *   **`app/Filament/Resources/TenantResource.php` の修正:** `form()` のフィールド名を `name`, `description` に戻し、`table()` のカラム名も `name`, `description` に戻した。`mutateFormDataBeforeCreate` などのメソッドは不要なため削除した。
    *   **`app/Filament/Resources/TenantResource/Pages/CreateTenant.php` の修正:** `handleRecordCreation()` 内で、`Tenant::create($data)` の後に `$tenant->name = $data['name']; $tenant->description = $data['description'] ?? null; $tenant->save();` を追加し、`data` カラムへの保存を確実にした。
*   **結果:** CUIからの `app:setup-tenant` コマンドによるテナント作成と `data` カラムへの保存が成功し、Filament GUIからのテナント作成、一覧表示、編集においても、テナント名と説明が `data` カラムに正しく保存され、表示されるようになった。

## 6. 既存機能への影響と修正

*   **`DashboardLinksWidget.php`:**
    *   本リソースが実装されることにより、ダッシュボードからテナント固有設定へのリンクは不要となる。リンクが削除されている現状を維持する。

*   **`FolderResource`など他のリソース:**
    *   `TenantResource`から`FolderResource`などへテナントIDを渡してフィルタリングする場合、対象のリソース側でURLパラメータを受け取り、クエリをスコープする改修が必要になる可能性がある。

## 7. 今後の拡張

*   テナントごとのユーザー一覧へのリンク。
*   テナントの有効/無効を切り替える機能。
*   特定のテナントのDB接続情報を管理する機能（フェーズ2移行時）。