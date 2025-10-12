# `LedgerContentProcessor` を活用した表示ロジックのリファクタリング計画 (最終版)

## 1. 背景と目的

`@docs/work/2025-08-04_ledger_show_refactoring_plan.md` に基づくリファクタリングの一環として、台帳の詳細表示における表示ロジックを集約し、コンポーネントとビューの責務を明確化するために `app/Services/Ledger/LedgerContentProcessor.php` サービスが作成された。

当初の設計思想は、台帳の `content` や `column_define`、差分情報、**添付ファイル情報**といった複雑なデータから、表示に必要なHTMLやデータ構造をサービス層で一元的に生成し、LivewireコンポーネントとBladeビューの責務を軽減することにあった。

しかし、リファクタリングの過程で、このサービスの活用が徹底されず、結果として表示ロジックが複数のコンポーネントやビューに分散・重複して存在する状況が生まれた。本計画は、この問題を解消し、当初の設計思想に沿った形でリファクタリングを完遂させることを目的とする。

## 2. 現状の課題（検討過程）

現状のコードベースを調査した結果、以下の課題が明らかになった。

1.  **`LedgerContentProcessor` が有効活用されていない:**
    *   `Show` コンポーネント (`app/Livewire/Ledger/Show.php`) で `LedgerContentProcessor` が呼び出されているものの、その実行結果である `displayColumns` プロパティは、対応するビュー (`show.blade.php`) で一切使用されていない。

2.  **表示責務の実質的な担当コンポーネント:**
    *   台帳カラムの実際の表示は、子コンポーネントである `LedgerDiffViewer` (`app/Livewire/Ledger/LedgerDiffViewer.php`) が担っている。

3.  **ロジックの重複 (DRY原則違反):**
    *   `LedgerDiffViewer` は `LedgerContentProcessor` を利用せず、表示レベルに応じたカラムのフィルタリングやグルーピングといったロジックを、`Show` コンポーネントとほぼ同様の形で**再実装**している。

4.  **ロジックのビューへの漏洩:**
    *   `LedgerDiffViewer` のビュー (`ledger-diff-viewer.blade.php`) が、`ColumnHtml` Facade を直接呼び出してカラムごとのHTMLを生成している。これにより、表示ロジックがビュー層に漏れ出し、コンポーネントとビューの責務分離が不完全な状態となっている。
    *   特に、**添付ファイル情報のセットアップ (`setAttachmentCollection`, `setAttachmentContents`) もビュー内で行われており**、ビューの責務が肥大化している。

これらの課題は、コードの可読性を下げ、将来的な仕様変更時の修正箇所を増やし、メンテナンスコストを増大させる要因となる。

## 3. リファクタリング方針

上記の課題を根本的に解決するため、**「当初の思想を徹底し、`LedgerContentProcessor` を中心としたアーキテクチャにリファクタリングを完遂する」** 方針を採択する。

### 目指すアーキテクチャ

*   **`LedgerContentProcessor` (表示ロジックの司令塔):**
    *   台帳レコード、差分情報、表示レベル、**添付ファイル情報**など、表示に必要なすべての情報を受け取る。
    *   フィルタリング、グルーピング、差分計算結果との統合、各カラムのHTML生成など、表示に関わる**すべての**複雑なロジックを実行する。
    *   最終的に、ビューがループ処理で表示するだけで済む、完成されたデータ構造を返す。
*   **`LedgerDiffViewer` (データの中継と状態管理):**
    *   ユーザー操作（差分表示の切り替えなど）を受け付け、自身の状態を管理する。
    *   `$allAttachments` などの**添付ファイル情報を準備し、`LedgerContentProcessor` に引数として渡す**ことに専念する。
    *   生成された表示用データを取得し、ビューに渡す。
*   **`ledger-diff-viewer.blade.php` (表示に専念):**
    *   ロジックを一切含まず、**`$allAttachments` のような生データも不要になる**。コンポーネントから渡されたデータを `foreach` でループし、事前に生成されたHTMLを出力するだけのシンプルなテンプレートとなる。

## 4. 具体的な作業計画

リファクタリングは以下のステップで段階的に進める。

### Step 1: `LedgerContentProcessor` の機能強化 (詳細版)

`LedgerContentProcessor` の責務を、表示に関わるすべてのロジックの集約点として再定義し、実装を強化する。

*   **依存性の注入:**
    *   `LedgerContentProcessor` のコンストラクタで、`LedgerDiffProcessor` と `ColumnHtmlService` をインジェクトするようにする。

*   **メソッドシグネチャの定義:**
    *   `processContentForDisplay` メソッドを以下のようなシグネチャで定義する。
    ```php
    public function processContentForDisplay(
        Ledger $ledgerRecord,
        ?LedgerDiff $comparisonTargetDiff,
        int $displayLevel,
        \Illuminate\Database\Eloquent\Collection $allAttachments
    ): array
    ```

*   **具体的な処理フロー:**
    1.  **差分データの取得:** `LedgerDiffProcessor` を使い、`$ledgerRecord` と `$comparisonTargetDiff` から差分情報 (`contentChanges`) を取得する。
    2.  **カラムのフィルタリング:** `$ledgerRecord->define->column_define` を、引数で受け取った `$displayLevel` に基づいてフィルタリングする。このロジックは `LedgerDiffViewer::updateGroupedColumns()` から移管する。
    3.  **カラムのグルーピング:** フィルタリング後のカラムを、グループ名で整理する。このロジックも `LedgerDiffViewer::updateGroupedColumns()` から移管する。
    4.  **最終データ構造の組み立て:** グループとカラムをループ処理し、ビューで必要な情報をすべて含んだ配列を生成する。
        *   ループ内で、各カラムの差分情報 (`status`, `current_value`, `old_value`) を `contentChanges` から取得する。
        *   **HTMLの生成:** `ColumnHtmlService` を呼び出して、`current_value_html` と `old_value_html` を生成する。この際、**`setAttachmentCollection()` と `setAttachmentContents()` に相当する処理をサービス内で実行し、添付ファイル情報を正しく渡す。**

*   **出力データ構造の定義:**
    *   ビューで直接利用可能な、以下の情報を含む多次元配列を返す。
    ```php
    [
        [
            'group_name' => 'グループ名',
            'is_required_group' => true, // 必須カラムを含むグループか
            'columns' => [
                [
                    'id' => 'column-id-1',
                    'name' => 'カラム名',
                    'hint' => 'カラムのヒント',
                    'is_required' => true,
                    'status' => 'modified', // 'added', 'deleted', 'unchanged'
                    'current_value_html' => '...', // 生成済みHTML
                    'old_value_html' => '...',     // 生成済みHTML
                ],
                // ... more columns
            ]
        ],
        // ... more groups
    ]
    ```

### Step 2: `LedgerDiffViewer` コンポーネントのリファクタリング

`LedgerDiffViewer.php` から表示ロジックを剥ぎ取り、`LedgerContentProcessor` の呼び出しに置き換える。

1.  `updateGroupedColumns` メソッドと、それに類するフィルタリング・グルーピングのロジックをすべて削除する。
2.  `render` メソッド内で `LedgerContentProcessor` を呼び出す。その際、`prepareContentDiff()` で事前に準備しておいた添付ファイル情報 (`$this->allAttachments`) を引数として渡す。
3.  Step 1で定義した完成形のデータ構造を取得し、publicプロパティに格納してビューに渡す。

### Step 3: `ledger-diff-viewer.blade.php` ビューのクリーンアップ

ビューからロジックを完全に排除する。

1.  `{!! ColumnHtml::... !!}` のような Facade 呼び出しをすべて削除する。これにより、**ビューは `$allAttachments` を受け取る必要がなくなります。**
2.  コンポーネントから渡された加工済みのデータ構造を `foreach` で二重にループする。
3.  `{!! $column['current_value_html'] !!}` のように、事前に生成されたHTMLを出力するだけのシンプルな構造に変更する。

### Step 4: 親コンポーネント `Show.php` のクリーンアップ

リファクタリングの最終段階として、親コンポーネントに残った不要なコードを削除する。

1.  `render` メソッド内の `LedgerContentProcessor` の呼び出しを削除する。
2.  不要になったプロパティ (`$displayColumns`, `$filteredColumns`, `$groupedColumns` など) を完全に削除する。

### Step 5: テストの修正と確認

リファクタリングによるデグレードを防ぐため、テストを慎重に修正・実行する。

1.  **`LedgerContentProcessor` のユニットテスト:** 新しく移管された複雑なロジック（フィルタリング、グルーピング、HTML生成、**添付ファイル処理**）を網羅するユニットテストを重点的に作成・強化する。
2.  **`LedgerDiffViewer` のフィーチャーテスト:** コンポーネントが `LedgerContentProcessor` を正しく呼び出し、返されたデータをビューに渡していることを確認するようにテストを修正する。ビューのHTML構造が期待通りであることもアサートする。
3.  **`Show` のフィーチャーテスト:** Step 4で削除したプロパティに関するアサーションを削除する。
4.  **最終確認:** `vendor/bin/sail pest` を実行し、すべてのテストがパスすることを確認する。

## 5. 期待される効果

このリファクタリングにより、以下の効果が期待される。

*   **責務の明確化:** 表示ロジックが `LedgerContentProcessor` に一元化され、コンポーネントは状態管理、ビューは出力にそれぞれ専念できる。
*   **保守性の向上:** 表示仕様の変更（例: 新しいカラムタイプの追加、表示形式の変更）は `LedgerContentProcessor` の修正のみで対応可能になる。
*   **コードのDRY化:** `Show.php` と `LedgerDiffViewer.php` に存在したロジックの重複が解消される。
*   **テスト容易性の向上:** 複雑な表示ロジックを、依存関係の少ないサービスクラスのユニットテストとして堅牢に検証できるようになる。
