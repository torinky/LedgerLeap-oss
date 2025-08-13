# 自動リンクからの台帳検索機能 実装計画 (改訂版)

## 1. 目的と背景

現状の自動リンク機能、特に自動採番カラムから生成されるリンクは、台帳一覧画面 (`/ledgers`) に遷移し、ユーザーに手動での再検索を強いる仕様となっている。文書番号のように一意性が高い情報へのリンクでは、検索結果が1件だけのリストを表示するのは冗長であり、ユーザー体験を損なう一因となっている。

本計画では、この挙動を改善し、自動リンクから遷移してきたユーザーの意図を汲み取った、よりインテリジェントな検索・表示機能を提供することを目的とする。

## 2. ユーザーシナリオと機能要件

### 2.1. ユーザーシナリオ

#### シナリオ1：一意な文書番号に完全一致した場合（理想的な体験）
*   **状況:** ユーザーは、自動採番された一意の文書番号「`SPEC-001`」のリンクをクリックする。
*   **期待される動作:** 一覧ページを挟むことなく、**直接「`SPEC-001`」の台帳詳細ページにリダイレクト**される。

#### シナリオ2：検索結果が複数または0件の場合（フォールバック処理）
*   **状況:** ユーザーは、一般的な単語（例: `報告書`）や、存在しない文書番号（`OLD-999`）のリンクをクリックする。
*   **期待される動作:** **台帳一覧ページが表示**され、検索ボックスにはクリックしたキーワードが入力された状態で、複数件の検索結果または「該当なし」のメッセージが表示される。

#### シナリオ3：表示モードで「一覧表示」を強制
*   **状況:** 管理者など、意図的に一覧で結果を確認したいユーザーが、`&mode=list` パラメータが付与されたリンク (`/l/{query}?mode=list`) をクリックする。
*   **期待される動作:** たとえ検索結果が1件であってもリダイレクトせず、**台帳一覧ページでその1件を表示**する。

### 2.2. 機能要件

上記のシナリオに基づき、機能要件を以下のように整理する。

1.  **クエリパラメータの受信:** 検索処理は、URLの `query` と `mode` パラメータを認識できる。
2.  **バックエンドでの事前検索:** `query` パラメータの値で台帳を検索する。
3.  **結果件数に基づく条件分岐:**
    *   `mode` パラメータが `list` の場合は、常に一覧ページへリダイレクトする。
    *   上記以外で、検索結果が**ちょうど1件**だった場合は、その台帳の詳細ページへリダイレクトする。
    *   検索結果が**0件または2件以上**だった場合は、一覧ページへリダイレクトする。
4.  **グローバル検索:** URLに検索キーワード (`q=...`) が含まれており、かつフォルダや台帳定義が指定されていない場合、**システムに存在する全ての台帳を対象に検索を実行**する。

## 3. 実装計画と結果

### アプローチの変更経緯

当初は、既存の台帳一覧Livewireコンポーネント (`RecordsTable.php`) の `mount()` メソッドにリダイレクト処理を実装する計画でした。しかし、このコンポーネントは既に多くの機能（絞り込み、ソート、ページネーション、権限管理等）を担っており、非常に複雑な状態でした。

実際にテストを試みたところ、コンポーネントが依存する `SearchRequest` オブジェクトの完全な模倣が困難であることに起因する、解決の難しいエラーが頻発しました。これは、コンポーネントの責務が肥大化しすぎていることを示唆しています。

そこで、よりクリーンで堅牢なアーキテクチャを目指し、以下の通りアプローチを全面的に変更しました。

*   **新アプローチ:** **自動リンクからの検索リクエストを専門に処理する、新しいルートとコントローラーを作成する。**

この方法には、以下の大きなメリットがあります。

*   **責務の分離:** 「キーワードで検索し、結果に応じてリダイレクト先を振り分ける」という単一の責務を、専用のコントローラーに完全に分離できます。
*   **シンプルさ:** 既存の `RecordsTable` コンポーネントの複雑さに影響されることなく、新しいコントローラーは今回の機能のためだけのシンプルなロジックを持つことができます。
*   **テストの容易性:** 新しいコントローラーに対するテストは、複雑なLivewireコンポーネントのライフサイクルを考慮する必要がなく、非常にシンプルかつ堅牢になります。

### ステップ 1: 自動リンク検索用の専用ルートとコントローラーの作成 (完了)

*   **目的:** 自動リンクからの検索リクエストを専門に処理する、新しいエンドポイントを作成する。
*   **タスクと成果物:**
    1.  **コントローラーの作成:** `app/Http/Controllers/LedgerLookupController.php` を作成し、`handle(Request $request, string $query)` メソッドに、受け取ったクエリで台帳を検索し、結果件数と `mode` パラメータに応じて適切なルート (`ledger.show` または `ledger.index`) へリダイレクトするロジックを実装しました。
    2.  **ルート定義の追加 (`routes/web.php`):** `/l/{query}` というURLでリクエストを受け付け、作成した `LedgerLookupController@handle` を呼び出す `ledger.lookup` という名前のルートを定義しました。

### ステップ 2: `AutoLinkService` のURL生成ロジック修正 (完了)

*   **目的:** `auto_number` 型のカラムから生成されるリンクのURLを、ステップ1で作成した新しいルート (`ledger.lookup`) を指すように変更する。
*   **タスクと成果物:** `app/Services/AutoLinkService.php` の `convert()` メソッドを修正し、`auto_number` 型のリンクURLを `route('ledger.lookup', ...)` ヘルパを使用して生成するように変更しました。

### ステップ 3: 自動リンク定義テンプレートの修正 (完了)

*   **目的:** 自動リンク作成機能のテンプレートを修正し、新しい検索ルートを使用するように更新する。
*   **タスクと成果物:** `app/Filament/Resources/AutoLinkResource.php` ファイル内の `spec_id` テンプレートの `url_template` を、`/ledgers?query=$1` から `/l/$1` に変更しました。

### ステップ 4: グローバル検索への対応

*   **目的:** URLに直接検索キーワードが指定された場合に、フォルダや台帳定義の選択状態に依存せず、全件を対象とした検索を実行する。
*   **対象ファイル:** `app/Livewire/Ledger/RecordsTable.php`
*   **タスク:**
    1.  `render()` メソッドを修正する。
    2.  メソッドの冒頭で、`$this->search` プロパティに値があり、かつ `$this->selectedFolderIds` と `$this->selectedLedgerDefineIds` が空である場合を「グローバル検索」と判定する。
    3.  グローバル検索の場合、検索対象となる台帳定義IDのリスト (`$searchTargetLedgerDefineIds`) を、全台帳定義のIDリストで初期化する。
    4.  通常（フォルダ等が選択されている）の場合は、従来通り選択されたIDのみを対象とする。

*   **調査と修正:**
    *   **問題の特定:**
        *   当初、`RecordsTable.php` の `render()` メソッドにおける `isGlobalSearch` の判定が期待通りに `true` にならない問題が報告された。
        *   詳細な調査の結果、この問題は `app/Http/Requests/Ledger/SearchRequest.php` の `folderId()` メソッドの挙動に起因することが判明した。
        *   `SearchRequest::folderId()` は、URLパラメータ (`f` や `folderId`) やルートパラメータにフォルダIDが指定されていない場合、デフォルトで `[1]` (ルートフォルダのID) を返していた。
        *   これにより、`RecordsTable.php` の `mount()` メソッドで `$this->selectedFolderIds` が `[1]` に初期化され、`empty($this->selectedFolderIds)` が `false` となり、`isGlobalSearch` の条件が満たされなかった。
    *   **解決策 (SearchRequest.php の修正):**
        *   `app/Http/Requests/Ledger/SearchRequest.php` の `folderId()` メソッドを修正し、URLパラメータやルートパラメータにフォルダIDが指定されていない場合に、`[1]` ではなく空配列 (`[]`) を返すように変更した。
        *   これにより、`RecordsTable.php` の `mount()` メソッドで `$this->selectedFolderIds` が正しく空配列に初期化されるようになり、`isGlobalSearch` の判定が期待通りに動作するようになった。
    *   **テストと追加の修正:**
        *   `SearchRequest.php` の修正後、関連するユニットテスト (`tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php`) を実行したところ、以下の問題が発生した。
            *   **Livewire 内部エラー (`Trying to access array offset on null`):**
                *   原因は、`Livewire::test()` の引数に `['ledgerDefine' => $this->ledgerDefine]` を渡していたことと、その後の `->set()` メソッドによるプロパティ設定が Livewire の状態管理と競合していたため。
                *   **修正1:** `Livewire::test(RecordsTable::class, ['ledgerDefine' => $this->ledgerDefine])` を `Livewire::test(RecordsTable::class)` に変更し、`mount()` メソッドの引数解決を Livewire に任せるようにした。
                *   **修正2:** `->set()` メソッドによるプロパティ設定を削除し、必要なパラメータ (`f`, `l`, `cf`) をすべて `Livewire::withQueryParams()` で渡すように変更した。これにより、コンポーネントの初期化時にすべてのプロパティが正しくバインドされるようになった。
            *   **権限エラー (`403 Forbidden`):**
                *   `RecordsTable.php` の `render()` メソッドで `LedgerDefine` の `view` 権限をチェックしている (`$this->authorize('view', LedgerDefine::class);`) ため、テストユーザーに権限が付与されていなかった。
                *   **修正3:** `tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php` の `setUp()` メソッドに、テストユーザーに `'view_ledger_defines'` 権限を付与するコード (`Permission::findOrCreate('view_ledger_defines'); $this->user->givePermissionTo('view_ledger_defines');`) を追加した。
            *   **リダイレクトアサーションの不一致:**
                *   `it_redirects_to_show_page_on_unique_match()` テストがリダイレクトを期待していたが、`RecordsTable` コンポーネントの責務は検索結果の表示であり、リダイレクトは `LedgerLookupController` の責務であるため、テストの意図が不適切だった。
                *   **修正4:** `assertRedirect` を `assertOk()` と `assertSee()` に変更し、検索結果が正しく表示されていることを確認するように修正した。
    *   **結果:** 上記すべての修正により、`tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php` のすべてのテストがパスし、ステップ4の要件が満たされたことを確認した。

## 4. テストによる品質保証 (完了)

*   **目的:** 新しく作成したコントローラーの振り分けロジックが、様々な条件下で正しく動作することを検証する。
*   **タスクと成果物:** `tests/Feature/Http/Controllers/LedgerLookupControllerTest.php` を新規作成し、以下の全てのテストケースが成功することを確認しました。
    1.  **一意な結果での詳細ページリダイレクト**
    2.  **複数結果での一覧ページリダイレクト**
    3.  **結果0件での一覧ページリダイレクト**
    4.  **`mode=list` での一覧ページ強制リダイレクト**

### ステップ 5: キーワードハイライト機能の実装

*   **目的:** キーワード検索で詳細画面にリダイレクトする際に、キーワードを着色する。通常検索のリスト画面から詳細画面に入る場合と、リンクから詳細画面に入る場合の両方に適用する。
*   **機能要件:**
    1.  詳細画面への遷移時に、検索キーワードを `highlight` というURLクエリパラメータとして渡す。
    2.  詳細画面で `highlight` パラメータを受け取り、ビューに渡す。
    3.  詳細画面のコンテンツ内で、渡されたキーワードをサーバーサイド（`ColumnHtmlService`）でハイライト表示する。
*   **実装計画:**
    1.  **ハイライトキーワードの伝達方法の統一:**
        *   `app/Http/Controllers/LedgerLookupController.php`: `handle` メソッド内で `ledger.show` および `ledger.index` へのリダイレクト時に、`highlight` パラメータを追加する。
        *   `app/Livewire/Ledger/Show.php`: `mount()` メソッドで URL から `highlight` パラメータを受け取り、`searchContext` に設定する。`render()` メソッドで `LedgerContentProcessor` を呼び出す際に、`searchContext` の `highlights` を渡す。
        *   `resources/views/livewire/ledger/records-table.blade.php`: 詳細画面へのリンクに `highlight` パラメータを追加する。
    2.  **`ColumnHtmlService` へのキーワード伝達とハイライト処理:**
        *   `app/Services/Ledger/LedgerContentProcessor.php`: `processContentForDisplay()` メソッドのシグネチャに `$highlights` パラメータを追加し、`ColumnHtmlService::show()` を呼び出す際に `$highlights` を渡す。
        *   `app/Services/Ledger/ColumnHtmlService.php`: `show()` メソッドの引数に `$highlights` を追加し、`show()` メソッド内で `setHighlightKeywords()` を呼び出すように変更する。`setHighlightKeywords()` メソッドの可視性を `private` に変更する。
        *   `resources/views/components/ledger/table-row.blade.php`: `ColumnHtml::setHighlightKeywords($keywords)` の呼び出しを削除し、`ColumnHtml::show()` の呼び出しに `$keywords` を追加する。
*   **JavaScript実装とのトレードオフ:**
    *   **サーバーサイドハイライトのメリット:**
        *   リスト画面と詳細画面でハイライトのロジックを統一できる。
        *   JavaScript でのハイライト実装が不要になる。
    *   **サーバーサイドハイライトのデメリット:**
        *   サーバーサイドで HTML を生成する際にハイライト処理を行うため、HTML の構造を直接操作することになる。これにより、HTML の属性値などが意図せず変換されるリスクが残る（`AutoLinkService` で同様の問題が発生し、`DOMDocument` を導入して解決した経緯がある）。
        *   JavaScript でのハイライトに比べて、より動的な表現（例: ユーザーがハイライトの色を変更する、リアルタイムハイライトなど）が難しい。
        *   クライアントサイドでの処理が減る一方で、サーバーサイドの負荷が増加する可能性がある。
*   **テスト:**
    *   この機能は主にバックエンドの表示ロジックの変更であり、既存のユニットテストでカバーされる範囲が広い。
    *   ただし、手動での動作確認（自動リンクからの遷移、一覧画面からの遷移の両方でキーワードがハイライトされること）は必須とする。

## 5. 最終的な成果

未完了