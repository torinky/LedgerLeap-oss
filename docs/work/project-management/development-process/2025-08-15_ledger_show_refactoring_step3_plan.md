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

**--- 実施結果と課題、解決策 (2025-08-15) ---**

本ステップでは、ユーザーからの指示により、`Show.php` からのプロパティやメソッドの削除は最終段階で行い、新旧の実装が二重表示される形で作業を進めました。また、3.1完了時点でUIの動作確認ができるように段階的に実装を行いました。

1.  **Livewireコンポーネントの生成:**
    *   `vendor/bin/sail artisan make:livewire Ledger/LedgerDiffViewer` を実行し、コンポーネントを生成しました。

2.  **プロパティとメソッドの移動と初期実装:**
    *   `app/Livewire/Ledger/Show.php` から差分表示関連のプロパティ (`comparisonTargetDiff`, `contentChanges`, `hasChangedColumns`, `showChanges`) とメソッド (`prepareContentDiff()`) を `app/Livewire/Ledger/LedgerDiffViewer.php` に移動しました。
    *   `LedgerDiffViewer` に `public Ledger $ledgerRecord;` プロパティと `LedgerDiffProcessor` の依存性注入を追加しました。
    *   `LedgerDiffViewer` の `mount()` メソッドで `prepareContentDiff()` を呼び出すようにしました。
    *   `Show.blade.php` に `LedgerDiffViewer` を埋め込み、元の差分表示UIはコメントアウトすることで二重表示の状態を維持しました。

3.  **発生した課題と解決策:**

    *   **課題1: `Cannot assign Illuminate\Support\Collection to property App\Livewire\Ledger\LedgerDiffViewer::$groupedColumns of type array`**
        *   **原因:** `LedgerDiffViewer.php` の `groupedColumns` プロパティが `array` 型で宣言されていたにもかかわらず、`collect()` ヘルパー関数が返す `Illuminate\Support\Collection` 型の値を代入しようとしたため発生しました。
        *   **解決策:** `groupedColumns` プロパティの型を `Illuminate\Support\Collection` に変更し、初期値を `null` に設定しました。また、`Illuminate\Database\Eloquent\Collection` との混同を避けるため、`EloquentCollection` としてエイリアスを付与しました。

    *   **課題2: `New expressions are not supported in this context`**
        *   **原因:** Livewire のプロパティの初期値として `new Collection()` のように `new` キーワードを使用しようとしたため発生しました。Livewire のプロパティは、宣言時に複雑なオブジェクトを `new` で初期化することをサポートしていません。
        *   **解決策:** `groupedColumns` プロパティの初期値を `null` に変更し、`mount` メソッド内で `collect()` の結果を代入するようにしました。

    *   **課題3: `Call to undefined method App\Models\ColumnDefine::toArray()`**
        *   **原因:** `LedgerDiffViewer` の `mount` メソッド内で `groupedColumns` を生成する際に、`ColumnDefine` オブジェクトを配列に変換するために `toArray()` メソッドを呼び出しましたが、`ColumnDefine` は Eloquent モデルではないため、このメソッドを持っていませんでした。
        *   **解決策:** `ColumnDefine` オブジェクトから必要なプロパティを手動で抽出し、連想配列を作成するように `map` メソッド内のロジックを修正しました。これは `Show.php` の `calculateFilteredColumns` メソッドで行われていた処理と同様です。

    *   **課題4: 「表示レベルの調整が効かない」**
        *   **原因:** `Show.php` の `displayLevel` が変更されても、子コンポーネントである `LedgerDiffViewer` の `displayLevel` プロパティが自動的に更新されないためでした。Livewire の子コンポーネントにプロパティを渡す場合、親のプロパティが更新されても子は自動的に更新されません。
        *   **解決策:** `Show.php` の `updatedDisplayLevel` および `setDisplayLevel` メソッド内で `displayLevelUpdated` イベントを発火させ、`LedgerDiffViewer` がこのイベントをリッスンして自身の `displayLevel` を更新し、`updateGroupedColumns` を再実行するようにしました。

    *   **課題5: 「グループ化のアコーディオンの反応が遅い」**
        *   **原因:** アコーディオンの開閉状態 (`collapsedStates`) の管理が親 (`Show.php`) と子 (`LedgerDiffViewer`) の間でイベント通信を介して行われていたため、オーバーヘッドによる遅延が発生していました。
        *   **解決策:** `collapsedStates` と `toggleGroup` メソッドの管理を `LedgerDiffViewer` 自身で行うように変更しました。これにより、親とのイベント通信が不要になり、反応速度が改善されました。`Show.php` から `collapsedStates` を渡すプロパティバインディングも削除しました。

**--- 実施結果と課題、解決策 (2025-08-15) 終了 ---**

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

**--- 実施結果と提案 (2025-08-15) ---**

Step 3.1 の作業中に、既に Step 3.2 の「UIの分離」と「`Show.blade.php` への埋め込み」は実施済みです。ただし、ユーザーの指示により、`Show.blade.php` の元の差分表示UIはコメントアウトされ、新旧の二重表示が維持されていました。

**実施結果:**

1.  **古いUIの削除:** `resources/views/livewire/ledger/show.blade.php` から、コメントアウトされていた古い差分表示UIを完全に削除しました。
2.  **遅延ロード (Lazy Loading) の適用:** `resources/views/livewire/ledger/show.blade.php` の `LedgerDiffViewer` コンポーネントの埋め込みに `lazy` 修飾子を追加しました。これにより、初期ロード時のパフォーマンスが向上し、ユーザーが「詳細」タブを選択し、かつ差分表示が有効な場合にのみ `LedgerDiffViewer` がロードされるようになりました。
    *   **ユーザーからのフィードバック:** レイジーロードは一瞬で表示されるため効果が分かりにくかったものの、レイジーロードしている挙動は確認できました。
3.  **プレースホルダーの実装:** `app/Livewire/Ledger/LedgerDiffViewer.php` に `placeholder()` メソッドを追加しました。シンプルなローディングメッセージを表示するように実装しました。

**--- 実施結果と提案 (2025-08-15) 終了 ---**

### Step 3.3: テストの作成と実行

1.  **新規テストの作成:**
    *   `tests/Feature/Livewire/Ledger/LedgerDiffViewerTest.php` を作成し、`LedgerDiffViewer` コンポーネントの機能（差分計算の正確性、表示状態の制御など）を検証するフィーチャーテストを実装します。
    *   `LedgerDiffProcessor` サービスは既にユニットテストで検証済みであるため、ここではコンポーネントとしての統合と表示に焦点を当てます。

2.  **既存テストの実行:**
    *   `vendor/bin/sail pest tests/Feature/Livewire/Ledger/ShowTest.php` を実行し、既存のフィーチャーテストがパスすることを確認します。これにより、`Show.php` からのロジックとUIの分離が既存機能に影響を与えていないことを検証します。

**--- 実施結果と課題、解決策 (2025-08-17) ---**

本ステップでは、`LedgerDiffViewer` コンポーネントの機能が正しく動作することを保証するためのテストを実装し、既存の `Show.php` のテストがリファクタリングによる影響を受けていないことを確認しました。

1.  **新規テストの作成:**
    *   `tests/Feature/Livewire/Ledger/LedgerDiffViewerTest.php` を新規作成し、以下のテストケースを実装しました。
        *   `component_mounts_and_renders_grouped_columns_correctly()`: コンポーネントがマウントされ、グループ化されたカラムが正しくレンダリングされることを確認。
        *   `it_filters_columns_by_display_level()`: 表示レベルによるカラムのフィルタリングが正しく行われることを確認。
        *   `it_correctly_displays_diffs_including_deleted_columns()`: 削除されたカラムを含む差分が正しく表示されることを確認。このテストは、`LedgerDiffProcessor` サービスが内部的に呼び出され、差分計算ロジックが正しく機能していることを間接的に検証しています。
    *   これらのテストはすべてパスしました。

2.  **既存テストの実行と修正:**
    *   `vendor/bin/sail pest tests/Feature/Livewire/Ledger/ShowTest.php` を実行したところ、当初 `it_prepares_content_diff_correctly()` と `it_finds_comparison_target_diff_correctly()` の2つのテストケースが失敗しました。
    *   **原因:** これらのテストケースは、`Show.php` コンポーネントが差分計算ロジックを保持していることを前提としていましたが、このロジックはリファクタリングによって `LedgerDiffViewer` コンポーネントに完全に移管されたため、`Show.php` には存在しなくなっていました。
    *   **解決策:**
        *   `ShowTest.php` から、移管済みのロジックに関するテストケース (`it_prepares_content_diff_correctly`, `it_finds_comparison_target_diff_correctly`) を削除しました。これらの機能は `LedgerDiffViewerTest.php` で網羅されていることを確認済みです。
        *   `Show.php` の `boot` メソッド内に残っていた、削除済みの `LedgerDiffProcessor` プロパティへの代入行 (`$this->ledgerDiffProcessor = $ledgerDiffProcessor;`) を削除しました。
    *   これらの修正後、`ShowTest.php` のすべてのテストがパスすることを確認しました。

**--- 実施結果と課題、解決策 (2025-08-17) 終了 ---**

### Step 3.4: `Show` 親コンポーネントのクリーンアップ

1.  **プロパティとメソッドの削除:**
    *   `app/Livewire/Ledger/Show.php` から、`LedgerDiffViewer` コンポーネントに移動した差分表示関連のプロパティ（`comparisonTargetDiff`, `contentChanges`, `hasChangedColumns`, `showChanges`）とメソッド（`prepareContentDiff()`）を完全に削除します。
    *   `boot` メソッドから `LedgerDiffProcessor` の依存性注入を削除します。

**--- 実施結果と課題、解決策 (2025-08-17) ---**

本ステップでは、`Show.php` コンポーネントから、`LedgerDiffViewer` に移管された差分表示関連のコードを完全に削除し、親コンポーネントの責務をさらに明確化しました。

1.  **プロパティの削除:**
    *   `app/Livewire/Ledger/Show.php` から、以下のプロパティを完全に削除しました。
        *   `public ?LedgerDiff $comparisonTargetDiff = null;`
        *   `public array $contentChanges = [];`
        *   `public bool $hasChangedColumns = false;`
        *   `public bool $showChanges = false;`
2.  **メソッドの削除:**
    *   `app/Livewire/Ledger/Show.php` から、`protected function prepareContentDiff(): void` メソッドを完全に削除しました。
3.  **依存性注入の削除:**
    *   `app/Livewire/Ledger/Show.php` の `boot` メソッドの引数から `LedgerDiffProcessor $ledgerDiffProcessor` を削除しました。
    *   `protected LedgerDiffProcessor $ledgerDiffProcessor;` のプロパティ宣言も削除しました。
    *   `mount()` メソッド内に残っていた `prepareContentDiff()` の呼び出しも削除しました。

**--- 実施結果と課題、解決策 (2025-08-17) 終了 ---**

## 3. 期待される成果物

*   台帳レコードの変更差分表示機能が `LedgerDiffViewer` という独立したLivewire子コンポーネントに分離され、`Show.php` から差分表示に関するコードが削減され、見通しが改善された状態。
*   差分表示機能が独立して管理・開発できるようになった状態。

*   テストに裏付けられた、安全なリファクタリングの完了。

## 4. 考慮事項

*   **パフォーマンス:** `lazy` 修飾子の適用は、初期ロード時のパフォーマンス向上に寄与しますが、ユーザーがタブを切り替える際にわずかな遅延が発生する可能性があります。ユーザー体験とパフォーマンスのバランスを考慮して最終決定します。
*   **データフロー:** `LedgerRecord` は親から子へプロパティとして渡されますが、差分表示のトリガー（例: `LedgerRecord` の更新）が必要な場合は、Livewire イベント (`$this->dispatch()`) を使用して親から子へ通知するメカニズムを検討します。ただし、`Show.php` の `refreshLedgerRecord` が `mount` を呼び出すため、`LedgerDiffViewer` も再マウントされ、自動的に更新されるはずです。
*   **二重表示の維持:** 今回の作業では、ユーザーの指示により新旧の二重表示を維持しました。最終的なクリーンアップステップで、古いコードを削除する必要があります。
