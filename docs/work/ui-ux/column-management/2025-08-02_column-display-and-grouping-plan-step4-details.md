# 台帳表示・入力UIの改善計画 - ステップ4 詳細（改訂履歴あり）

## 1. 概要

本ドキュメントは、`docs/work/2025-08-02_column-display-and-grouping-plan.md` に記載された「台帳表示・入力UIの改善計画」における「ステップ4: 入力フォーム/詳細表示画面の改修」の詳細設計を記述するものです。

当初は「グループ化」と「表示レベル制御」の同時実装を計画していましたが、実装の複雑化による問題が発生したため、**「ステップ4.1: 表示レベルの制御」を先行して実装**し、安定性を確保した上で次のステップに進む方針に修正されました。

## 2. 基本方針

「重要度（表示レベル）」と「グループ化」の概念を台帳のUIに適用します。これにより、情報過多な画面を整理し、ユーザーがより直感的かつ効率的に情報を閲覧・入力できるようにします。実装は段階的に行い、まずグループ化機能を各画面に導入し、その後表示レベル制御を適用します。

## 3. 実装計画

### **ステップ4.1: 詳細画面 (`Show`) の改修（表示レベル対応） (完了済み)**

*   **目的:**
    台帳の詳細画面において、表示レベル（概要、標準、詳細）に応じて表示されるカラムを動的に制御する機能を追加します。利用者は表示レベルを切り替えることで、情報の粒度を調整できるようになります。

*   **対象ファイル:**
    *   `app/Livewire/Ledger/Show.php` (Livewireコンポーネント)
    *   `resources/views/livewire/ledger/show.blade.php` (Bladeビュー)
    *   `app/Services/Ledger/ColumnHtmlService.php` (HTML生成サービス)
    *   `resources/views/components/ledger/detail/table.blade.php` (Bladeコンポーネント)

*   **最終的な設計と実装:**
    1.  **Livewireコンポーネント (`Show.php`) の改修:**
        *   `displayLevel` プロパティを `#[Url]` 属性付きで追加し、表示レベルの状態をURLで管理します。
        *   **`render()` メソッド内でカラムのフィルタリングを実行:** Livewireの `Property type not supported` エラーを回避するため、フィルタリングしたカラムの配列を `public` プロパティとして保持するのではなく、`render()` メソッド内で計算し、直接ビューに渡す設計としました。
        *   `setDisplayLevel()` メソッドで `$displayLevel` プロパティを更新し、コンポーネントの再レンダリングをトリガーします。

    2.  **Bladeビュー (`show.blade.php`) の改修:**
        *   詳細情報カードのメニュー部分に、MaryUIの `<x-mary-group>` コンポーネントを使用して表示レベル切り替えボタンを設置しました。（当初 `<x-mary-button-group>` を使用してエラーが発生したため修正）
        *   差分表示がない場合は `<x-ledger.detail.table>` コンポーネントに、差分表示がある場合はテーブルのループ内で、それぞれフィルタリング済みのカラム情報を渡すように修正しました。

    3.  **関連コンポーネントとサービスの修正:**
        *   **`ColumnHtmlService` の堅牢性向上:** `render` メソッドでフィルタリングした結果、カラム定義が `ColumnDefine` オブジェクトではなく連想配列になるケースがありました。これが原因で `AutoLinkService` で型エラーが発生したため、`ColumnHtmlService` の `show` メソッドの冒頭で、引数が配列だった場合に `new ColumnDefine()` でオブジェクトに変換する処理を追加し、問題を解決しました。
        *   **`x-ledger.detail.table` の改修:** フィルタリングされたカラム配列（`$filteredColumns`）を受け取れるようにpropsを修正し、`data_get()` ヘルパを使用して配列・オブジェクト両方の形式に対応できるようにしました。

*   **実装経緯と問題解決のサマリー:**
    *   **課題1: Livewireのプロパティ型エラー**
        *   **現象:** フィルタリングしたカラム配列（複雑な多次元配列）を `public` プロパティに保持したところ、`Property type not supported` エラーが発生。
        *   **解決策:** `public` プロパティで状態を保持するのをやめ、`render()` メソッド内でフィルタリング処理を行い、結果を直接ビューに渡す方式に変更しました。これは、Livewireで複雑なデータを扱う際の基本的な設計パターンです。
    *   **課題2: 差分表示の不具合**
        *   **現象:** 更新履歴があるにも関わらず、差分表示の切り替えトグルが表示されない。
        *   **原因:** 差分検出ロジック (`findComparisonTargetDiff`) が、誤ってワークフロー機能の有効/無効状態に依存していたため、ワークフロー無効の台帳で差分が検出されませんでした。また、カラムが削除されたケースを考慮できていませんでした。
        *   **解決策:** ワークフロー有効化のチェックを削除し、差分検出ロジックを「現在と過去の両方のカラム定義をマージして比較する」ように修正しました。
    *   **課題3: サービス層での型エラー**
        *   **現象:** `AutoLinkService` で `ColumnDefine` オブジェクトを期待する箇所に配列が渡され、TypeErrorが発生。
        *   **原因:** `render()` メソッドでフィルタリングした結果が、オブジェクトではなく連想配列になっていたため。
        *   **解決策:** データを直接利用するサービス (`ColumnHtmlService`) 側で責任を持つのが適切と判断し、`show()` メソッドの入り口でデータ型をチェックし、配列であれば `ColumnDefine` オブジェクトに変換する処理を追加しました。
    *   **課題4: 差分表示UIの消失**
        *   **現象:** グループ化UIの導入後、差分表示を切り替えるトグルスイッチが画面から消滅した。
        *   **原因:** グループ化UIの導入時に、差分表示トグルが含まれるBladeのブロック全体を置き換えてしまったため。
        *   **解決策:** `resources/views/livewire/ledger/show.blade.php` 内の適切な位置に、`$hasChangedColumns` の条件付きで `x-mary-toggle` コンポーネントを再挿入した。
    *   **課題5: 差分表示がデフォルトで無効**
        *   **現象:** 差分が検出されても、差分表示がデフォルトで有効にならず、手動でトグルを切り替える必要があった。
        *   **原因:** `showChanges` プロパティの初期値が `false` であったため。
        *   **解決策:** `app/Livewire/Ledger/Show.php` の `mount()` メソッド内で、`prepareContentDiff()` 実行後に `hasChangedColumns` が `true` の場合、`showChanges` を `true` に設定するロジックを追加した。

*   **成果物:**
    *   `app/Livewire/Ledger/Show.php` の修正。
    *   `resources/views/livewire/ledger/show.blade.php` の修正。
    *   `app/Services/Ledger/ColumnHtmlService.php` の修正。
    *   `resources/views/components/ledger/detail/table.blade.php` の修正。

*   **確認方法:**
    1.  台帳の詳細画面に「概要」「標準」「詳細」の切り替えボタンが表示されることを確認する。
    2.  表示レベルの切り替えに応じて、表示されるカラムが正しくフィルタリングされることを確認する。
    3.  URLに `?dl=2` のようにクエリ文字列が反映され、ページをリロードしても表示レベルが維持されることを確認する。
    4.  ワークフローの有効/無効に関わらず、更新履歴があれば「差分表示」のトグルが正しく表示され、機能することを確認する。

### **ステップ4.2: 詳細表示画面 (`Show`) のグループ化対応**

*   **目的:**
    台帳の詳細表示画面において、カラム定義で設定された「グループ名」に基づき、関連する項目をグループ化し、アコーディオン形式で表示します。これにより、情報量の多い画面の視認性を向上させ、ユーザーが目的の情報にアクセスしやすくします。これは、後の入力フォームでのグループ化機能の基礎となります。

*   **対象ファイル:**
    *   `app/Livewire/Ledger/Show.php` (Livewireコンポーネント)
    *   `resources/views/livewire/ledger/show.blade.php` (Bladeビュー)
    *   `resources/views/components/ledger/detail/table.blade.php` (Bladeコンポーネント)

*   **詳細設計:**

    #### 3.1. `app/Livewire/Ledger/Show.php` の改修

    このLivewireコンポーネントは、詳細表示画面のデータとロジックを管理します。グループ化機能の導入に伴い、以下の変更を加えます。

    *   **プロパティの追加:**
        グループの開閉状態を管理するために、`public array $collapsedGroups = [];` プロパティを追加します。この配列には、現在折りたたまれているグループの名前が格納されます。Livewireのリアクティブなプロパティとして定義することで、UIからの操作で状態が自動的に同期されます。

    *   **メソッドの追加:**
        ユーザーがグループのヘッダーをクリックした際に、そのグループの開閉状態を切り替える `public function toggleGroup(string $groupName): void` メソッドを実装します。このメソッドは、`$collapsedGroups` 配列から指定されたグループ名を追加または削除することで、状態を更新します。

    *   **`mount()` メソッドの改修:**
        コンポーネントの初期化時に、`$collapsedGroups` プロパティを適切に初期化します。
        **方針:** 全ての名前付きグループをデフォルトで折りたたんだ状態とし、ただし、**必須項目（`required` が `true`）を含むグループは初期状態で展開する**ようにします。これにより、ユーザーは重要な情報を見落とすことなく、かつ画面が情報過多になるのを防ぎます。
        実装としては、まず全てのユニークなグループ名を取得し、それらを `$collapsedGroups` に設定して全て折りたたまれた状態にします。その後、`column_define` をループし、`required` なカラムが見つかった場合、そのカラムが属するグループを `$collapsedGroups` から削除して展開状態にします。

    *   **`render()` メソッドの改修:**
        このメソッドは、ビューに渡すデータを準備します。
        **方針:** 既存の `displayLevel` によるカラムフィルタリング処理は維持しつつ、フィルタリングされたカラムを「グループ名」でさらにグループ化します。
        `column_define` の各カラムには `group` プロパティ（文字列）が設定されています。このプロパティを利用して、関連するカラムを論理的なグループにまとめます。`group` が `null` または空文字列のカラムは、「その他」といったデフォルトのグループとして扱います。
        グループ化された結果は、`groupedColumns` という変数としてビューに渡されます。これにより、Bladeビュー側でグループごとの表示を容易に構築できます。グループの表示順序は、グループ内の最初のカラムの `order` プロパティに基づいてソートすることで、定義画面での並び順を反映させます。

    #### 3.2. `resources/views/livewire/ledger/show.blade.php` の改修

    このBladeビューは、詳細表示画面のUIをレンダリングします。グループ化機能の導入に伴い、メインコンテンツ表示エリアを大きく再構築します。

    *   **メインコンテンツ表示エリアの再構築:**
        既存の `<x-ledger.detail.table>` コンポーネントの呼び出し（および差分表示のための `if($showChanges)` ブロック）を削除し、代わりに新しいグループ化構造を直接実装します。
        **方針:** `groupedColumns` 変数（`render()` メソッドから渡される）をループし、各グループに対してMaryUIの `<x-mary-collapse>` コンポーネントを使用します。
        `<x-mary-collapse>` は、アコーディオン形式のUIを提供するコンポーネントです。`name` 属性で一意な識別子を与え、`collapsed` 属性を `$collapsedGroups` プロパティの状態とバインドすることで、開閉状態をLivewireと同期させます。
        各グループのヘッダーにはグループ名を表示し、`wire:click.prevent="toggleGroup('{{ $groupName }}')"` を設定することで、クリック時にLivewireの `toggleGroup` メソッドが呼び出され、開閉状態が切り替わるようにします。
        また、グループ内に必須項目が含まれる場合は、視覚的なインジケーター（例: 「必須項目あり」といったテキストやアイコン）を表示し、ユーザーに注意を促します。
        各グループのコンテンツエリア内では、そのグループに属するカラムをさらにループし、既存のカラム表示ロジック（`ColumnHtml` サービスを使った値の表示、`canView` や `empty` のチェック、差分表示ロジックなど）を適用します。これにより、グループ化されたUIの中でも、表示レベル制御や差分表示といった既存機能が引き続き正しく機能するようにします。

    #### 3.3. `resources/views/components/ledger/detail/table.blade.php` の改修

    *   このコンポーネントは、`show.blade.php` でのグループ化ロジックによって置き換えられるため、**使用されなくなります**。したがって、このファイル自体への変更は不要です。将来的に削除を検討しても良いでしょう。

*   **成果物:**
    *   `app/Livewire/Ledger/Show.php` の修正。
    *   `resources/views/livewire/ledger/show.blade.php` の修正。
    *   `lang/ja/ledger.php` に `group_default` と `required_group_indicator` の翻訳キーを追加。

*   **確認方法:**
    1.  台帳詳細画面にアクセスし、項目が「グループ名」に基づいてセクションに分けられ、それぞれが折りたたみ可能なパネルとして表示されることを確認する。
    2.  各グループのヘッダーをクリックすると、そのグループの内容がスムーズに開閉することを確認する。
    3.  カラム定義で「必須」と設定されている項目を含むグループが、初期表示時に自動的に展開されていることを確認する。
    4.  表示レベル（概要、標準、詳細）を切り替えた際に、グループ化された構造が維持されつつ、各グループ内に表示されるカラムが正しくフィルタリングされることを確認する。
    5.  「差分表示」のトグルを操作した際に、グループ化されたUIの中で、変更されたカラムが正しくハイライトされ、新旧の値が比較表示されることを確認する。
    6.  `group` プロパティが設定されていないカラムが、「その他」といったデフォルトのグループ名で表示されることを確認する。


### **ステップ4.3: 入力フォーム (`CreateColumn`, `ModifyColumn`) のグループ化対応 (完了済み)**

*   **目的:**
    詳細表示画面で実装したグループ化機能を、**台帳の新規作成・編集フォーム**にも適用します。これにより、入力項目が業務の流れに沿って整理され、ユーザーは目的の項目を素早く見つけられるようになり、入力作業の効率と正確性が向上します。特に、多数の項目を持つ複雑な台帳において、入力時の認知負荷を軽減し、入力ミスや漏れを防ぐことが本ステップの重要な目的です。

*   **設計方針:**
    *   **継承の活用:** `CreateColumn` (親) と `ModifyColumn` (子) という既存のクラス構造を活かし、共通となるグループ化ロジックは親クラス `CreateColumn` に実装します。これにより、コードの重複を避け、保守性を高めます。
    *   **先行実装の踏襲:** 先行して実装し、安定動作が確認されている詳細表示画面 (`Show.php`) の設計（プロパティ名、メソッド名、グループ化ロジック）を可能な限り踏襲します。これにより、開発効率を高め、UI/UXの一貫性を保ちます。
    *   **ユーザー中心のデフォルト動作:** 詳細表示画面と同様に、必須項目を含むグループは初期状態で展開し、それ以外のグループは折りたたんでおきます。これにより、ユーザーは重要な項目を見落とすことなく、かつ画面の初期表示が情報過多になるのを防ぎます。

*   **対象ファイル:**
    *   `app/Livewire/Ledger/CreateColumn.php` (親コンポーネント)
    *   `app/Livewire/Ledger/ModifyColumn.php` (子コンポーネント)
    *   `resources/views/livewire/ledger/create-column.blade.php` (Bladeビュー)
    *   `resources/views/livewire/ledger/modify-column.blade.php` (Bladeビュー)
    *   `resources/views/livewire/ledger-define/modify-column.blade.php` (Bladeビュー)

*   **最終的な設計と実装:**

    #### 1. `app/Livewire/Ledger/CreateColumn.php` (親クラス) の改修

    入力フォームの共通ロジックを持つこの親クラスに、グループ化のコア機能を追加しました。

    *   **プロパティの追加:**
        *   グループの開閉状態を管理するため、`Show.php` と同様の `public array $collapsedStates = [];` プロパティを追加しました。
    *   **メソッドの追加:**
        *   グループの開閉状態を切り替える `public function toggleGroup(string $groupName): void` メソッドを実装しました。
    *   **グループ初期化ロジックの共通化:**
        *   `mount()` から呼び出される `protected function initializeGroups(): void` メソッドを新設し、全てのユニークなグループ名を取得し、必須項目を含むグループを初期状態で展開、それ以外を折りたたむように設定しました。
    *   **`mount()` メソッドの改修:**
        *   `mount()` の最後に、`$this->initializeGroups();` を呼び出す処理を追加しました。
    *   **`render()` メソッドの改修:**
        *   `Show.php` と同様に、カラム定義を `group` プロパティでグループ化し、`order` プロパティでソートした結果を `$groupedColumns` としてビューに渡すロジックを追加しました。

    #### 2. `app/Livewire/Ledger/ModifyColumn.php` (子クラス) の改修

    *   **`mount()` メソッドの改修:**
        *   `ModifyColumn` の `mount` メソッドの最後に、親クラスから継承した `$this->initializeGroups();` を呼び出す処理を追加しました。
    *   **`render()` メソッドの改修:**
        *   親クラスの `render()` をオーバーライドし、`modify-column` ビューを返しつつ、グループ化されたカラムを渡すロジックを再利用するようにしました。

    #### 3. Bladeビューの改修 (`create-column.blade.php` / `modify-column.blade.php`)

    両方のビューファイルを、詳細表示画面（`show.blade.php`）と同様の二重ループ構造に書き換えました。

    *   **構造の変更:**
        1.  既存の `@foreach($ledgerDefineRecord->column_define ...)` ループを削除しました。
        2.  代わりに、`render()` メソッドから渡される `$groupedColumns` をループ処理し（外側ループ）、各グループに対して `<div class="collapse ...">` コンポーネントを配置しました。
        3.  `wire:click` で `toggleGroup` メソッドを呼び出すことで開閉を制御します。
        4.  `collapse-content` の中で、そのグループに属するカラム (`$columnsInGroup`) をループし（内側ループ）、既存の各入力フォームコンポーネントを配置しました。

    #### 4. 不具合修正：背景画像の切り替え

    *   **現象:** グループ化対応後、入力項目にキーボードでフォーカスしても、その項目に設定された背景画像に切り替わらなくなりました。
    *   **原因:** UI構造がアコーディオン形式に変わったことで、Alpine.jsがキーボードのフォーカスイベント (`focusin`) を正しく検知できていませんでした。
    *   **解決策:**
        *   `create-column.blade.php` と `modify-column.blade.php`、`ledger-define/modify-column.blade.php` の3つのビューファイルにおいて、各入力項目を囲む `div` または `x-mary-collapse` に `x-on:focusin="updateBackground(...)` を追加し、フォーカス時にも背景画像更新関数が呼ばれるようにしました。
        *   同時に `focus-within:opacity-100` クラスを追加し、フォーカスが当たっているグループ全体の透明度を100%にして視認性を向上させました。
        *   `CreateColumn.php` の `initBackgroundImages()` メソッド内で、背景画像のパスをオブジェクト (`$value->path`) ではなく配列 (`$value['path']`) として正しく参照するように修正しました。

*   **成果物:**
    *   `app/Livewire/Ledger/CreateColumn.php` の修正。
    *   `app/Livewire/Ledger/ModifyColumn.php` の修正。
    *   `resources/views/livewire/ledger/create-column.blade.php` の修正。
    *   `resources/views/livewire/ledger/modify-column.blade.php` の修正。
    *   `resources/views/livewire/ledger-define/modify-column.blade.php` の修正。

*   **確認方法:**
    *   台帳の作成・編集画面で、入力項目がグループごとに折りたたまれて表示されることを確認する。
    *   必須項目を含むグループが、初期表示で展開されていることを確認する。
    *   各グループのヘッダーをクリックすると、そのグループの内容がスムーズに開閉することを確認する。
    *   全ての入力、バリデーション、保存、ファイルアップロード機能が、グループ化されたUIの中でも従来通り正しく動作することを確認する。
    *   マウスオーバーだけでなく、キーボードのTabキーなどで入力項目にフォーカスを移動した際にも、対応する背景画像が正しく表示されることを確認する。

### **ステップ4.4: 入力フォーム (`CreateColumn`, `ModifyColumn`) の表示レベル制御対応**

*   **目的:**
    グループ化が実装された入力フォームに、表示レベル（概要、標準、詳細）の切り替え機能を追加します。
*   **対象ファイル:**
    *   `app/Livewire/Ledger/CreateColumn.php`
    *   `resources/views/livewire/ledger/create-column.blade.php`
    *   `app/Livewire/Ledger/ModifyColumn.php`
    *   `resources/views/livewire/ledger/modify-column.blade.php`
*   **実装タスク:**
    1.  **`CreateColumn.php` の改修:**
        *   `#[Url]` 属性を付けた `public int $displayLevel = 1;` プロパティと、`setDisplayLevel` メソッドを追加します。
        *   `render()` メソッド内のカラム取得ロジックに、`displayLevel` に基づくフィルタリング処理を追加します。（グループ化の処理は既存）
    2.  **`create-column.blade.php`, `modify-column.blade.php` の改修:**
        *   フォーム上部に、表示レベルを切り替えるボタン（`<x-mary-button-group>`）を設置します。
*   **確認方法:**
    *   表示レベルボタンが表示され、切り替えに応じて表示される入力グループやフィールドが動的に変わること。
    *   URLに表示レベルが反映され、リロードしても状態が維持されること。
    *   グループ化と表示レベル制御の両方が、互いに干渉せず正しく機能すること。
