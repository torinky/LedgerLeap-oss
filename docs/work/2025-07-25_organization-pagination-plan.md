# 組織リストのページネーション計画

## 1. 目的

`OrganizationResource` の組織リストにおいて、大量の組織データを効率的に管理・閲覧できるように、ページネーション機能を導入する。現在のツリービュー (`ListOrganizations.php`) はツリー構造の表示に特化しており、一般的なページネーションには適さないため、標準的なテーブル形式の新しいビューを追加する。

## 2. 現状の課題と背景

*   **ツリービューの制約:** `Studio15\FilamentTree\Components\TreePage` を継承している `ListOrganizations.php` は、ツリー構造の視覚的な表現に優れているが、親ノードと子ノードが異なるページに分かれると階層構造が崩れるため、一般的なページネーション（例: 1ページあたりN件表示）を直接組み込むのは困難である。
*   **大量データへの対応:** 組織の数が増加した場合、ツリービューでは一度に表示される情報量が多くなりすぎ、パフォーマンスやユーザー体験に影響を与える可能性がある。
*   **Filament Treeプラグインとの競合:** Filamentの標準的なページネーション機能と`Studio15\FilamentTree\Components\TreePage`の組み合わせが、予期せぬ動作やエラーを引き起こすことが判明した。

## 3. 提案するアプローチ（最終計画）

これまでの試行錯誤の結果、FilamentのタブUIと`Studio15\FilamentTree`プラグインの`Tree`コンポーネントを同じページ内で共存させるのは困難であると判断しました。それぞれのコンポーネントの設計思想を尊重し、機能を最大限に活用するため、以下の独立したページとナビゲーションによるアプローチを採用します。

1.  **`ListOrganizations` ページ:** Filamentの標準的なテーブル表示に特化させ、組織のリストを階層的に表示します。
2.  **`ListOrganizationsTree` ページ:** `Studio15\FilamentTree\Components\TreePage` を継承した独立したページとして維持し、ツリー構造の操作に特化させます。
3.  **ページ間のナビゲーション:** `ListOrganizations` ページと `ListOrganizationsTree` ページ間で相互に遷移するためのボタンを提供します。

## 4. 詳細計画（最終計画）

**目標:** `ListOrganizations` ページで組織の階層リストを効率的に表示し、`ListOrganizationsTree` ページでツリー構造を操作できるようにし、両ページ間をシームレスに移動できるようにする。

### ステップ1: `ListOrganizations.php` の実装（完了）

*   **ファイルパス:** `app/Filament/Resources/OrganizationResource/Pages/ListOrganizations.php`
*   **継承:** `Filament\Resources\Pages\ListRecords` を継承。
*   **実装内容:**
    *   **階層表示:** `table()` メソッドの `query()` で `Organization::withDepth()->defaultOrder()` を使用し、`name` カラムの `getStateUsing()` で階層に応じたインデントを付与することで、テーブル形式でツリー構造を視覚的に表現。
    *   **レコードURLの無効化:** `->recordUrl(fn($record) => null)` を設定し、テーブル行のクリックによる詳細ページ遷移を無効化。
    *   **カラム定義:** `id`, `name`, `org_id`, `description`, `combined_roles_permissions` (カスタムビュー), `parent.name`, `created_at`, `updated_at`, `deleted_at` などのカラムを定義。
    *   **フィルター:** `TrashedFilter` と、`SelectTree` を利用した親組織による `tree` フィルターを実装。
    *   **アクション:** 標準的なビュー、編集、削除、強制削除、復元アクションを定義。
    *   **並べ替え:** `reorderable('sort_order')` と `defaultSort('sort_order')` により、ドラッグ＆ドロップでの並べ替えを可能に。
    *   **ツリー表示へのナビゲーション:** `getHeaderActions()` に `Actions\Action::make('tree_view')->label('ツリー表示')->url(OrganizationResource::getUrl('tree'))` を追加し、`ListOrganizationsTree` ページへのボタンを提供。

### ステップ2: `ListOrganizationsTree.php` の実装（完了）

*   **ファイルパス:** `app/Filament/Resources/OrganizationResource/Pages/ListOrganizationsTree.php`
*   **継承:** `Studio15\FilamentTree\Components\TreePage` を継承。
*   **実装内容:**
    *   ツリー表示に必要な `getModel`, `getCreateForm`, `getEditForm`, `getInfolistColumns`, `getTreeActions` メソッドを実装。
    *   **リスト表示へのナビゲーション:** `getHeaderActions()` に `Actions\Action::make('list_view')->label('リスト表示')->url(OrganizationResource::getUrl('index'))` を追加し、`ListOrganizations` ページへのボタンを提供。

### ステップ3: `OrganizationResource.php` の修正（完了）

*   **ファイルパス:** `app/Filament/Resources/OrganizationResource.php`
*   **実装内容:**
    *   `getPages()` メソッドで `ListOrganizations` と `ListOrganizationsTree` の両方を `Filament\Resources\Pages\PageRegistration` を使用して登録。
    *   `getTreeModel` などのツリー関連メソッドは `ListOrganizationsTree.php` に移動済み。

## 5. 実装のステップ（コード変更時）

上記「4. 詳細計画（最終計画）」に記載の通り、各ファイルの修正が完了済み。

---

## これまでの取り組みとエラーの分析（最終版）

### 試行1: Filament Tabs と `TreePage` の直接連携

*   **アプローチ:** `ListOrganizations.php` を `TreePage` のままにし、`ListOrganizationsTable.php` を `ListRecords` として作成。`OrganizationResource` の `getPages()` で両方をタブとして登録しようとした。
*   **遭遇したエラー:**
    *   `treeプラグインの実装とfillament標準機能がバッティングしたためです。`
*   **分析:** Filamentのタブ機能は、同じページ内でコンテンツを切り替えることを想定しており、異なるページへのルーティングを直接タブとして扱うのは難しいことが判明。`TreePage` が持つ独自のライフサイクルやDOM操作が、Filamentの標準的なタブ管理と競合した可能性が高い。

### 試行2: `ListOrganizations.php` (ListRecords) 内での Filament Tabs と `Tree` コンポーネントの埋め込み

*   **アプローチ:** `ListOrganizations.php` を `ListRecords` に変更し、`getTabs()` メソッド内で「All」タブ（通常のテーブル）と「Tree」タブを定義。「Tree」タブのコンテンツとして `Studio15\FilamentTree\Components\Tree` コンポーネントを直接埋め込もうとした。
*   **遭遇したエラーと対応:**
    *   `Class "App\Filament\Resources\OrganizationResource\Pages\Organization" not found`: `ListOrganizations.php` で `use App\Models\Organization;` が不足していたため。**→ 修正済み**
    *   `App\Filament\Resources\OrganizationResource\Pages\ListOrganizationsTree::route does not exist.` / `Method Filament\Resources\Components\Tab::url does not exist.`: `Tab::url()` はFilamentのタブコンポーネントには存在しないメソッド。タブは同じページ内のコンテンツ切り替え用であり、別ページへのリンクには使えない。**→ `Tab::url()` の使用を中止し、`content()` で直接コンポーネントを埋め込む方針に変更。**
    *   `Class "Filament\Facades\Filament\Infolists\Components\TextEntry" not found`: `OrganizationResource.php` で `Filament\Infolists\Components\TextEntry` の名前空間が誤っていたため。**→ 修正済み**
    *   `Class "App\Filament\Resources\Actions\EditAction" not found`: `OrganizationResource.php` で `Filament\Actions` の `use` ステートメントが不足していたため。**→ 修正済み**
    *   `Class "App\Filament\Resources\Filament" not found`: `OrganizationResource.php` で `Filament\Facades\Filament` の `use` ステートメントが不足していたため。**→ 修正済み**

### 試行3: DaisyUI Tabs とカスタムBladeビュー、Livewireラッパー

*   **アプローチ:** FilamentのタブUIを諦め、`ListOrganizations.php` でカスタムBladeビューをレンダリング。そのBladeビュー内でDaisyUIのタブとAlpine.jsを使ってリスト表示とツリー表示を切り替えるUIを実装。ツリー表示には、`Studio15\FilamentTree\Components\Tree` をラップするLivewireコンポーネント (`OrganizationTreeComponent.php`) を作成し、`@livewire` で呼び出そうとした。
*   **遭遇したエラーと対応:**
    *   `Unable to find component: [tree]`: `@livewire('tree', ...)` が失敗。`Studio15\FilamentTree\Components\Tree` はLivewireコンポーネントとして登録されていないため。**→ `OrganizationTreeComponent` Livewireコンポーネントを作成し、その中で `Studio15\FilamentTree\Components\Tree` をインスタンス化する方針に変更。**
    *   `syntax error, unexpected token "use"`: Bladeの `@php` ブロック内で `use` ステートメントを直接使用したため。**→ 完全修飾名を使用するように修正。**
    *   `Class "Studio15\FilamentTree\Components\Tree" not found` (Livewireラッパー内): Livewireコンポーネント内で `Studio15\FilamentTree\Components\Tree::make()` を呼び出してもクラスが見つからない。これは、`Studio15\FilamentTree\Components\Tree` がLivewireコンポーネントとして直接インスタンス化されることを想定していないか、`TreePage` のコンテキスト外での利用が難しいことを示唆。
    *   `Treeという初期化メソッドはないはずです。`: `Tree::make()` が正しい初期化方法ではない可能性。

### 試行4: Filament Tabs と `TreePage` の再々々々計画

*   **アプローチ:** `ListOrganizations` ページは `ListRecords` を継承し、`getTabs()` メソッドで「All」（リスト表示）と「Tree」（`ListOrganizationsTree` ページへのリンク）を定義。`ListOrganizationsTree` ページは `Studio15\FilamentTree\Components\TreePage` を継承した独立したページとして再作成。`OrganizationResource` の `getPages()` で両方を `PageRegistration` を使用して登録。
*   **遭遇したエラーと対応:**
    *   `Method Filament\Resources\Components\Tab::url does not exist.`: `Tab::url()` はFilamentのタブコンポーネントには存在しないメソッド。タブは同じページ内のコンテンツ切り替え用であり、別ページへのリンクには使えない。**→ `Tab::url()` の使用を中止し、`content()` でBladeビューを埋め込む方針に変更。**
    *   `Class "Studio15\FilamentTree\Components\Tree" not found` (Bladeビュー内): Bladeビュー内で `\Studio15\FilamentTree\Components\Tree::make()` を呼び出してもクラスが見つからない。これは、`Tree` コンポーネントが `TreePage` のコンテキスト外での利用を想定していないためと考えられる。

### 最終的な解決策と今後の方向性

これまでのエラー分析から、`Studio15\FilamentTree` プラグインは `TreePage` を継承したページで利用されることを強く前提としていると推測されます。FilamentのタブUIと `TreePage` を同じページ内で共存させるのは非常に困難であると判断し、以下の最終的なアプローチを採用しました。

1.  **`ListOrganizations` ページは、Filamentの標準的なテーブル表示に特化させます。**
    *   `ListRecords` を継承し、`table()` メソッドで組織の階層リストを効率的に表示します。
    *   `getHeaderActions()` に「ツリー表示」ボタンを追加し、`ListOrganizationsTree` ページへのナビゲーションを提供します。
2.  **`ListOrganizationsTree` ページは、`Studio15\FilamentTree\Components\TreePage` を継承した独立したページとして維持します。**
    *   `getHeaderActions()` に「リスト表示」ボタンを追加し、`ListOrganizations` ページへのナビゲーションを提供します。
3.  **`OrganizationResource` の `getPages()` メソッドで両ページを `PageRegistration` を使用して登録します。**

このアプローチは、各コンポーネントの設計思想を尊重し、それぞれの機能を最大限に活用できると考えられます。
