# LedgerDiff 画面での添付ファイル表示修正計画 (改訂版)

## 1. 概要

LedgerDiff 画面（台帳の変更履歴詳細画面）において、添付ファイルが正しく表示されない問題を修正します。この問題は、`LedgerDiff` レコードの `content` が空の場合に、以前の `LedgerDiff` から `content` を流用するロジックが、添付ファイルのリレーションを適切に更新していないために発生していました。

## 2. 現状の問題点 (修正前)

-   `app/Livewire/Ledger/ShowDiff.php` の `loadDiffRecord` メソッドにおいて、`$this->currentDiffRecord->content` が空の場合に、空でない最新の `LedgerDiff` の `content` を `$this->ledgerRecord->content` に流用していました。
-   しかし、この流用処理では `$this->ledgerRecord` の `attachedFiles` リレーションが更新されませんでした。
-   添付ファイルの表示を担う `x-ledger.detail.table` コンポーネントは、`Ledger` モデルの `attachedFiles` リレーションに依存しているため、このリレーションが正しく設定されていないと添付ファイルが表示されませんでした。

## 3. 修正方針

1.  **`ShowDiff.php` に `setAttachedFilesFromContent` ヘルパーメソッドを導入**:
    -   `content` 配列の中から、添付ファイルのカラムに該当する要素を特定し、`hashedbasename` のリストを抽出して `AttachedFile` モデルの情報を取得するロジックをカプセル化します。
2.  **`ShowDiff.php` の `loadDiffRecord` メソッドの修正**:
    -   `$this->ledgerRecord->content` を流用する際、または `currentDiffRecord->content` を直接使用する際に、上記 `setAttachedFilesFromContent` ヘルパーメソッドを呼び出し、`$this->ledgerRecord->attachedFiles` リレーションを適切にセットします。
    -   `content` に `files` カラムが存在しない場合も考慮し、空のコレクションをセットするようにします。
3.  **`show-diff.blade.php` の修正**: 
    -   `x-ledger.detail.table` に `allAttachments` プロパティを渡し、`ColumnHtmlService` が添付ファイル情報を利用できるようにします。

## 4. 実装計画と作業結果 (ステップ・バイ・ステップ)

### ✅ ステップ 1: `ShowDiff.php` の `loadDiffRecord` メソッドに `attachedFiles` リレーションの直接設定を追加 (完了)

-   **目的**: `LedgerDiff` の `content` に基づいて `AttachedFile` リレーションを直接ロードする初期実装を行う。
-   **タスク**: 
    -   `app/Livewire/Ledger/ShowDiff.php` の `loadDiffRecord()` メソッド内で、`$latestNonEmptyDiff` から `content` を流用する `if ($latestNonEmptyDiff)` ブロック内、および `$this->currentDiffRecord->content` が直接使用される `else` ブロック内に、以下のロジックを直接追加しました。
        ```php
        // 添付ファイル情報を取得し、ledgerRecordにセット
        // contentがfilesカラムを持つ場合のみ処理
        if (isset($latestNonEmptyDiff->content['files'])) { // または $this->currentDiffRecord->content['files']
            $this->ledgerRecord->setRelation('attachedFiles', \App\Models\AttachedFile::whereIn('hashedbasename', array_keys($latestNonEmptyDiff->content['files']))->get());
        } else {
            $this->ledgerRecord->setRelation('attachedFiles', collect()); // 空のコレクションをセット
        }
        ```
    -   `ShowDiff` コンポーネントに `public ?Collection $allAttachments = null;` プロパティを追加し、`mount()` メソッドの最後に `$this->allAttachments = $this->ledgerRecord->attachedFiles->keyBy('hashedbasename');` を追加しました。
-   **作業結果**: `ShowDiff.php` の `loadDiffRecord` メソッドが修正され、`attachedFiles` リレーションが直接セットされるようになりました。しかし、この時点では `content` の構造（`files` キーの有無）に依存しており、ログから判明した実際の `content` 構造とは合致しないため、添付ファイルはまだ表示されませんでした。

### ✅ ステップ 2: `ShowDiff.php` に `setAttachedFilesFromContent` ヘルパーメソッドを導入し、`loadDiffRecord` から呼び出すようにリファクタリング (完了)

-   **目的**: 添付ファイルのリレーション設定ロジックをヘルパーメソッドにカプセル化し、`content` の実際の構造に合わせて `hashedbasename` を正しく抽出する。
-   **タスク**: 
    -   `app/Livewire/Ledger/ShowDiff.php` に `protected function setAttachedFilesFromContent(array $content): void` メソッドを新規追加しました。このメソッドは、`LedgerDefine` の `column_define` を参照し、`files` タイプのカラムの `content` から `hashedbasename` を抽出し、`AttachedFile` モデルを検索して `attachedFiles` リレーションにセットするロジックを実装しました。
    -   `loadDiffRecord()` メソッド内の、ステップ1で追加した直接の `attachedFiles` リレーション設定ロジックを、新しく作成した `setAttachedFilesFromContent($content)` の呼び出しに置き換えました。
-   **作業結果**: `ShowDiff.php` の `loadDiffRecord` メソッドが `setAttachedFilesFromContent` ヘルパーメソッドを使用するようにリファクタリングされ、`content` の実際の構造から添付ファイル情報を正しく抽出できるようになりました。これにより、添付ファイルが表示されるようになりました。

### ✅ ステップ 3: `show-diff.blade.php` を修正 (完了)

-   **目的**: 添付ファイル表示用の `x-ledger.detail.table` コンポーネントを有効にし、必要なデータを渡す。
-   **タスク**: 
    -   `resources/views/livewire/ledger/show-diff.blade.php` を開きました。
    -   `$ledgerRecord->content` が空でない場合の `x-ledger.detail.table` の呼び出しで、`allAttachments` を渡すように修正しました。
        ```blade
        @if($ledgerRecord->content)
            <x-ledger.detail.table
                    :ledgerRecord="$ledgerRecord"
                    :canView="auth()->user()->can('view', $ledgerRecord)"
                    :allAttachments="$allAttachments"
            />
        @else
            <div class="alert alert-info"><i class="fas fa-info-circle"></i>{{__('ledger.no_change_content')}}</div>
        @endif
        ```
-   **作業結果**: `show-diff.blade.php` の `x-ledger.detail.table` コンポーネントが有効になり、`allAttachments` プロパティが渡されるようになりました。

## 5. 検証

-   添付ファイルを含む台帳レコードの `ledgerDiff` 画面にアクセスします。
-   スライダーを操作して、`content` が直接記録されている `LedgerDiff` と、`content` が流用されている `LedgerDiff` の両方で添付ファイルが正しく表示されることを確認します。
-   添付ファイルの状態アイコン（処理中、失敗など）や、ダウンロードリンク、再試行ボタンが正しく機能することを確認します。
-   ファイルが添付されていない `LedgerDiff` では、添付ファイル表示エリアが表示されないことを確認します。

**修正結果**: 上記のステップにより、`ledgerDiff` 画面で添付ファイルが正しく表示されるようになりました。