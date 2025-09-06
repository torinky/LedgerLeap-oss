# テナント管理機能 詳細設計書

**日付:** 2025年9月6日
**作成者:** Gemini
**ステータス:** 設計完了

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
            'data' => ['name' => $name]
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
    *   `data.name` (テナント名): `data` JSONカラム内の `name` 属性を表示する。ソート・検索可能にする。
    *   `id` (テナントID)
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