# 2025-09-20 Livewireにおけるテナンシーコンテキスト喪失問題の調査と解決策

## 1. 問題の概要

Livewireコンポーネント（特に `CreateColumn.php`）でLedgerレコードを新規作成する際、`tenant_id` が空の状態でデータベースに登録されてしまう問題が発生した。これにより、テナント固有のレコードが中央データベースに紐付けられ、テナントコンテキストでの参照時に404エラーが発生していた。

## 2. 問題の特定と原因分析

本問題は、テナント環境下でLedgerレコードの参照時に404エラーが発生し、新規作成時に`tenant_id`が空で登録されるという二つの事象から発覚した。詳細な調査により、以下の点が明らかになった。

### 2.1 参照時の404エラーの根本原因

*   **初期仮説の検証:** `UpdateController`や`ShowController`における`findOrFail()`の挙動、`tenancy()->run()`の誤用と正しい構文での再試行など、様々なアプローチを試みたが、404エラーは解消されなかった。
*   **デバッグログによる判明:** `UpdateController`に挿入したデバッグログにより、テナントは正しく初期化されているものの、データベース接続が中央DB（`ledgerleap`）のまま切り替わっていないことが判明した。
*   **シングルデータベース構成の確認:** ユーザーからの情報により、本プロジェクトがテナントごとにDBを分けるマルチデータベース構成ではなく、単一DB内で`tenant_id`カラムによりテナントを区別するシングルデータベース構成であることが判明。この場合、DB接続が中央DBのままであるのは正常な挙動である。
*   **データ不整合の発見:** `ledgers`テーブルのスキーマには`tenant_id`カラムが正しく存在することを確認後、`id=102`のレコードを直接クエリした結果、その`tenant_id`が空文字列（`""`）であることが判明した。
*   **結論:** 参照時の404エラーは、**対象レコードの`tenant_id`が空であるため、テナントスコープの条件に合致せず、レコードが見つからない**ことが直接的な原因であった。

### 2.2 新規作成時に`tenant_id`が空になる原因

*   **レコード作成箇所の特定:** `CreateController`には保存処理がなく、`routes`ファイルにも`Ledger`の`POST`ルートが見当たらないことから、Livewireコンポーネントが作成処理を担っていると推測。調査の結果、`resources/views/ledger/create.blade.php`が呼び出す`<livewire:ledger.create .../>`が、実際には`app/Livewire/Ledger/CreateColumn.php`の`saveDirectly()`メソッド内で`Ledger::create($ledgerData)`を実行していることを特定した。
*   **Livewireコンポーネントにおけるテナントコンテキスト喪失:** `CreateColumn.php`の`saveDirectly()`メソッド内に挿入したデバッグログにより、`Ledger::create()`実行直前で`"tenant_initialized": false`、`"current_tenant_id_from_helper": null`であることが判明した。
*   **結論:** 新規作成時に`tenant_id`が空になるのは、**LivewireのAJAXリクエストにおいてテナントコンテキストが失われるため、`Ledger::create()`実行時に`BelongsToTenant`トレイトによる`tenant_id`の自動付与が機能しない**ことが原因である。これは`stancl/tenancy`とLivewireの連携における一般的な問題である。

## 3. 解決策の検討と提案

Livewireコンポーネント内でテナントコンテキストが失われる問題は、`stancl/tenancy` と Livewire の連携における一般的な課題です。この問題をDRYに解決するため、カスタムトレイトを作成し、それを必要なLivewireコンポーネントに適用することを提案します。

### 3.1 カスタムトレイト `InitializesTenantContext` の作成

`app/Livewire/Traits/InitializesTenantContext.php` を作成し、Livewireコンポーネントの `boot` メソッドでテナントを明示的に初期化するロジックをカプセル化します。

```php
// app/Livewire/Traits/InitializesTenantContext.php
<?php

namespace App\Livewire\Traits;

use Livewire\Component;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait InitializesTenantContext
{
    public function initializeTenantContext(Tenancy $tenancy): void
    {
        if (!$tenancy->initialized()) {
            $tenantId = Request::route('tenant');
            if ($tenantId) {
                $tenant = Tenant::where('id', $tenantId)->first();
                if ($tenant) {
                    $tenancy->initialize($tenant);
                    Log::info('Tenant re-initialized via InitializesTenantContext trait', ['tenant_id' => $tenantId]);
                } else {
                    Log::error('Tenant not found for ID from route in InitializesTenantContext trait', ['tenant_id' => $tenantId]);
                    // エラーハンドリングは呼び出し元で行うか、ここでToastなどを表示
                }
            } else {
                Log::error('Tenant ID not found in route for InitializesTenantContext trait');
                // エラーハンドリング
            }
        }
    }

    // Livewire の boot メソッドで呼び出す
    public function bootInitializesTenantContext(Tenancy $tenancy): void
    {
        $this->initializeTenantContext($tenancy);
    }
}
```

### 3.2 `CreateColumn.php` の修正

`app/Livewire/Ledger/CreateColumn.php` に `InitializesTenantContext` トレイトを `use` し、`saveDirectly()` メソッド内のデバッグコードを削除します。`Ledger::create()` 呼び出し時に `tenant_id` が自動で付与されることを期待します。

```php
// app/Livewire/Ledger/CreateColumn.php

use App\Livewire\Traits\InitializesTenantContext; // 追加

class CreateColumn extends Component
{
    use InitializesTenantContext; // 追加

    // ...

    public function saveDirectly(): void
    {
        // ... (既存のワークフローチェック) ...

        // デバッグコードを削除し、明示的な tenant_id の設定は BelongsToTenant に任せる
        // bootInitializesTenantContext でテナントが初期化されるため、Ledger::create() で自動付与されるはず

        // ... (バリデーション、ファイル処理) ...

        try {
            // ... (トランザクション開始) ...

            $ledgerData = [
                'ledger_define_id' => $this->ledgerDefineId,
                'content' => $this->content,
                'content_attached' => $this->contentAttached,
                'modifier_id' => $userId,
                'status' => WorkflowStatus::NONE,
                // 'tenant_id' は BelongsToTenant トレイトが自動で付与する
            ];

            if ($this->ledgerId && $this->ledgerRecord) {
                // ... (更新処理) ...
            } else { // 新規作成の場合
                // デバッグコードを削除
                $ledgerData['creator_id'] = $userId;
                $ledgerData['version'] = 1;
                $ledger = Ledger::create($ledgerData); // ここで tenant_id が自動付与されることを期待
                $this->ledgerId = $ledger->id;
                $this->ledgerRecord = $ledger;
                $message = __('ledger.stored.success');
            }

            // ... (LedgerDiff 作成処理、トランザクション確定) ...

        } catch (Throwable $e) {
            // ... (エラーハンドリング) ...
        }
    }
}
```

### 3.3 プロジェクト内の他のLivewireコンポーネントへの適用

`app/Livewire` ディレクトリ以下の全てのLivewireコンポーネントを調査し、テナントコンテキストが必要なコンポーネントに `InitializesTenantContext` トレイトを `use` します。

### 3.4 必要なテストの追加

*   **`InitializesTenantContext` トレイトのユニットテスト:**
    *   トレイトがテナントを正しく初期化するかどうかを検証するテスト。
*   **`CreateColumn.php` のフィーチャーテスト:**
    *   `CreateColumn` コンポーネントがテナントコンテキストで `Ledger` レコードを正しく作成し、`tenant_id` が適切に設定されることを検証するテスト。

### 3.5 既存レコードの修正とデバッグコードの削除

*   `UPDATE ledgers SET tenant_id = 'tenanta' WHERE id = 102;` を実行し、既存の不整合レコードを修正します。
*   `UpdateController.php` と `CreateColumn.php` に挿入したデバッグコードを削除し、元の状態に戻します。

## 4. 解決策の実施と進捗

### 4.1 カスタムトレイト `InitializesTenantContext` の作成 [完了]

`app/Livewire/Traits/InitializesTenantContext.php` は実装済みであったが、`stancl/tenancy` v3.9 とのAPI互換性の問題がログから発見された。`tenancy()->initialized()` メソッド呼び出しを `tenancy()->initialized` プロパティアクセスに修正し、問題を解決した。

### 4.2 `CreateColumn.php` の修正 [完了]

`app/Livewire/Ledger/CreateColumn.php` には、既に `InitializesTenantContext` トレイトが適用済みであることを確認した。

### 4.3 プロジェクト内の他のLivewireコンポーネントへの適用 [未着手]

`CreateColumn.php` 以外にもテナントコンテキストを必要とするコンポーネントがないか、調査と適用が必要。

### 4.4 必要なテストの追加 [中断→方針転換]

*   **`InitializesTenantContext` トレイトのユニットテスト:**
    *   `tests/Unit/Livewire/Traits/InitializesTenantContextTest.php` が存在したが、Livewireのテスト環境の複雑性に起因する様々なエラー（`BadMethodCallException`, `RootTagMissingFromViewException` 等）により、安定したテストの実行が困難であった。
    *   ユニットテストによる検証を中断し、より信頼性の高いフィーチャーテストに方針を転換する。
*   **`CreateColumn.php` のフィーチャーテスト:**
    *   次のステップとして、このフィーチャーテストを作成する。

### 4.5 既存レコードの修正とデバッグコードの削除 [未着手]

すべての改修とテストが完了した後に実施する。

## 5. 調査・対応ログ (2025-09-20)

1.  **現状把握:**
    *   `app/Livewire` 配下のコンポーネントをリストアップし、影響範囲の特定に着手。
    *   解決策として提案されていた `InitializesTenantContext` トレイトと、その適用先である `CreateColumn.php` が既に実装済みであることを確認。
2.  **エラー原因の特定:**
    *   `storage/logs/laravel-2025-09-20.log` を確認し、`BadMethodCallException: Method Stancl\Tenancy\Tenancy::initialized does not exist.` を発見。
    *   `composer.json` から `stancl/tenancy` のバージョンが `^3.9` であることを特定し、APIのバージョン差異がエラーの根本原因であると断定。
3.  **トレイトの修正:**
    *   Web検索により `stancl/tenancy` v3.9 では `initialized` プロパティを使用することを確認。
    *   `InitializesTenantContext.php` 内の `!$tenancy->initialized()` を `!$tenancy->initialized` に修正。
4.  **ユニットテストの試行:**
    *   `tests/Unit/Livewire/Traits/InitializesTenantContextTest.php` を発見し、テストを実行。
    *   `Request` ファサードのモックに起因する `BadMethodCallException` が発生。
    *   `URL` ファサードへの変更、`Request` オブジェクトの直接生成など、複数のアプローチでテストコードを修正したが、`RootTagMissingFromViewException` や `InvalidCountException` など、Livewireのテスト環境に起因する新たな問題が次々と発生。
5.  **方針転換の決定:**
    *   ユニットテストの安定化は困難と判断。より実践的で信頼性の高いフィーチャーテストに切り替える方針をユーザーと合意した。

## 6. 次のステップ

1.  **`InitializesTenantContext` トレイトの適用:**
    *   以下のテナントコンテキストを必要とするLivewireコンポーネントに `use InitializesTenantContext;` を追加する。
        *   `app/Livewire/Ledger/ModifyColumn.php`
        *   `app/Livewire/Ledger/Show.php`
        *   `app/Livewire/Ledger/RecordsTable.php`
        *   `app/Livewire/Ledger/Import.php`
        *   `app/Livewire/Ledger/WorkflowActionButtons.php`
        *   `app/Livewire/Ledger/WorkflowHistoryList.php`
        *   `app/Livewire/Ledger/WorkflowStatusCard.php`
        *   `app/Livewire/LedgerDefine/Create.php`
        *   `app/Livewire/LedgerDefine/Edit.php`
        *   `app/Livewire/LedgerDefine/ModifyColumn.php`
        *   `app/Livewire/LedgerDefine/Preview.php`
        *   `app/Livewire/Folder/FolderForm.php`
        *   `app/Livewire/Folder/Tag.php`
        *   `app/Livewire/Workflow/PendingList.php`
        *   `app/Livewire/Workflow/OtherRelatedTasksList.php`
        *   `app/Livewire/Workflow/WorkflowCommentModal.php`
        *   `app/Livelive/Common/ActivityHistoryDisplay.php`
        *   `app/Livewire/Common/PermissionDisplay.php`
        *   `app/Livewire/Notifications/Icon.php`
        *   `app/Livewire/Notifications/NotificationList.php`
        *   `app/Livewire/Notifications/Settings.php`
        *   `app/Livewire/MyPortal.php`

2.  **ビューファイルの堅牢化:**
    *   ビューファイル内で `route()` ヘルパーを使用している箇所において、テナントIDの受け渡し方を `tenant()?->id` ヘルパーに統一し、コンポーネントのプロパティへの依存を減らす。
    *   対象ファイル:
        *   `resources/views/components/ledger/table-row.blade.php`
        *   `resources/views/components/folder/folder-and-ledger-panels.blade.php`
        *   `resources/views/components/ledgerDefine/header.blade.php`
        *   `resources/views/livewire/ledger/modify-column.blade.php`
        *   `resources/views/livewire/ledger-define/records-table.blade.php`
        *   `resources/views/livewire/notifications/icon.blade.php`

3.  **フィーチャーテストの作成:**
    *   `CreateColumn.php` が、テナントコンテキストで正しく `Ledger` レコードを作成し、`tenant_id` が適切に設定されることを検証するフィーチャーテストを作成する。

4.  **最終作業:**
    *   全ての改修とテストが完了した後、`UPDATE ledgers SET tenant_id = 'tenanta' WHERE id = 102;` を実行し、不整合データを修正する。
    *   調査のために追加したデバッグコードがあれば、全て削除する。