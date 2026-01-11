# W3-3.1 承認履歴UIおよび編集者情報表示 詳細設計

**最終更新:** 2026-01-11
**対象:** LedgerLeap v12.0 / Cycle 3
**関連Issue:** #38
**関連調査:** [W2-1.5 Workflow UI Investigation](./2026-01-11_W2-1-5_workflow_ui_investigation.md)

## 1. 概要

本ドキュメントは、Cycle 3 で実装する「承認履歴UI」および「編集者情報表示・コピー機能」の詳細設計書である。
`W2-1.5` の調査結果に基づき、サイドバー形式の履歴リストを拡張し、厳密なワークフロー状態とアクター情報を提供する。

## 2. UI/UX詳細設計

### 2.1 サイドバー履歴アイテム (`LedgerHistoryManager`)

既存の単純なリストアイテムを、以下の要素を持つ「承認履歴カード」に改修する。

**表示ルールと翻訳キー:**

1. **ヘッダー:**
    * `Version`: `Ver.{version}`
    * `Badge`: `WorkflowStatus::label()` でラベル取得、`WorkflowStatus::colorClass()` でスタイル適用。
    * `Date`: `created_at->format('Y/m/d H:i')`

2. **アクター行:**
    * **編集者行 (必須):**
        * アイコン: `o-pencil`
        * ラベル: `__('ledger.activity.column.causer')` ('操作者') または新規 `__('ledger.workflow.label.editor')` ('
          編集者')
        * 名前: `$diff->modifier->name`
    * **承認者行 (承認済みの場合):**
        * 条件: `$diff->status === WorkflowStatus::APPROVED`
        * アイコン: `o-check-circle`
        * ラベル: `__('ledger.workflow.approved_by')` (未定義なら追加: '承認者')
        * 名前: `$diff->approver->name`

3. **コメント:**
    * `$diff->comments` を表示。`__('ledger.activity.column.comment')` ('コメント') をツールチップ等で利用。

### 2.2 ユーザー情報ポップオーバー (`UserCardPopover`)

`x-ledger.user-card-popover` コンポーネントとして実装。

**表示項目とデータソース:**

| 項目       | データソース                              | 翻訳キー (lang/ja/ledger.php)                                 | 備考                                       |
|:---------|:------------------------------------|:----------------------------------------------------------|:-----------------------------------------|
| **氏名**   | `$user->name`                       | `access_and_permissions.column.user_name` ('ユーザー名')       |                                          |
| **所属**   | `$user->primaryOrganization?->name` | `access_and_permissions.column.organization_name` ('組織名') | `$user->primaryOrganization()` リレーションを利用 |
| **メール**  | `$user->email`                      | `access_and_permissions.column.email` ('メールアドレス')         | コピーボタン付き                                 |
| **チャット** | `$user->chat_link`                  | `user_info.chat_link` (新規: 'チャット')                        | リンクがある場合のみ表示                             |

## 3. 実装詳細

### 3.1 データベース・モデル拡張

`User` モデルおよびテーブルを拡張する。

* **マイグレーション:** `add_chat_link_to_users_table`
    * `chat_link` (string, nullable) を `users` テーブルに追加。
* **Userモデル:**
    * `$fillable` に `chat_link` を追加。
    * `primaryOrganization` リレーションは既存 (`BelongsToMany` where pivot `is_primary`) を利用。

### 3.2 データ構造とクエリ (`LedgerHistoryManager`)

N+1問題を回避し、ポップオーバーに必要な組織情報を取得するため、Eager Loading を修正する。

```php
// app/Livewire/Ledger/LedgerHistoryManager.php

public function render()
{
    // ...
    $diffsQuery = $this->ledgerRecord->ledgerDiff()
        // id,name の制限を外し、primaryOrganization もロードする
        ->with([
            'modifier.primaryOrganization',
            'inspector.primaryOrganization',
            'approver.primaryOrganization'
        ])
        ->orderBy('created_at', 'desc')
        ->orderBy('id', 'desc');
    // ...
}
```

### 3.3 コンポーネント実装 (`UserCardPopover`)

Bladeコンポーネントとして実装し、`LedgerHistoryManager` や `UserList` などから再利用可能にする。

```html
<!-- resources/views/components/ledger/user-card-popover.blade.php -->
@props(['user'])

<div class="p-4 w-64">
    <div class="flex items-center gap-3 mb-3">
        <x-mary-avatar :title="$user->name" class="!w-10 !h-10"/>
        <div>
            <div class="font-bold">{{ $user->name }}</div>
            <div class="text-xs text-gray-500">
                {{ $user->primaryOrganization?->name ?? __('ledger.access_and_permissions.no_organizations') }}
            </div>
        </div>
    </div>

    <!-- Email Copy -->
    <div class="flex items-center justify-between text-sm py-1 border-t">
        <span class="text-gray-500">{{ __('ledger.access_and_permissions.column.email') }}</span>
        <button class="btn btn-ghost btn-xs"
                x-data
                @click="
                navigator.clipboard.writeText('{{ $user->email }}');
                $dispatch('mary-toast', {type: 'success', title: '{{ __('ledger.file_inspector.messages.text_copied') }}'});
            ">
            <x-mary-icon name="o-clipboard"/>
        </button>
    </div>

    <!-- Chat Link -->
    @if($user->chat_link)
    <div class="flex items-center justify-between text-sm py-1 border-t">
        <span class="text-gray-500">{{ __('ledger.user_info.chat') }}</span>
        <a href="{{ $user->chat_link }}" target="_blank" class="btn btn-ghost btn-xs text-primary">
            <x-mary-icon name="o-chat-bubble-left-right"/>
        </a>
    </div>
    @endif
</div>
```

### 3.4 翻訳ファイル追加 (`lang/ja/ledger.php`)

以下のキーを追加する。

```php
'user_info' => [
    'chat' => 'チャット',
    'chat_link' => 'チャットリンク',
],
'workflow' => [
    'label' => [
        'editor' => '編集者',
        'approver' => '承認者',
    ],
    // approved_by が未定義の場合は追加
    'approved_by' => '承認者', 
],
```

## 4. テスト計画

### 4.1 Unit/Feature Test

* **`UserCardPopoverTest`**:
    * ユーザー情報 (名前, 組織, メール) の表示確認。
    * `chat_link` の有無による表示切り替え確認。
* **`LedgerHistoryManagerTest`**:
    * 承認済み `LedgerDiff` に対する承認者情報の表示確認。
    * Eager Loading が正しく機能し、クエリ回数が爆発していないことの確認。

### 4.2 Manual Test

* 承認フローを経て `LedgerDiff` を作成し、サイドバーに承認者情報が表示されるか。
* ユーザー名をクリックしてポップオーバーが表示され、メールコピーやチャットリンクが機能するか。

## 5. タスク分解 (#38)

1. **マイグレーション:** `users` テーブルに `chat_link` 追加。
2. **翻訳追加:** `lang/ja/ledger.php` に不足キーを追加。
3. **UIコンポーネント作成:** `resources/views/components/ledger/user-card-popover.blade.php`。
4. **Livewire改修:** `LedgerHistoryManager` のクエリ (`with`) 修正。
5. **リストUI改修:** `ledger-history-manager.blade.php` に承認履歴カードデザイン適用。
6. **テスト実装 & 実行:** Featureテスト作成とパス確認。

この設計に基づき実装を進める。
