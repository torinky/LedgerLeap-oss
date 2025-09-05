# テナント管理機能 詳細設計書

**日付:** 2025年9月6日
**作成者:** Gemini
**ステータス:** 設計中

## 1. 概要

本ドキュメントは、[新マルチテナント実装計画書](./2025-08-30_new-multi-tenant-implementation-plan-final.md)のステップ10で定義された「テナント管理機能」の実装に関する詳細な設計を定義するものである。

### 1.1. 目的

管理者が中央管理画面（Filament）から、システム内に存在する全テナントを一覧・管理し、各テナント固有のリソース（台帳定義など）へシームレスにアクセスできるUI/UXを提供することを目的とする。

### 1.2. 背景

現状の中央管理画面には、各テナントの設定を直接操作する手段がない。以前はダッシュボードウィジェットから台帳定義へのリンクが存在したが、テナントコンテキストの欠如によりエラーが発生するため削除された。本機能は、その代替となる、より堅牢で拡張性の高い管理機能を提供する。

## 2. 実装方針

Filamentの標準機能であるリソース（Resource）を活用し、テナント（`App\Models\Tenant`）を管理するための専用UIを構築する。

## 3. 実装タスク

### 3.1. `TenantResource` の作成

1.  **コマンドの実行:** 以下のArtisanコマンドを実行し、`TenantResource`とその関連ファイルを生成する。
    ```bash
    php artisan make:filament-resource Tenant --generate
    ```
    これにより、`app/Filament/Resources/TenantResource.php` および関連するページクラスが作成される。

### 3.2. テナント一覧ページの実装 (`ListTenants`)

`TenantResource.php`の`table()`メソッドを編集し、テナント一覧を定義する。

*   **表示カラム:**
    *   `id`: テナントID。ソート可能、検索可能にする。
    *   `name`: テナント名。ソート可能、検索可能にする。
    *   `created_at`: 作成日時。ソート可能にする。

*   **フィルター:**
    *   現時点では特に不要。将来的にテナントのステータス（有効/無効など）が追加された場合は、フィルターを追加する。

*   **アクション（各行）:**
    *   **編集アクション:** Filament標準の`Tables\Actions\EditAction`を配置する。
    *   **台帳定義管理アクション:**
        *   **ラベル:** `ledger.settings.framework`（台帳定義）
        *   **アイコン:** `heroicon-o-clipboard-document-list`
        *   **動作:** `route('ledgerDefine.index', ['tenant' => $record->id])` で生成されるURLへ遷移させる。
        *   **推奨:** `openUrlInNewTab()` を設定し、新しいタブで開くことで、管理画面のコンテキストを維持する。
    *   **フォルダ管理アクション:**
        *   **ラベル:** `ledger.settings.folder`（フォルダ）
        *   **アイコン:** `heroicon-o-folder`
        *   **動作:** `route('filament.admin.resources.folders.index', ['tenant' => $record->id])` のようなURLへ遷移させる。（注: `FolderResource`側でテナントIDを受け取れるような改修が別途必要になる可能性がある）

*   **ヘッダーアクション（テーブル全体）:**
    *   Filament標準の`Tables\Actions\CreateAction`を配置し、新規テナント作成画面へ遷移できるようにする。

### 3.3. テナント作成・編集フォームの実装 (`CreateTenant`, `EditTenant`)

`TenantResource.php`の`form()`メソッドを編集し、テナント情報の入力フォームを定義する。

*   **入力フィールド:**
    *   `name`: テナント名。必須入力、テキストフィールド。
    *   `id`: テナントID（パス名）。必須入力、ユニーク制約。`stancl/tenancy`はデフォルトでテナントIDを小文字・URLセーフな文字列に変換するが、任意の文字列を許可するために`tenancy.php`の`tenant_id_generator`設定を`function ($id) { return $id; }`のように変更することを検討する。

## 4. 既存機能への影響と修正

*   **`DashboardLinksWidget.php`:**
    *   本リソースが実装されることにより、ダッシュボードからテナント固有設定へのリンクは不要となる。リンクが削除されている現状を維持する。

*   **`FolderResource`など他のリソース:**
    *   `TenantResource`から`FolderResource`などへテナントIDを渡してフィルタリングする場合、対象のリソース側でURLパラメータを受け取り、クエリをスコープする改修が必要になる可能性がある。

## 5. 今後の拡張

*   テナントごとのユーザー一覧へのリンク。
*   テナントの有効/無効を切り替える機能。
*   特定のテナントのDB接続情報を管理する機能（フェーズ2移行時）。
