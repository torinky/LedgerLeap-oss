# `app/Livewire/Ledger/Show.php` リファクタリング計画 - Step 3: `LedgerDiffViewer` コンポーネントの分離

## 1. 目的

`app/Livewire/Ledger/Show.php` コンポーネントから台帳レコードの変更差分表示に関するUIとロジックを抽出し、`LedgerDiffViewer` という独立したLivewire子コンポーネントに分離します。これにより、`Show.php` の責務をさらに軽減し、差分表示機能を独立して管理・開発できるようにします。

## 2. 詳細計画

### Step 3.1: `LedgerDiffViewer` Livewireコンポーネントの作成とロジックの移動

1.  **Livewireコンポーネントの生成:**
    *   `vendor/bin/sail artisan make:livewire Ledger/LedgerDiffViewer` コマンドを実行し、`app/Livewire/Ledger/LedgerDiffViewer.php` と `resources/views/livewire/ledger/ledger-diff-viewer.blade.php` を作成します。

2.  **プロパティとメソッドの移動:**
    *   `app/Livewire/Ledger/Show.php` から、差分表示に関連する以下のプロパティとメソッドを `app/Livewire/Ledger/LedgerDiffViewer.php` に移動します。
        *   **プロパティ:** `comparisonTargetDiff`, `contentChanges`, `hasChangedColumns`, `showChanges`
        *   **メソッド:** `prepareContentDiff()`
    *   `LedgerDiffViewer` コンポーネントは、親から渡される `LedgerRecord` を受け取るための `public Ledger $ledgerRecord;` プロパティを定義します。
    *   `LedgerDiffProcessor` サービスをコンストラクタまたは `mount` メソッドで依存注入します。

3.  **`LedgerDiffProcessor` サービスの利用:**
    *   移動した `prepareContentDiff()` メソッド内で、`LedgerDiffProcessor` サービスを利用して差分計算を行います。`Show.php` で既に `LedgerDiffProcessor` が注入され、`prepareContentDiff` が呼び出されているため、このロジックをそのまま移動し、`$this->ledgerRecord` を使用するように調整します。

4.  **初期化ロジックの調整:**
    *   `LedgerDiffViewer` の `mount()` メソッド内で `prepareContentDiff()` を呼び出し、コンポーネントがロードされた際に差分情報が準備されるようにします。

### Step 3.2: ビューの分離と `Show` への埋め込み

1.  **UIの分離:**
    *   `resources/views/livewire/ledger/show.blade.php` から、差分表示に関連するHTML構造（通常、「詳細」タブ内に存在する差分表示エリア）を `resources/views/livewire/ledger/ledger-diff-viewer.blade.php` にコピーします。
    *   `Show.php` のビューから移動したUI要素が完全に削除されたことを確認します。

2.  **`Show.blade.php` への埋め込み:**
    *   `resources/views/livewire/ledger/show.blade.php` の元の差分表示UIがあった場所に、新しく作成した `LedgerDiffViewer` コンポーネントを埋め込みます。
        *   例: `<livewire:ledger.ledger-diff-viewer :ledgerRecord="$ledgerRecord" wire:key="diff-viewer-{{ $ledgerRecord->id }}" />`
    *   **遅延ロード (Lazy Loading) の検討:**
        *   `Show.php` の「詳細」タブで、かつ差分表示が有効な場合にのみロードされるように、`lazy` 修飾子を適用することを検討します。
        *   例: `<livewire:ledger.ledger-diff-viewer :ledgerRecord="$ledgerRecord" wire:key="diff-viewer-{{ $ledgerRecord->id }}" lazy />`
        *   `lazy` を使用する場合、`LedgerDiffViewer` コンポーネントの `placeholder()` メソッドを実装し、ロード中の表示を提供します。

### Step 3.3: テストの作成と実行

1.  **新規テストの作成:**
    *   `tests/Feature/Livewire/Ledger/LedgerDiffViewerTest.php` を作成し、`LedgerDiffViewer` コンポーネントの機能（差分計算の正確性、表示状態の制御など）を検証するフィーチャーテストを実装します。
    *   `LedgerDiffProcessor` サービスは既にユニットテストで検証済みであるため、ここではコンポーネントとしての統合と表示に焦点を当てます。

2.  **既存テストの実行:**
    *   `vendor/bin/sail pest tests/Feature/Livewire/Ledger/ShowTest.php` を実行し、既存のフィーチャーテストがパスすることを確認します。これにより、`Show.php` からのロジックとUIの分離が既存機能に影響を与えていないことを検証します。

### Step 3.4: `Show` 親コンポーネントのクリーンアップ

1.  **プロパティとメソッドの削除:**
    *   `app/Livewire/Ledger/Show.php` から、`LedgerDiffViewer` コンポーネントに移動した差分表示関連のプロパティ（`comparisonTargetDiff`, `contentChanges`, `hasChangedColumns`, `showChanges`）とメソッド（`prepareContentDiff()`）を完全に削除します。
    *   `boot` メソッドから `LedgerDiffProcessor` の依存性注入を削除します。

## 3. 期待される成果物

*   台帳レコードの変更差分表示機能が `LedgerDiffViewer` という独立したLivewire子コンポーネントに分離され、`Show.php` から差分表示に関するコードが削減され、見通しが改善された状態。
*   差分表示機能が独立して管理・開発できるようになった状態。
*   テストに裏付けられた、安全なリファクタリングの完了。

## 4. 考慮事項

*   **パフォーマンス:** `lazy` 修飾子の適用は、初期ロード時のパフォーマンス向上に寄与しますが、ユーザーがタブを切り替える際にわずかな遅延が発生する可能性があります。ユーザー体験とパフォーマンスのバランスを考慮して最終決定します。
*   **データフロー:** `LedgerRecord` は親から子へプロパティとして渡されますが、差分表示のトリガー（例: `LedgerRecord` の更新）が必要な場合は、Livewire イベント (`$this->dispatch()`) を使用して親から子へ通知するメカニズムを検討します。ただし、`Show.php` の `refreshLedgerRecord` が `mount` を呼び出すため、`LedgerDiffViewer` も再マウントされ、自動的に更新されるはずです。
