# W3-1.2 表示状態引き継ぎ設計

**最終更新:** 2026-01-04
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`
**ステータス:** Draft

## 1. 目的

詳細画面（Show.php）で設定された `displayLevel`（表示レベル）や、ユーザーが操作した `collapasedStates`（グループ開閉状態）を、履歴タブ内の `LedgerHistoryManager` および `LedgerDiffViewer` に適切に引き継ぎ、同期させる仕組みを設計する。

## 2. 表示レベル (`displayLevel`) の同期

### 2.1 データフロー
`Show` (Parent) → `LedgerHistoryManager` (History Tab Root) → `LedgerDiffViewer` (Child)

### 2.2 実装方針
Livewire の `Reactive` プロパティまたはイベントリスナーを使用する。今回はイベントベースでの緩やかな結合を採用しつつ、初期化時はプロパティ渡しを行う。

1.  **Parent (`Show.php`)**:
    -   `displayLevel` プロパティを持つ（既存）。
    -   更新時に `displayLevelUpdated` イベントを発火する（既存）。
    -   `LedgerHistoryManager` コンポーネント呼び出し時に `:displayLevel="$displayLevel"` を渡す。

2.  **Child (`LedgerHistoryManager.php`)**:
    -   `#[Reactive]` 属性を使用して `$displayLevel` を定義し、親の変更を自動追従させる（Livewire v3の推奨パターン）。
    -   または、`displayLevelUpdated` イベントをリスンしてプロパティを更新する（v2互換/明示的制御）。
    -   **決定:** `#[Reactive]` を使用する。これによりイベント発火が不要になる可能性があるが、他コンポーネントへの互換性のためイベント発火は維持する。

3.  **GrandChild (`LedgerDiffViewer.php`)**:
    -   `LedgerHistoryManager` から `:displayLevel="$displayLevel"` で受け取る。
    -   こちらは純粋な UI コンポーネントであるため、`baseData` 生成時にこのレベルを使用する。

### 2.3 コードイメージ

**Show.blade.php**
```blade
<livewire:ledger.ledger-history-manager
    :ledgerId="$ledgerRecord->id"
    :displayLevel="$displayLevel"
    wire:key="history-manager-{{ $ledgerRecord->id }}"
/>
```

**LedgerHistoryManager.php**
```php
use Livewire\Attributes\Reactive;

class LedgerHistoryManager extends Component
{
    #[Reactive]
    public int $displayLevel;
    // ...
}
```

---

## 3. グループ開閉状態 (`collapsedStates`) の同期

### 3.1 変更方針: LocalStorage + Alpine.store の採用
ユーザー体験を向上させるため、**LocalStorage** を用いてコンポーネント（タブ）間での状態共有と永続化を行う方針に変更する。これにより、リロード後も状態が維持され、タブ間での同期もスムーズになる。

### 3.2 実装詳細

1.  **Alpine.store の定義 (`app.js` または `blade` 内 script)**
    -   `ledgerState` ストアを作成し、`collapsed` 状態を保持する。
    -   `init()` で LocalStorage からロードし、変更時に保存する。
    -   キーは `ledger_collapsed_{ledgerId}` とする。

    ```javascript
    document.addEventListener('alpine:init', () => {
        Alpine.store('ledgerState', {
            collapsed: {},
            ledgerId: null,

            init(ledgerId) {
                this.ledgerId = ledgerId;
                const key = `ledger_collapsed_${this.ledgerId}`;
                const stored = localStorage.getItem(key);
                if (stored) {
                    this.collapsed = JSON.parse(stored);
                }
            },

            toggle(groupName) {
                this.collapsed[groupName] = !this.collapsed[groupName];
                const key = `ledger_collapsed_${this.ledgerId}`;
                localStorage.setItem(key, JSON.stringify(this.collapsed));
            },

            isCollapsed(groupName) {
                return !!this.collapsed[groupName];
            }
        })
    })
    ```

2.  **LedgerDiffViewer (View)**
    -   `x-data` でストアにアクセスする。
    -   `Show.php` (基本情報タブ) の既存の開閉UIも、このストアを使う形にリファクタリングすることを**推奨**（今回は必須としないが、同期のためには望ましい）。
    -   Phase 1では、少なくとも履歴タブ (`LedgerDiffViewer`) はこのストアを利用して描画する。

    ```html
    <div x-data x-init="$store.ledgerState.init({{ $ledgerId }})">
        <!-- Group Loop -->
        <div class="collapse"
             :class="{ 'collapse-open': !$store.ledgerState.isCollapsed('{{ $groupName }}'), 'collapse-close': $store.ledgerState.isCollapsed('{{ $groupName }}') }"
             :aria-expanded="!$store.ledgerState.isCollapsed('{{ $groupName }}')">
            <div class="collapse-title" @click="$store.ledgerState.toggle('{{ $groupName }}')" role="button" tabindex="0">
                {{ $groupName }}
            </div>
            <!-- ... -->
        </div>
    </div>
    ```

### 3.3 構造変化（Structural Mismatch）への対応

過去のバージョンではカラム構成が異なり、グループ名が存在しなかったり、逆に現在存在しないグループがあったりするケースが発生する。

-   **方針:** 開閉状態は「グループ名（文字列キー）」に対するユーザー設定として扱う（**Advisory State**）。
-   **挙動:**
    -   **現在ある・過去にもあるグループ:** ストアの状態に従う（同期される）。
    -   **現在ある・過去にないグループ:** 過去ビューには表示されないため影響なし。
    -   **現在ない・過去にあるグループ:** ストアにキーが存在しないため、デフォルト（開く/閉じるの初期設定）に従う。
-   **懸念点:** 同名のグループだが、意味合いが変わっている場合。
    -   **対応:** グループ名はユーザー定義（台帳定義）に依存するため、同名であれば同じ設定を適用するのが自然なUXであると判断する。

---

## 4. `ShowDiff` (専用履歴画面) への対応

### 4.1 LocalStorage 連携
- `ShowDiff` 画面でも、同様に `ledgerId` を元に LocalStorage を参照することで、詳細画面での開閉状態を引き継ぐことが可能になる。
- これにより、「詳細画面でグループAを閉じた → 履歴画面に遷移 → グループAは閉じている」という一貫した体験を提供できる。

---

## 5. まとめ

| 状態 | 同期元 | 同期先 | 手法 | 備考 |
|---|---|---|---|---|
| `displayLevel` | `Show` (Parent) | `LedgerHistoryManager` | Prop (`#[Reactive]`) | 基本情報タブでの変更を即時反映 |
| `collapsedStates` | LocalStorage | 全コンポーネント | Alpine.store | ページリロード、タブ切替、画面遷移（専用画面）すべてで同期可能。 |

この設計により、サーバー負荷をかけずに高度な状態同期を実現する。
