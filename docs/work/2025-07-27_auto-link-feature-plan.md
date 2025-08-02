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

### ステップ 5: 適用範囲の拡大と最適化 - <span style="color: green;">完了</span>

*   **目的:** 自動リンク機能の適用範囲を台帳レコード本体以外にも拡大し、ユーザーシナリオを網羅する。さらに、管理者が適用範囲を直感的に設定できるUIを提供し、システム全体のパフォーマンスを最適化する。

*   **詳細設計:**

    *   **5.1. 台帳一覧画面 (`RecordsTable`) への適用 - <span style="color: green;">完了</span>**
        *   **背景・目的:** `ColumnHtmlService`は既に`AutoLinkService`を呼び出すように修正済みであったため、`RecordsTable`から`ColumnHtmlService`を呼び出す際に、コンテキスト情報として現在の`$ledgerRecord`を渡す必要があった。これにより、将来的にレコード単位でリンクの挙動を変える拡張が可能になる。
        *   **変更内容の要約:**
            *   **変更ファイル:** `resources/views/components/ledger/table-row.blade.php`
            *   **変更詳細:** `ColumnHtml::show()` メソッドの呼び出しにおいて、第7引数として `$ledgerRecord` を追加した。
                *   変更前: `->show($columnDefine, $ledgerRecord->content[$columnDefine->id], $canView)`
                *   変更後: `->show($columnDefine, $ledgerRecord->content[$columnDefine->id], $canView, [], '', false, $ledgerRecord)`
        *   **成果:** `AutoLinkService`が台帳レコードのコンテキストを認識可能になった。

    *   **5.2. 台帳定義のプレビュー画面 (`Preview.php`) への適用 - <span style="color: green;">完了</span>**
        *   **背景・目的:** ユーザーシナリオ7「台帳定義の理解促進」に基づき、台帳定義の各種説明文（`create_description`, `list_description`, `detail_description`）に自動リンク機能を適用する必要があった。当初、`x-markdown`コンポーネント内で`AutoLinkService::convert()`を呼び出していたが、MarkdownがHTMLに変換される前にリンク処理が走り、HTMLタグがエスケープされる問題が発生した。
        *   **調査と判断:** この問題を解決するため、`AutoLinkService`の責務を見直し、Markdownのパースとリンク変換の両方を行うように変更することが最適だと判断した。これにより、処理の順序が保証され、ビューは最終的なHTMLを受け取るだけで済むようになる。
        *   **変更内容:**
            1.  `app/Services/AutoLinkService.php`のコンストラクタで`Spatie\LaravelMarkdown\MarkdownRenderer`を依存注入。
            2.  `convert`メソッド内で、まず`$this->markdownRenderer->toHtml($text)`を呼び出してMarkdownをHTMLに変換し、その後でリンク置換処理を行うように順序を変更した。
            3.  `resources/views/livewire/ledger-define/preview.blade.php`では、`x-markdown`タグを削除し、`AutoLinkService::convert()`の結果を`{!! ... !!}`で直接表示するように修正した。
        *   **成果:** 台帳定義のプレビュー画面で3種類の説明文に自動リンクが正しく適用される状態になった。

    *   **5.3. `AutoLink`管理UIへの適用範囲設定機能の実装と適用ロジックの修正 - <span style="color: green;">完了</span>**
        *   **背景・目的:** ユーザーシナリオ5「パフォーマンスとメンテナンス性の確保」で示された「特定の部署でしか使わないルールが全社でチェックされるのは非効率」という課題に対応するため、管理者がGUIで直感的に自動リンクの適用範囲を設定できる機能と、その設定を変換処理に反映させるロジックが必要であった。
        *   **UI実装の調査と判断:** プロジェクト内の既存実装を調査した結果、`codewithdennis/filament-select-tree`パッケージによる`SelectTree`コンポーネントが、階層選択UIとして複数の箇所で利用されていることを確認。プロジェクト全体のUI統一性と開発効率の観点から、この`SelectTree`コンポーネントを流用することが最適だと判断した。
        *   **適用ロジックの調査と判断:** 当初の実装では、適用範囲の絞り込みロジックがコメントアウトされていた。また、コンテキストが`Ledger`モデルの場合の考慮が漏れており、さらにフォルダの継承範囲（親か子か）についての要件も明確にする必要があった。最終的に、指定されたフォルダとそのすべての子孫フォルダに適用される`descendantsAndSelf`を利用するロジックが正しい要件であると確認された。
        *   **実装内容:**
            1.  **モデル:** `app/Models/AutoLink.php`に`folders`リレーション（`morphedByMany`）を定義した。
            2.  **UI:** `app/Filament/Resources/AutoLinkResource.php`に`SelectTree`コンポーネントを追加。`relationship('folders', 'title', 'parent_id')`と設定し、フォルダ階層を正しく表示・保存できるようにした。
            3.  **翻訳:** `lang/ja/auto_links.php`に必要なUIテキストを追加した。
            4.  **適用ロジック:** `app/Services/AutoLinkService.php`の`convert`メソッド内のクエリを修正。コンテキスト（`Ledger`, `LedgerDefine`, `Folder`）から基準となるフォルダを特定し、そのフォルダの`descendantsAndSelf`（自身とすべての子孫）を適用範囲とするようにした。また、スコープが設定されていないグローバルな定義は常に読み込まれるように`whereDoesntHave`と`orWhereHas`を組み合わせて実装した。
        *   **成果:** 管理画面で`AutoLink`定義に適用範囲（フォルダ）を設定でき、その設定がリンク変換時に正しく（子孫フォルダを含めて）反映されるようになった。

    *   **5.4. 台帳定義説明文への自動リンク適用（網羅） - <span style="color: green;">完了</span>**
        *   **背景・目的:** ユーザーシナリオ7「台帳定義の理解促進」を完全に満たすには、ユーザーが台帳定義に触れる全ての主要画面で説明文の自動リンクが機能する必要がある。当初の計画ではプレビュー、一覧、詳細、編集の各画面を対象としていた。
        *   **調査と判断:**
            *   **既存サービス:** `AutoLinkService`は、Markdownテキストを受け取り、リンク変換済みのHTMLを返す機能が既に実装されており、これを最大限に再利用する方針が最も効率的である。
            *   **静的表示画面の特定:** ユーザーが主に情報を閲覧する「台帳一覧画面」「台帳詳細画面」「台帳新規作成画面」を対象として特定。これらの画面では、Bladeビュー内で直接`AutoLinkService`を呼び出し、結果をHTMLとして表示する方法が、既存の構造への影響も少なく、シンプルで最適だと判断した。
        *   **実装と確認:**
            1.  **台帳一覧画面:** `resources/views/components/ledgerDefine/header.blade.php` 内の `list_description` 表示箇所を修正し、`AutoLinkService` を経由して表示するように変更した。
            2. **ユーザー確認とフィードバック:**
                *   **適用漏れの指摘:** 当初の計画から「台帳新規作成画面」への適用が漏れていることが判明した。
                *   **表示箇所の不備:** 「台帳詳細画面」において、当初実装したLivewireコンポーネント (`livewire/ledger/show.blade.php`) 内での表示は、画面レイアウトの観点から不適切であることが判明した。
        *   **最終的な修正:**
            *   **新規作成画面:** ユーザーからのフィードバックに基づき、`resources/views/ledger/create.blade.php` に `create_description` の表示ロジックを追加した。
            *   **詳細画面:** 表示の適正化のため、`resources/views/ledger/show.blade.php` のレイアウト内に `detail_description` の表示ロジックを移設した。
        *   **成果:** 台帳の新規作成、一覧、詳細、そして台帳定義の編集という、ユーザーが説明文に触れる全ての主要画面で、意図通りに自動リンクが機能する状態となった。

    *   **5.5. `AutoLinkService`におけるリンク定義のキャッシュ導入と適用範囲の考慮 - <span style="color: green;">完了</span>**
        *   **背景・目的:** 自動リンクの定義数が増加した場合のパフォーマンス低下を防ぐため、`AutoLinkService`にデータベースから取得したリンク定義をキャッシュする機構を導入する。また、定義やその適用範囲が変更された際には、キャッシュを即座に無効化（パージ）し、常に最新の定義が適用されることを保証する。
        *   **調査と判断（アーキテクチャ選定）:**
            *   **課題:** `AutoLink`定義は、それ自体の変更だけでなく、適用範囲となる`Folder`の階層構造の変更によっても、実際に適用されるルールが変化する。これらの変更を漏れなく検知し、かつパフォーマンスを損なわないキャッシュ無効化戦略が必要とされた。
            *   **検討したアプローチ:**
                1.  **関連モデル（Ledger, Folder等）のObserverで都度無効化:** `Ledger`や`Folder`が変更されるたびにキャッシュを無効化する方法。`Ledger`は更新頻度が極めて高く、キャッシュヒット率が著しく低下し、逆に性能を悪化させる懸念が大きいため、この方法は不採用とした。
                2.  **`AutoLink`の変更のみを検知:** `AutoLink`モデルの変更のみをObserverで検知する方法。シンプルだが、フォルダ階層の変更に対応できない点が課題だった。
            *   **最終的な設計方針（ハイブリッドアプローチ）:** 上記の課題を解決するため、以下の技術を組み合わせたハイブリッドアプローチを採用することが最適だと判断した。
                *   **Observerパターン (`AutoLinkObserver`, `FolderObserver`):** `AutoLink`定義の変更と、適用範囲に影響する`Folder`の階層変更という、2つの主要な変更トリガーを漏れなく監視する。
                *   **キャッシュタグ (`Cache Tags`):** コンテキストによって動的にキーが変わるキャッシュ（例: `auto_links_folder_123`）を、`auto_links`という単一のタグでグループ化する。これにより、無効化処理が「タグを指定して一括削除」というシンプルなロジックになり、確実性とメンテナンス性が向上する。
                *   **Pivotモデルと`$touches`プロパティ:** `AutoLink`と`Folder`の中間テーブル`auto_link_scopes`の変更が、親である`AutoLink`モデルの`updated`イベントを発火させるようにする。これにより、`AutoLinkObserver`が適用範囲の変更を直接検知できるようになる。

        *   **実装と確認:**
            1.  **キャッシュキー生成ロジックの修正:** `AutoLinkService`内の`getCacheKeyForContext`メソッドを修正し、コンテキストが`Ledger`モデルの場合でも、`$ledger->define->folder_id`を辿って、その台帳が属するフォルダに基づいた一意なキャッシュキー（例: `auto_links_folder_123`）が生成されるようにした。これにより、異なるフォルダの台帳でキャッシュが衝突する問題を完全に防いだ。
            2.  **Pivotモデルの作成とリレーションの更新:**
                *   `app/Models/AutoLinkScope.php`を新規作成し、`protected $table = 'auto_link_scopes';`でテーブル名を明示的に指定した。また、`$touches = ['autoLink'];` を設定した。
                *   `app/Models/AutoLink.php`の`folders()`リレーションを修正し、`->using(AutoLinkScope::class)`を追加した。
                *   `app/Models/Folder.php`に`autoLinks()`リレーションを追加し、`morphToMany`で`AutoLink`モデルとの関係を定義した。
            3.  **キャッシュ無効化Observerの実装:**
                *   `app/Observers/AutoLinkObserver.php`を作成。`saved`と`deleted`イベントで`Cache::tags('auto_links')->flush();`を実行する。
                *   `app/Observers/FolderObserver.php`を作成。`saved`イベント内で`$folder->wasChanged('parent_id')`をチェックし、親子関係が変更された場合のみ`Cache::tags('auto_links')->flush();`を実行する。`deleted`イベントでもキャッシュをクリアする。
                *   `AppServiceProvider`に両Observerを登録した。
            4.  **キャッシュロジックのタグ対応:**
                *   `app/Services/AutoLinkService.php`の`convert()`メソッド内の`Cache::remember(...)`を`Cache::tags(['auto_links'])->remember(...)`に修正した。

        *   **デバッグと解決の経緯:**
            *   **`Table 'ledgerleap.auto_link_scope' doesn't exist` エラー:** `migrate:fresh`を実行しても解消されず、`auto_link_scopes`テーブルが認識されない問題が発生した。これは、`AutoLinkScope`モデルがテーブル名を正しく推測できていなかったためと判明。`protected $table = 'auto_link_scopes';`を明示的に追加することで解決した。
            *   **`Call to undefined method App\Models\AutoLinkScope::setMorphType()` エラー:** `AutoLinkScope`が`Illuminate\Database\Eloquent\Relations\Pivot`を継承していたため、ポリモーフィックリレーションに必要な`setMorphType()`メソッドが存在せず発生した。`Illuminate\Database\Eloquent\Relations\MorphPivot`を継承するように修正することで解決した。
            *   **`Cannot redeclare App\Models\AutoLink::scopeable()` エラー:** `AutoLink`モデルに`scopeable()`メソッドが重複して定義されていたため発生した。不要な`scopeable()`メソッドを削除することで解決した。
            *   **「グローバル設定した自動リンクで適用範囲を制限する設定をしても、グローバル設定のまま自動リンクが適用され続けてしまう」問題:** 上記のデバッグを経て、`AutoLinkScope`の変更が`AutoLink`モデルに正しく伝播し、キャッシュが無効化されるようになったことで、この問題も解消された。

*   **成果物 (ステップ5全体):**
    *   台帳一覧画面および詳細画面の台帳定義説明文に、自動リンクが適用される。
    *   管理者が`AutoLink`定義の適用範囲をフォルダ階層から直感的に設定できる。
    *   `AutoLinkService`が適用範囲を考慮した上で、キャッシュを利用して効率的に動作する。
    *   `AutoLink`定義やその適用範囲の変更が、即座にキャッシュに反映される。

---

### ステップ 6: 権限管理と監査証跡の実装 - <span style="color: green;">完了</span>

*   **目的:** ユーザーシナリオ3「権限と監査」で示された「`AutoLink`定義の変更は特定の管理者のみに許可し、全ての変更履歴を追跡可能にする」という要件に対応します。これにより、重要なシステム設定である自動リンク機能のセキュリティと信頼性を確保します。

*   **詳細設計と実装結果:**

    *   **6.1. 権限の定義と永続化 (Seeder)**
        *   **背景・目的:** `AutoLink`の管理操作を保護するため、専用の権限を定義する必要がありました。プロジェクトには既に権限とロールを一元管理する`RolesAndPermissionsSeeder.php`が存在するため、この既存の仕組みに則って権限を追加し、一貫性を保ちました。
        *   **調査と判断:** `database/seeders/RolesAndPermissionsSeeder.php`を確認し、権限を`$permissions`配列に、ロールへの割り当てを`$roles`配列で行う既存の設計パターンを特定しました。このパターンに従うことで、既存のシーダー実行ロジックをそのまま活用でき、最も安全かつ効率的に権限を追加できると判断しました。
        *   **実装内容:**
            1.  **権限定義の追加:** `RolesAndPermissionsSeeder.php`の`$permissions`配列に、キー`manage_auto_links`、値`自動リンクを管理できる`を追加しました。
            2.  **ロールへの割り当て:** 同ファイル内の`$roles`配列で、`Organization Admin`の権限リストに`manage_auto_links`を追加しました。`Super Admin`は全権限を持つ設定のため、自動的に割り当てられています。
            3.  **データベースへの反映:** `./vendor/bin/sail artisan db:seed --class=RolesAndPermissionsSeeder` コマンドを実行し、変更をデータベースに適用しました。

    *   **6.2. 権限管理の実装 (Policy)**
        *   **背景・目的:** 定義した`manage_auto_links`権限に基づき、実際のHTTPリクエストレベルでアクセス制御を行うため、LaravelのPolicy機能を利用しました。
        *   **実装内容:**
            1.  **Policy作成:** `php artisan make:policy AutoLinkPolicy --model=AutoLink`コマンドで`app/Policies/AutoLinkPolicy.php`を生成しました。
            2.  **Policy登録:** `app/Providers/AuthServiceProvider.php`の`$policies`プロパティに`AutoLink::class => AutoLinkPolicy::class`を登録しました。
            3.  **ロジック実装:** `AutoLinkPolicy`内の各メソッド（`viewAny`, `create`, `update`, `delete`等）で、`$user->can('manage_auto_links')`を返すように実装し、権限チェックを一元化しました。

    *   **6.3. 監査証跡の実装 (Activity Log)**
        *   **背景・目的:** 「いつ、誰が、どの`AutoLink`定義を、どのように変更したか」を記録し、監査要件を満たすために`spatie/laravel-activitylog`パッケージを利用しました。
        *   **実装内容:**
            1.  **モデルへのトレイト適用:** `app/Models/AutoLink.php`に`LogsActivity`トレイトを追加しました。
            2.  **ログ内容のカスタマイズ:** 同モデルに`getActivitylogOptions()`メソッドを実装し、`logOnlyDirty()`で変更があった属性のみを記録し、`setDescriptionForEvent(...)`でログメッセージが人間にとって分かりやすい形式になるよう設定しました。

*   **最終的な成果物:**
    *   **新規作成:**
        *   `app/Policies/AutoLinkPolicy.php`
    *   **修正:**
        *   `database/seeders/RolesAndPermissionsSeeder.php`
        *   `app/Providers/AuthServiceProvider.php`
        *   `app/Models/AutoLink.php`
    *   **状態:**
        *   `manage_auto_links`権限を持たないユーザーは、`AutoLink`管理機能にアクセス・操作できなくなりました。
        *   権限を持つ管理者による全ての操作が、アクティビティログに詳細に記録されるようになりました。

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

---

## 5. ドキュメント更新計画

本機能の実装完了に伴い、管理者および利用者が機能を正しく理解し、開発者が将来のメンテナンスを効率的に行えるよう、以下のドキュメントを整備する。

### 5.1. 新規作成するドキュメント

1.  **機能仕様書 (`/docs/function/AutoLink.md`)**
    *   **目的:** 機能の全体像、設定方法、利用方法を網羅的に解説する。
    *   **対象読者:** システム管理者、一般利用者。
    *   **記載項目:**
        *   **機能概要:** どのような課題を解決し、何ができるのか。
        *   **管理者向けガイド:**
            *   自動リンク定義の作成・編集手順（テンプレート機能、正規表現、プレビュー機能の詳細な使い方）。
            *   適用範囲（スコープ）の設定方法と、フォルダ階層への継承ルール。
            *   優先順位の考え方と、競合した場合の挙動。
            *   定義の複製、有効/無効の切り替え方法。
            *   権限（`manage_auto_links`）と監査ログ（アクティビティログ）に関する説明。
        *   **利用者向けガイド:**
            *   台帳レコードや台帳定義の説明文で、どのようにリンクが自動生成されるかの具体例。
            *   自動採番カラムが特別にリンクされる仕様について。

2.  **モデル仕様書 (`/docs/models/AutoLink.md`)**
    *   **目的:** 開発者向けに、`AutoLink`モデルと関連テーブルの構造を解説する。
    *   **対象読者:** 開発者。
    *   **記載項目:**
        *   `AutoLink`モデルの責務とプロパティ。
        *   `auto_links`テーブルと`auto_link_scopes`テーブルの詳細なカラム定義。
        *   `Folder`モデル等とのポリモーフィックリレーション（`morphedByMany`）の解説。
        *   `AutoLinkScope` Pivotモデルと`$touches`プロパティの役割。

3.  **サービス仕様書 (`/docs/services/AutoLinkService.md`)**
    *   **目的:** 開発者向けに、`AutoLinkService`の責務と主要なロジックを解説する。
    *   **対象読者:** 開発者。
    *   **記載項目:**
        *   サービスの責務（Markdown変換とリンク生成）。
        *   `convert`メソッドのロジックフロー（自動採番の優先処理、カスタム定義の適用順、キャッシュ機構）。
        *   キャッシュ戦略（タグベースの無効化）と、`AutoLinkObserver`, `FolderObserver`との連携。
        *   依存関係（`MarkdownRenderer`など）。

### 5.2. 既存ドキュメントの更新

1.  **プロジェクト概要 (`/docs/README.md`)**
    *   **変更箇所:** 「LedgerLeap の特徴と機能」セクション。
    *   **変更内容:** 新機能として「**自動リンク機能**」の項目を追加し、上記で作成した機能仕様書 (`/docs/function/AutoLink.md`) へのリンクを追記する。

2.  **関連ドキュメント一覧の更新**
    *   **変更箇所:** `/docs/README.md` や関連するインデックスファイル。
    *   **変更内容:** 新規作成したモデル仕様書、サービス仕様書へのリンクを追加する。

---

## 6. 機能改善・不具合修正ログ

このセクションでは、初期リリース後の機能改善や不具合修正に関する技術的な記録を追記します。

### 6.1. [2025-08-02] HTML構造を破壊するリンク置換の修正 - <span style="color: green;">完了</span>

*   **課題・背景:**
    *   `AutoLinkService` の変換処理が、入力文字列にHTMLタグが含まれている場合でも、タグの属性内まで無差別に置換してしまい、HTML構造を破壊する不具合が確認された。特に、添付ファイルのツールチップ表示で問題が顕在化していた。
    *   また、`DOMDocument` 導入後に、台帳のカラムの先頭の空白が詰まって表示される問題が発生した。

*   **調査:**
    *   **HTML構造破壊の原因:** `AutoLinkService`の`convert`メソッドが、入力文字列全体に対して単純な`preg_replace_callback`を実行していることが原因と特定。HTMLのテキストノードとタグを区別できていなかった。
    *   **空白詰めの原因:** `DOMDocument::loadHTML()` に `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD` オプションを指定していたため、`<body>` タグなどが生成されず、その後の `saveHTML()` でコンテンツが正しく取得できていなかった。また、`DOMDocument` がHTMLをパースする際に、タグ間の改行や空白を独立したテキストノードとして認識し、それが最終出力に含まれることで表示上の問題を引き起こしていた。

*   **実装と判断理由:**
    *   **HTML構造破壊の修正:** `convert`メソッドのロジックを修正し、`DOMDocument` を導入。正規表現 `/(<[^>]*>)|([^<]+)/` を使用して「HTMLタグ」と「それ以外のテキストノード」を区別して処理するように変更した。HTMLタグはそのまま維持し、テキストノードに対してのみ自動リンクの置換処理を適用することで、HTMLの属性値などが意図せず変換されることを防いだ。この方法が、既存のロジックへの影響を最小限に抑えつつ、問題を解決するのに最適だと判断した。
    *   **空白詰めの修正:**
        1.  `DOMDocument::loadHTML()` から `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD` オプションを削除し、`<body>` タグなどが正しく生成されるようにした。これにより、`saveHTML()` がコンテンツを正しく出力できるようになった。
        2.  `DOMDocument::loadHTML()` に `LIBXML_NOBLANKS` オプションを追加し、空白のみのテキストノードを無視するようにした。これにより、DOMツリーから余分な空白ノードが取り除かれた。
        3.  最終的なHTML出力に対して `trim()` と `preg_replace('/>\s+</', '><', $innerHtml)` を適用し、タグ間の余分な空白や改行をさらに除去した。これにより、表示上の不自然な空白が解消された。
    *   **デバッグログの削除:** 不具合解消に伴い、`app/Services/AutoLinkService.php` に一時的に追加していた `Log::debug` ステートメントはすべて削除した。これは本番環境でのパフォーマンスやログの肥大化を避けるため、およびコードのクリーンアップのために必要であると判断した。

*   **関連ファイル:**
    *   `app/Services/AutoLinkService.php`
    *   `app/Services/Ledger/ColumnHtmlService.php` (data-tipサニタイズ)
    *   `resources/views/components/ledger/detail/table.blade.php` (HTMLエスケープ解除)
    *   `resources/views/livewire/ledger/show.blade.php` (HTMLエスケープ解除)

### 6.2. [2025-08-02] 自動リンク表示の改善（ツールチップとアイコンの導入）

*   **課題・背景:**
    *   現在の自動リンクは、単にテキストが青字・下線付きで表示されるため、ユーザーが「なぜこのテキストがリンクになっているのか」「リンク先は何なのか」を直感的に理解できない。
    *   特に実務担当者にとって、リンククリック前にリンクの意図や種類を把握できると、関連情報へのアクセス効率が向上する。

*   **検討された表示改善案:**
    1.  **ツールチップによる情報提供 (`title` 属性の活用):**
        *   リンクにカーソルを合わせた際に表示されるツールチップに、そのリンクがどの自動リンク定義によって生成されたか、またはリンク先の種類（例: 「Redmineチケットへのリンク」）を表示する。
        *   これにより、ユーザーはリンクの意図を詳細に確認できる。
    2.  **アイコンによる視覚的なヒントの追加:**
        *   リンクの直後または直前に、リンクの種類を示す小さなアイコン（例: 外部リンクを示すアイコン、Redmineのロゴ、仕様書アイコンなど）を表示する。
        *   これにより、一目でリンクの種類を判別できるようになる。

*   **詳細設計（再々検討版）:**

    #### 1. 変更のポイント

    *   **アイコンプレビューの導入:** `link_type` の `Select` フィールドの隣に、選択されたアイコンをリアルタイムで表示するプレビューを追加します。これにより、ユーザーは選択したアイコンが実際にどのように表示されるかを即座に確認でき、視覚的なフィードバックが向上します。
    *   **デフォルトアイコンの明示:** `link_type` が `default` の場合に表示されるアイコンを明確にし、ユーザーがその意味を理解しやすくします。
    *   **翻訳の徹底:** UI上の全ての文字列は、ハードコーディングせず、翻訳ファイル経由で表示されることを改めて確認します。

    #### 2. 詳細設計

    #### 2.1. データ構造の変更

    1.  **`auto_links`テーブルへのカラム追加**
        *   `database/migrations/..._create_auto_links_table.php` を修正します。
        *   **カラム名:** `link_type`
        *   **型:** `string`
        *   **属性:** `nullable`
        *   **目的:** リンクの種類を示す識別子（例: `default`, `external`, `document`, `ticket`）を保存します。

    2.  **`AutoLink`モデルの更新**
        *   `app/Models/AutoLink.php` の `$fillable` 配列に `'link_type'` を追加します。

    #### 2.2. 設定ファイルの新設

    1.  **`config/ledgerleap.php` の作成**
        *   アイコンと識別子のマッピングを一元管理するため、新しい設定ファイルを作成します。
        *   **内容:**
            ```php
            return [
                'auto_links' => [
                    'link_types' => [
                        'default' => [
                            'icon' => 'o-link',
                            'label_key' => 'auto_links.link_types.default', // 翻訳キー
                        ],
                        'external' => [
                            'icon' => 'o-arrow-top-right-on-square',
                            'label_key' => 'auto_links.link_types.external',
                        ],
                        'document' => [
                            'icon' => 'o-document-text',
                            'label_key' => 'auto_links.link_types.document',
                        ],
                        'ticket' => [
                            'icon' => 'o-ticket',
                            'label_key' => 'auto_links.link_types.ticket',
                        ],
                        // ... 他のタイプを追加可能
                    ],
                ],
            ];
            ```

    #### 2.3. 管理UIの改修 (`AutoLinkResource`)

    `app/Filament/Resources/AutoLinkResource.php` の `form()` メソッドを修正します。

    1.  **リンクタイプ選択フィールドの追加:**
        *   `Select::make('link_type')` を追加します。
        *   `options()` メソッドで、`config('ledgerleap.auto_links.link_types')` を読み込み、選択肢を動的に生成します。
        *   `allowHtml()` を `true` に設定し、選択肢内のHTMLを有効化します。
        *   `default('default')` を設定します。
        *   `live()` メソッドの追加: 選択が変更されたときにリアルタイムでフォームを更新するために `live()` を追加します。
        *   `afterStateUpdated()` メソッドの追加: `link_type` が更新された際に、プレビュー用の状態を更新するロジックを追加します。

    2.  **アイコンプレビューの追加:**
        *   `Select::make('link_type')` の直後に `Placeholder::make('icon_preview')` を追加します。
        *   このプレースホルダーの `content()` メソッド内で、現在の `link_type` の値に基づいてアイコンをレンダリングします。
        *   `getState()` を使用して現在の `link_type` の値を取得し、`config('ledgerleap.auto_links.link_types.'.$state.'.icon', 'o-link')` のようにしてアイコンクラス名を取得します。
        *   取得したアイコンクラス名を使って、`Blade::render()` で `<x-icon>` コンポーネントのHTMLを生成し、表示します。
        *   ヘルプテキストの追加: プレビューの下に、このアイコンが何を示すのかを説明するヘルプテキスト（翻訳済み）を表示します。

        ```php
        // app/Filament/Resources/AutoLinkResource.php の form() メソッド内
        // ...
        Forms\Components\Select::make('link_type')
            ->label(__('auto_links.link_type'))
            ->helperText(__('auto_links.link_type_helper'))
            ->options(
                collect(config('ledgerleap.auto_links.link_types'))
                    ->mapWithKeys(function ($type, $key) {
                        $label = __($type['label_key']);
                        $icon = $type['icon'];
                        return [$key => Blade::render("<x-icon name='{$icon}' class='inline-block h-4 w-4' /> {$label}")];
                    })
                    ->all()
            )
            ->allowHtml()
            ->default('default')
            ->live() // リアルタイム更新を有効にする
            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                // プレビューを更新するために、ダミーの値をセットするなどしてフォームを再描画させる
                // または、Placeholderのcontent()がgetState()を直接参照するようにする
            }),

        Forms\Components\Placeholder::make('icon_preview')
            ->label(__('auto_links.icon_preview')) // 新しい翻訳キー
            ->content(function (Forms\Get $get) {
                $linkType = $get('link_type') ?? 'default'; // デフォルト値を考慮
                $iconName = config('ledgerleap.auto_links.link_types.'.$linkType.'.icon', 'o-link');
                $labelKey = config('ledgerleap.auto_links.link_types.'.$linkType.'.label_key', 'auto_links.link_types.default');
                $label = __($labelKey);

                return Blade::render(<<<HTML
                    <div class="flex items-center space-x-2">
                        <x-icon name="{$iconName}" class="h-6 w-6 text-primary-500" />
                        <span class="text-gray-600 dark:text-gray-400">{$label}</span>
                    </div>
                HTML);
            })
            ->columnSpanFull(), // 全幅を使用
        // ...
        ```

    #### 2.4. リンク生成ロジックの修正 (`AutoLinkService`)

    *   `AutoLink`モデルの `link_type` の値から、設定ファイル経由でアイコンクラス名を取得。
    *   `title`属性とアイコンのHTMLを生成。

    #### 2.5. 翻訳ファイルの更新

    `lang/ja/auto_links.php` に、以下のキーと翻訳を追加します。

    *   `link_type`: "リンクの種類" (フィールドラベル)
    *   `link_type_helper`: "リンクの目的を示すアイコンを選択します。選択されたアイコンは、リンクの横に表示されます。" (ヘルプテキスト)
    *   `tooltip_prefix`: "自動リンク: "
    *   `icon_preview`: "選択されたアイコン"
    *   `link_types`:
        *   `default`: "デフォルト"
        *   `external`: "外部リンク"
        *   `document`: "ドキュメント"
        *   `ticket`: "チケット"

