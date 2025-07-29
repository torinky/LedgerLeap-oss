# 自動リンク機能 実装計画 (最終版)

## 1. 目的と背景

台帳に記録される特定の文字列（例: `SPEC-001`、Redmineチケット番号 `#12345`
など）や、そのルールを説明する文章に対して、システム内外の関連情報へ自動的にハイパーリンクを生成する機能を実装する。

これにより、異なる情報間の分断をなくし、システム利用者が情報を参照・追跡する際の利便性と、管理者が定義をメンテナンスする際の運用効率を飛躍的に向上させることを目的とする。

## 2. ユーザーシナリオと機能要件

### 2.1. ユーザーシナリオ

**シナリオ1：台帳レコード間の連携強化（実務担当者・管理者向け）**

* **状況**: 実務担当者の田中さんは、「作業日報」に「関連する仕様書 `SPEC-001` を参照して作業」と記録する。
* **自動リンク機能があれば**: `SPEC-001` という文字列が、自動的に該当する仕様書レコードへのリンクに変換され、ワンクリックで情報にアクセスできる。

**シナリオ2：外部システムとの連携（実務担当者向け）**

* **状況**: 田中さんは、台帳に「詳細はRedmineチケット `#12345` を参照」と記載する。
* **自動リンク機能があれば**: `#12345` という文字列が、自動的にRedmineの該当チケットへのリンクに変換され、システム間の移動が不要になる。

**シナリオ3：権限と監査（管理者向け）**

* **状況**: 管理者の佐藤さんは、業務上重要な自動リンク定義を管理している。これらの定義が権限のないユーザーによって変更・削除されると業務に支障が出る。また、監査時には変更履歴を正確に追跡できる必要がある。
* **結論**: `AutoLink`定義のCRUD操作は特定の管理者のみに許可し、全ての変更履歴をアクティビティログに記録する必要がある。

**シナリオ4：ルールの競合と意図しないリンク（管理者向け）**

* **状況**: 佐藤さんは、「`TICKET-`で始まる7桁の数字」をJiraへリンクするルールと、「7桁の数字」を旧システムへリンクするルールを両方定義したい。
* **問題**: `TICKET-1234567` という文字列が両方のパターンにマッチしてしまい、どちらが適用されるか制御できない。
* **結論**: 複数のルールが競合した場合に、どちらを優先的に適用するかを管理者が明示的に設定できる仕組みが必要。

**シナリオ5：パフォーマンスとメンテナンス性の確保（管理者向け）**

* **状況**:
  システムが大規模になり、自動リンクの定義も100個以上に増えた。特定の部署でしか使わないルールが、全社のあらゆるページでチェックされるのは非効率。また、新しい正規表現ルールを追加する際、意図通りに動作するか不安がある。
* **結論**: ルールの適用範囲を限定する機能と、保存前に変換結果を確認できるプレビュー機能が必要。

**シナリオ6：ルールの流用と検索（管理者向け）**

* **状況**: 佐藤さんは、過去に設定した「特定のプロジェクトの文書番号」のルールを探している。また、GitLabのマージリクエスト（
  `!123`）用のルールを、既存のRedmineチケット（`#12345`）のルールを参考に作成したい。
* **問題**: ルールを探すのにラベル名しか頼りがなく、似たルールを新規作成するのに一から入力し直すのは非効率でミスも起きやすい。
* **結論**: ラベルや説明文、パターン定義の内容で横断的に検索できる機能と、既存の定義を複製して新しい定義を作成できる機能が必要。

**シナリオ7：台帳定義の理解促進（全ユーザー向け）**

* **状況**: 田中さんは新しい台帳を作成しようとしており、台帳定義の説明文を読んでどの定義を使うべきか判断している。説明文には「この台帳は、
  **プロジェクト憲章 (CHARTER-001)** に基づいて作成してください。詳細は **Redmineチケット #98765** を参照してください。」と書かれている。
* **問題**: 田中さんは `CHARTER-001` や `#98765` が何を指すのか、どこにあるのかを別途探さなければならず、作業開始前の段階で手間がかかる。
* **結論**: 台帳定義の説明文自体にも自動リンク機能が適用され、ユーザーが必要な参照情報に直接アクセスできるようにする必要がある。

### 2.2. 機能要件

上記のシナリオに基づき、機能要件を以下のように整理する。

**1. リンク定義の管理機能（主に管理者向け）**

* **パターンの定義**: どのような文字列をリンクに変換するかを、**正規表現**を使って柔軟に定義できる。
* **リンク先URLの生成**: パターンに一致した文字列を元に、どのようなURLを生成するかを**テンプレート**
  で定義できる。URLテンプレートには、正規表現の**キャプチャグループ**を `$1`, `$2` のような形で埋め込める。
* **管理UI**: これらの定義を管理するUIが必要。
    * **優先順位**: 複数のルールが競合した場合の適用優先順位を数値で設定できる。
    * **適用範囲**: リンク定義を、特定のフォルダや台帳定義に限定して適用する設定ができる。
    * **有効/無効**: 各定義を一時的に有効/無効に切り替えられる。
    * **外部リンク**: 新しいタブで開く(`target="_blank"`)か設定できる。
    * **一覧での詳細表示**: 管理画面の一覧で、各定義の「パターン」と「URLテンプレート」を直接表示し、全体を俯瞰しやすくする。
    * **複製（クローン）機能**: 既存のリンク定義をコピーして、新しい定義の雛形として利用できる。
    * **全文検索**: 管理画面の一覧で、キーワードを入力すると「ラベル」「説明文」「パターン」「URLテンプレート」を対象に横断的な検索ができる。
* **正規表現の安全性とテスト**:
    * **バリデーション**: 不正な正規表現（構文エラーなど）が登録されるのを防ぐ。
    * **プレビュー**: 定義作成時に、サンプルテキストに対する変換結果をリアルタイムで確認できる。

**2. リンクの自動生成機能（全ユーザー向け）**

* **表示時の自動変換**: 台帳レコードのデータや**台帳定義の説明文**など、指定されたテキスト情報を表示する際に、以下のルールでリンクを生成する。
    1. **（最優先）自動採番カラムのリンク化**: 「自動採番」タイプのカラムは、設定不要で台帳内検索結果へ自動的にリンクされる。
    2. **カスタム定義によるリンク化**: 上記以外の場合、コンテキスト（フォルダ等）に応じて適用範囲が限定されたカスタム定義を、
       **優先順位の高い順に**適用する。一度いずれかのルールにマッチした文字列は、後続のルールの対象外とする。
* **パフォーマンスへの配慮**: 多数のリンク定義がある場合でも、ページの表示速度が低下しないよう、効率的な処理（例:
  リンク定義のキャッシュ）が求められる。

**3. 権限管理と監査証跡**

* `AutoLink`定義のCRUD操作は、特定の管理権限を持つユーザーのみに許可される。
* `AutoLink`定義に対する全ての操作（作成、更新、削除）は、アクティビティログに記録され、追跡可能である。

## 3. 実装計画 (最終版)

### ステップ 1: データ構造の定義と管理機能の基盤構築 (詳細設計) - **完了**

* **目的:** 自動リンクの定義情報を格納するためのデータベーステーブルと、それを操作するための基本的なモデルおよび管理画面の雛形を作成する。
* **詳細設計:**
    1. **データベースマイグレーションの作成:** 2つのマイグレーションファイルを作成する。
        * **`create_auto_links_table`**: 自動リンク定義の本体を格納するテーブル。
            * `id` (主キー)
            * `label` (string): 管理画面で表示するための分かりやすい名前。
            * `pattern` (string): リンク対象を検出するための正規表現パターン。
            * `url_template` (string): 変換先のURLテンプレート。正規表現のキャプチャグループ `$1` などを含む。
            * `description` (text, nullable): この定義に関する詳細な説明。
            * `priority` (integer, default: 0): 複数のルールが競合した場合の適用優先順位。数値が小さいほど優先度が高い。
            * `is_enabled` (boolean, default: true): この定義が有効かどうかを示すフラグ。
            * `open_in_new_tab` (boolean, default: true): 生成されたリンクを新しいタブで開く (`target="_blank"`)
              かどうかのフラグ。
            * `timestamps`: `created_at` と `updated_at`。
        * **`create_auto_link_scopes_table`**: 自動リンク定義の適用範囲を格納する中間テーブル。
            * `auto_link_id` (foreign key): `auto_links`テーブルへの参照。
            * `scopeable_id` (unsigned big integer): 適用対象リソースのID（例: `folders.id`）。
            * `scopeable_type` (string): 適用対象リソースのモデルクラス名（例: `App\Models\Folder`）。
            * 複合主キー (`auto_link_id`, `scopeable_id`, `scopeable_type`) を設定し、重複登録を防止する。
    2. **Eloquentモデルの作成:**
        * `app/Models/AutoLink.php` を作成する。
        * `$fillable` プロパティに、マイグレーションで定義したカラムを設定する。
        * `$casts` プロパティで、`is_enabled` と `open_in_new_tab` を `boolean` 型にキャストする。
        * 適用範囲のモデル (`Folder` など) とのポリモーフィックリレーション (`morphedByMany`) を定義する。
    3. **Filamentリソースの作成:**
        * Artisanコマンド `php artisan make:filament-resource AutoLink` を実行し、管理画面の雛形 (
          `app/Filament/Resources/AutoLinkResource.php` など) を生成する。
* **成果物:**
    * `database/migrations/2025_07_28_105234_create_auto_links_table.php`
    * `database/migrations/2025_07_28_105305_create_auto_link_scopes_table.php`
    * `app/Models/AutoLink.php`
    * `app/Filament/Resources/AutoLinkResource.php`
    * `app/Filament/Resources/AutoLinkResource/Pages/CreateAutoLink.php`
    * `app/Filament/Resources/AutoLinkResource/Pages/EditAutoLink.php`
    * `app/Filament/Resources/AutoLinkResource/Pages/ListAutoLinks.php`

---

### ステップ 2: リンク定義の管理UI実装 (Filament) - 詳細設計 (網羅版) - **完了**

* **目的:** 管理者が正規表現の知識を必要とせずに、直感的かつ安全に自動リンク定義を管理できる、ユーザーフレンドリーで高機能なUIを実装する。
* **詳細設計:**
    1. **翻訳ファイルの準備 (`lang/ja/auto_links.php`):**
        * フォーム項目、ヘルプテキスト、バリデーションメッセージ、パターンテンプレートの選択肢など、UIで表示されるすべてのテキスト要素の日本語訳を定義する。

    2. **カスタムバリデーションルールの実装 (`app/Rules/ValidAutoLinkPattern.php`):**
        * `pattern` フィールドの入力値を検証するため、以下の2つのチェックを行うルールを作成する。
            1. **正規表現の構文チェック:** `@preg_match()` を利用し、PHPの正規表現として構文が正しいかを検証する。
            2. **キャプチャグループの整合性チェック:** `url_template` で使用されているキャプチャグループ（例: `$1`）が、
               `pattern` 内に実際に存在するかを検証し、定義の不整合を防ぐ。

    3. **Filamentリソースのフォーム (`AutoLinkResource::form()`):**
        * **レイアウト:** 3カラムレイアウトを採用し、左側にメイン設定、右側に補助設定、下段にプレビューを配置する。
        * **パターンテンプレート機能:**
            * フォーム最上部に `Select::make('template')` を配置。選択肢として「Redmineチケット」などを翻訳付きで表示。
            * `live()` と `afterStateUpdated()` を使用し、テンプレートを選択すると `pattern` と `url_template`
              フィールドが対応するプリセット値で即座に更新されるようにする。
        * **入力フィールド:**
            * `label`, `description`, `pattern`, `url_template`, `priority` などの各フィールドを、翻訳ヘルパー `__()`
              を使って多言語対応で配置する。
            * `pattern` フィールドには、上記で作成した `ValidAutoLinkPattern` ルールを適用する。
        * **プレビュー機能の強化:**
            * `Placeholder::make('preview_output')` をフォーム下部に配置。
            * `content()` メソッド内で、入力されたテキストとパターンに基づき、以下の情報をリアルタイムで表示する。
                * **変換後テキスト:** 実際にリンクが埋め込まれたHTMLプレビュー。
                * **キャプチャグループの可視化:** パターンにマッチした各部分について、「マッチ全体 (`$0`)」「グループ1 (
                  `$1`)」などが具体的にどの文字列に対応するのかをテーブル形式で明示し、デバッグを容易にする。

    4. **Filamentリソースのテーブル (`AutoLinkResource::table()`):**
        * **表示カラム:** `label`, `pattern`, `priority` などを表示。主要なカラムは検索・ソート可能にする。
        * **インライン編集:** `ToggleColumn::make('is_enabled')` を使用し、一覧画面上で直接「有効/無効」を切り替えられるようにし、操作性を向上させる。
        * **アクション:**
            * 標準の `EditAction`, `DeleteAction` に加え、`ReplicateAction` を追加し、既存の定義をワンクリックで複製できるようにする。

* **成果物:**
    * `lang/ja/auto_links.php`
    * `app/Rules/ValidAutoLinkPattern.php`
    * `app/Filament/Resources/AutoLinkResource.php`
    * `app/Filament/Resources/AutoLinkResource/Pages/CreateAutoLink.php`
    * `app/Filament/Resources/AutoLinkResource/Pages/EditAutoLink.php`
    * `app/Filament/Resources/AutoLinkResource/Pages/ListAutoLinks.php`

---

### ステップ 3: リンク変換サービスの作成 - 詳細設計 - **完了**

* **目的:** テキストをリンクに変換する責務を持つ、再利用可能なビジネスロジックを実装します。このサービスは、台帳レコードの表示や台帳定義の説明文など、様々な場所で利用されることを想定しています。
* **詳細設計:**
    1. **サービスクラスの作成:**
        * `app/Services/AutoLinkService.php` を新規作成します。
    2. **`convert` メソッドの実装:**
        * メソッドシグネチャ:
          `public function convert(string $text, ?ColumnDefine $column = null, $context = null): string`
            * `$text`: 変換対象の文字列。
            * `$column`: オプション。`ColumnDefine` オブジェクト。`auto_number` タイプの場合に特別処理を行うために使用します。
            * `$context`: オプション。適用範囲を絞り込むためのコンテキスト情報（例: `Folder` モデルのインスタンス、
              `LedgerDefine` モデルのインスタンスなど）。
        * **ロジックフロー:**
            1. **`auto_number` カラムの特別処理:**
                * `$column` が `null` でなく、かつ `$column->getType()` が `'auto_number'` であるかをチェックします。
                * もし該当する場合、`$text` 全体を対象に、台帳内検索へのリンク（例: `/ledgers?query=` + `$text`）を生成し、
                  `<a>` タグで囲んで即座に返します。このリンクは常に新しいタブで開くようにします。
                * この処理は、カスタム定義によるリンク変換よりも**優先**されます。
            2. **カスタム定義によるリンク変換:**
                * データベースから有効な `AutoLink` 定義を全て取得します。
                    * **キャッシュの利用:** パフォーマンス最適化のため、取得した `AutoLink` 定義はキャッシュします。キャッシュキーは適用範囲（
                      `$context`）によって動的に生成するか、グローバルなキャッシュを利用するかを検討します。
                    * **適用範囲のフィルタリング:** `$context` が提供されている場合、`auto_link_scopes`
                      テーブルのリレーションシップを利用して、現在のコンテキストに適用される `AutoLink` 定義のみをフィルタリングします。
                * 取得した `AutoLink` 定義を `priority` カラムの値で昇順にソートします（数値が小さいほど優先度が高い）。
                * ソートされた `AutoLink` 定義をループ処理します。
                    * 各 `AutoLink` 定義の `pattern` と `url_template` を使用して、`preg_replace_callback` 関数で `$text`
                      内のパターンに一致する部分を `<a>` タグに置換します。
                    * `url_template` 内の `$1`, `$2` などのキャプチャグループ参照が、`preg_replace_callback`
                      のコールバック関数内で正しく展開されるように実装します。
                    * `AutoLink` 定義の `open_in_new_tab` プロパティが `true` の場合、生成される `<a>` タグに
                      `target="_blank"` 属性を追加します。
                    * **「一度マッチした文字列は後続のルールの対象外とする」**: これを実現するためには、
                      `preg_replace_callback` の結果を次のループの `$text`
                      に渡すことで、既にリンク化された部分が再度処理されないようにします。ただし、正規表現の性質上、オーバーラップするマッチングの制御は複雑になる可能性があるため、シンプルな実装では「最初のマッチが優先される」という挙動になることを許容します。厳密な制御が必要な場合は、マッチした範囲を記録し、次のマッチングから除外するなどの工夫が必要ですが、まずはシンプルな実装から始めます。
    3. **依存性の注入:**
        * 必要に応じて、`AutoLinkService` が他のサービスやリポジトリ（例: `AutoLink`
          モデルを操作するためのリポジトリ）に依存する場合、コンストラクタインジェクションを利用して依存性を注入します。
* **成果物:**
    * `app/Services/AutoLinkService.php`

---

### ステップ 4: 台帳詳細画面への適用と動作確認 - **完了**

*   **目的:** 作成した`AutoLinkService`を、実際の台帳詳細画面に組み込み、自動リンク機能が正しく動作することを確認します。
*   **詳細設計:**
    1.  **`app/Services/Ledger/ColumnHtmlService.php` の修正:**
        *   `ColumnHtmlService` は、台帳のカラムの値をHTMLとして表示する責務を持っています。このサービス内で `AutoLinkService` を利用し、カラムのテキストを自動リンクに変換します。
        *   **既存コードとの整合性確認と修正:**
            *   `ColumnHtmlService` のコンストラクタに `AutoLinkService` を依存注入しました。
            *   `show()` メソッドのシグネチャに、現在の `Ledger` レコード（または関連するコンテキストオブジェクト、例: `$record`）を受け取る引数を追加しました。これにより、`AutoLinkService` が適用範囲を判断するためのコンテキスト情報を受け取れるようになります。
            *   `show()` メソッド内で、カラムの値をHTMLとして返す前に、`AutoLinkService::convert()` を呼び出すように修正しました。この際、`$html` が `null` の場合でもエラーにならないよう、`(string)$html` とキャストして渡しています。
            *   `AutoLinkService::convert()` には、カラムの値（`$this->initialValue`）、`ColumnDefine` オブジェクト（`$this->columnDefineData`）、および追加されたコンテキスト引数（`$record`）を渡しています。
            *   `highlightKeywords` メソッドは、自動リンク変換後に適用されるように変更しました。
    2.  **`resources/views/livewire/ledger/show.blade.php` の修正:**
        *   `ColumnHtmlService::show()` メソッドの呼び出し箇所を特定し、`$ledgerRecord` を新しい引数として渡すように修正しました。
    3.  **`resources/views/components/ledger/detail/table.blade.php` の修正:**
        *   `ColumnHtmlService::show()` メソッドの呼び出し箇所を特定し、`$ledgerRecord` を新しい引数として渡すように修正しました。
    4.  **動作確認:**
        *   台帳詳細画面にアクセスし、自動リンクが設定されたテキストが正しくリンクとして表示されることを確認します。
        *   `auto_number` タイプとして定義されたカラムの値が、台帳内検索へのリンクとして表示されることを確認します。
        *   作成したカスタム自動リンク定義（例: Redmineチケット番号）が、台帳のテキスト内で正しくリンクに変換されることを確認します。
*   **成果物:** 台帳詳細画面で、定義したルール通りに文字列がリンクとして表示される状態。


---

### ステップ 5: 適用範囲の拡大と最適化 - **未着手**

*   **目的:** 他の画面にも自動リンク機能を展開し、パフォーマンスを最適化する。
*   **詳細設計:**

    ##### 5.1. 台帳一覧画面 (`RecordsTable`) への適用 - **完了**

    *   **対象ファイル:** `app/Livewire/Ledger/RecordsTable.php` および関連するBladeビュー
    *   **現状分析:** `RecordsTable`は、台帳レコードの各カラムの値を表示するために、`ColumnHtmlService`を使用しています。`ColumnHtmlService`は既に`AutoLinkService`を呼び出すように修正されているため、`RecordsTable`側で`ColumnHtmlService`に適切なコンテキスト（`$ledgerRecord`）が渡されているかを確認し、必要に応じて修正します。
    *   **変更内容:**
        1.  `app/Livewire/Ledger/RecordsTable.php`の`render()`メソッド、またはカラムの値をレンダリングする部分を特定します。
        2.  各カラムの値を表示する際に、`ColumnHtmlService::show()`メソッドに現在の`$ledgerRecord`インスタンスを引数として渡すように修正します。これにより、`ColumnHtmlService`内で`AutoLinkService`がスコープに応じたリンク定義を適用できるようになります。
        3.  もし`ColumnHtmlService`が使用されていない場合、`RecordsTable`の各カラムのレンダリングロジックに直接`AutoLinkService`を注入し、`convert()`メソッドを呼び出すように変更します。ただし、既存の`ColumnHtmlService`の利用を優先し、一貫性を保つことを推奨します。

**変更内容の要約:**
*   **変更ファイル:** `resources/views/components/ledger/table-row.blade.php`
*   **変更詳細:**
    *   `ColumnHtml::show()` メソッドの呼び出しにおいて、第7引数として `$ledgerRecord` を追加しました。
    *   変更前: `->show($columnDefine, $ledgerRecord->content[$columnDefine->id], $canView)`
    *   変更後: `->show($columnDefine, $ledgerRecord->content[$columnDefine->id], $canView, [], '', false, $ledgerRecord)`
*   **目的:**
    *   `ColumnHtmlService` が `AutoLinkService` を呼び出す際に、現在の台帳レコード (`$ledgerRecord`) をコンテキストとして渡せるようにするためです。これにより、`AutoLinkService` は台帳レコードの情報を利用して、より正確な自動リンクの適用範囲を判断し、適切なリンクを生成できるようになります。特に、将来的に自動リンクの適用範囲が特定の台帳レコードに限定されるような機能が追加された場合に、このコンテキスト情報が活用されます.

    ##### 5.2. 台帳定義の管理画面 (`app/Livewire/LedgerDefine/Preview.php`) への適用 - 見直し後の詳細設計

    #### 目的
    台帳定義の`create_description`, `list_description`, `detail_description`に自動リンク機能を適用する。特に、Markdown形式で記述された説明文が正しくHTMLに変換され、その上で自動リンクが適用されるようにする。

    #### 5.2.1 問題点と見直し

    5.2.1.1. ** Markdownパースと自動リンク適用順序の問題: **
        *   **旧アプローチの問題:** `resources/views/livewire/ledger-define/preview.blade.php`内で`x-markdown`コンポーネントの内部で`AutoLinkService::convert()`を呼び出していたため、MarkdownがHTMLに変換される前に自動リンクが適用され、結果的に生成されたリンクのHTMLタグがエスケープされて表示されていました。
        *   **見直し後の解決策:** `AutoLinkService`がMarkdownのパースと自動リンクの適用を両方行うように責務を変更します。これにより、`AutoLinkService`が最終的にHTMLを返すため、BladeビューではそのHTMLを直接表示するだけでよくなります。

    5.2.1.2. **`AutoLinkService.php`内の`preg_replace_callback`の`str_replace`誤用:**
        *   **旧アプローチの問題:** `url_template`内のキャプチャグループ（例: `$1`）を置換する際に、`str_replace(' . $key, ...)`という構文エラーが発生していました。
        *   **見直し後の解決策:** `preg_replace_callback`のコールバック関数内で、`$matches`配列のインデックスを直接利用し、`url_template`内の`$1`, `$2`などを正確に置換する、より堅牢なロジックを適用します。

    5.2.1.3.  **`AutoLinkService.php`内の`whereHas`における`$this->id`の問題:**
        *   **旧アプローチの問題:** `AutoLinkService`のインスタンスの`id`を参照してしまい、`AutoLink`モデルのIDを参照できていませんでした。
        *   **見直し後の解決策:** `AutoLink`モデルに`scopes`リレーション（`AutoLinkScope`モデルへの`hasMany`リレーション）を追加し、`AutoLinkScope`モデルを作成することで、`whereHas('scopes', ...)`のロジックが正しく機能するようになりました。この点については、これ以上の修正は不要と判断します。

    #### 5.2.2 変更内容

    5.2.2.1.  **`app/Services/AutoLinkService.php`の修正:**
        *   **`Spatie\LaravelMarkdown\MarkdownRenderer`の注入:** コンストラクタで`Spatie\LaravelMarkdown\MarkdownRenderer`を依存注入します。
        *   **`convert`メソッドの処理順序の変更:**
            *   `auto_number`カラムの特別処理はそのまま維持します。
            *   それ以外のテキストに対しては、まず`$this->markdownRenderer->toHtml($text)`を呼び出し、MarkdownをHTMLに変換します。
            *   変換されたHTML (`$html`) に対して、既存の`AutoLink`定義に基づくリンク置換処理を行います。
        *   **`preg_replace_callback`内の`url_template`置換ロジックの修正:**
            `preg_replace_callback`のコールバック関数内で、`$autoLink->url_template`内の`$1`, `$2`などのキャプチャグループを`$matches`配列の対応する値で置換するロジックを以下のように修正します。

```php
            // AutoLinkService.php の convert メソッド内
            foreach ($autoLinks as $autoLink) {
                $convertedHtml = preg_replace_callback($autoLink->pattern, function ($matches) use ($autoLink) {
                    $url = $autoLink->url_template;
                    // $1, $2 などのキャプチャグループを置換
                    // $matches[0] は全体マッチなのでスキップ
                    for ($i = 1; $i < count($matches); $i++) {
                        // URLエンコードしてから置換
                        $url = str_replace('$' . $i, urlencode($matches[$i]), $url);
                    }
                    $target = $autoLink->open_in_new_tab ? ' target="_blank"' : '';
                    return '<a href="' . e($url) . '"' . $target . ' class="font-bold text-primary-500 hover:underline">' . e($matches[0]) . '</a>';
                }, $convertedHtml);
            }
```

    5.2.2.2.  **`resources/views/livewire/ledger-define/preview.blade.php`の修正:**
        *   `create_description`, `list_description`, `detail_description`を表示している箇所から`<x-markdown>`タグを削除します。
        *   `AutoLinkService::convert()`の呼び出し結果を直接`{!! ... !!}`で表示するように戻します。

```blade
        {{-- 修正前 --}}
        <x-markdown class="prose text-sm leading-relaxed max-w-none">
            {!! app(\App\Services\AutoLinkService::class)->convert($ledgerDefineRecord->create_description, null, $ledgerDefineRecord) !!}
        </x-markdown>

        {{-- 修正後 --}}
        {!! app(\App\Services\AutoLinkService::class)->convert($ledgerDefineRecord->create_description, null, $ledgerDefineRecord) !!}
```

        *   同様に、`list_description`と`detail_description`についても修正します。

    #### 成果物

    *   `app/Services/AutoLinkService.php`がMarkdownのパースと自動リンクの適用を両方行うようになる。
    *   台帳定義の管理画面（`app/Livewire/LedgerDefine/Preview.php`が使用する`resources/views/livewire/ledger-define/preview.blade.php`）において、`create_description`, `list_description`, `detail_description`の各説明文に自動リンク機能が正しく適用される。


    ##### 5.3. `AutoLinkService`におけるリンク定義のキャッシュ導入

    *   **対象ファイル:** `app/Services/AutoLinkService.php`, `app/Models/AutoLink.php` (またはObserver)
    *   **現状分析:** 現在、`AutoLinkService::convert()`メソッド内で`AutoLink`定義がデータベースから直接取得されています。これをキャッシュすることで、データベースへのアクセス回数を減らし、パフォーマンスを向上させます。
    *   **変更内容:**
        1.  **`AutoLinkService.php`の修正:**
            *   `convert()`メソッド内で`AutoLink`定義を取得するロジックを、Laravelの`Cache`ファサードを利用するように変更します。
            *   **キャッシュキーの設計:**
                *   スコープ（`$context`）が提供される場合: `auto_links_scoped_{context_type}_{context_id}` (例: `auto_links_scoped_App_Models_Folder_123`)
                *   スコープが提供されない場合（グローバルなリンク定義）: `auto_links_global`
                *   これにより、スコープごとに異なるキャッシュエントリが作成され、無関係なスコープのキャッシュが影響を受けないようにします。
            *   **キャッシュの利用:** `Cache::remember()`メソッドを使用して、指定されたキーでキャッシュが存在すればそれを返し、なければクロージャを実行して結果をキャッシュに保存するようにします。
            *   **キャッシュ期間:** `forever()`を使用するか、適切な期間（例: `60 * 24`分）を設定します。
        2.  **キャッシュの無効化:**
            *   `AutoLink`モデルの変更（作成、更新、削除）時に、関連するキャッシュエントリを無効化するメカニズムを実装します。
            *   **方法1: モデルイベントリスナー (推奨)**
                *   `app/Models/AutoLink.php`に`boot()`メソッドを追加し、`created`, `updated`, `deleted`イベントをリッスンします。
                *   各イベント内で、`Cache::forget('auto_links_global')`を呼び出し、グローバルキャッシュを無効化します。
                *   もしスコープごとのキャッシュを厳密に管理する場合、`AutoLink`と`AutoLinkScope`のリレーションシップを考慮し、関連するスコープのキャッシュも無効化する必要があります。これは複雑になる可能性があるため、まずはグローバルキャッシュの無効化から始め、必要に応じて拡張します。
            *   **方法2: Observerの利用**
                *   `php artisan make:observer AutoLinkObserver --model=AutoLink`でObserverを作成し、`AppServiceProvider`に登録します。
                *   Observerの`created`, `updated`, `deleted`メソッド内でキャッシュを無効化します。

*   **成果物:**
    *   台帳一覧画面で、台帳レコード内のテキストが自動リンクとして表示される。
    *   台帳定義の管理画面で、`description`カラムのテキストが自動リンクとして表示される。
    *   `AutoLinkService`が`AutoLink`定義をキャッシュから取得するようになり、パフォーマンスが向上する。
    *   `AutoLink`定義の変更時に、関連するキャッシュが適切に無効化される。

---

### ステップ 6: 権限管理と監査証跡の実装 - **未着手**

* **目的:** `AutoLink`定義へのアクセスを特定の管理者に制限し、全ての操作履歴を記録する。
* **タスク:**
    1. **ポリシーの作成と登録:** `AutoLinkPolicy`を作成し、`AuthServiceProvider`に登録する。
    2. **ポリシーのロジック実装:** 各アクション（`viewAny`, `create`, `update`, `delete`）で、ユーザーの権限をチェックするロジックを実装する。
    3. **監査証跡の実装:** `AutoLink`モデルに`spatie/laravel-activitylog`の`LogsActivity`
       トレイトを適用し、分かりやすいログメッセージを生成するように設定する。
* **成果物:**
    * 権限のないユーザーは`AutoLink`管理画面にアクセス・操作できない状態。
    * 権限のある管理者による全ての操作が、アクティビティログに詳細に記録される状態。

---

## 4. 既存機能との関連性と影響

本機能の実装に先立ち、既存のモデルやデータベース構造を調査し、影響範囲と最適な設計について検討しました。

* **調査対象**: `app/Models` および `database/migrations` 配下の全ファイル。
* **結論**: **独立したテーブル (`auto_links`, `auto_link_scopes`) を新規作成する現在の計画が最適**です。

* **理由**:
    1. **責務の分離**: 既存の類似機能（例: `technical_term_groups`
       テーブルによる類義語管理）を拡張する方法も検討しましたが、自動リンク機能には「正規表現パターン」「URLテンプレート」「優先順位」「外部リンク設定」といった独自の複雑な属性が多数必要です。既存テーブルを流用すると、本来の責務から逸脱し、モデルとテーブル構造が複雑化するため、将来のメンテナンス性を著しく損なうリスクがあります。
    2. **既存機能への影響回避**:
       新規テーブルとして完全に分離することで、既存のデータベース構造やロジックへの変更が一切不要になります。これにより、既存機能の動作を破壊するリスクを完全に排除し、新機能の開発とテストを安全かつ独立して進めることができます。
    3. **カラムの妥当性**: 計画しているカラムは、機能要件をすべて満たすために必要不可欠であり、現時点での削減は困難です。

以上の理由から、本機能は既存機能に影響を与えることなく、安全に実装可能であると判断しました。
