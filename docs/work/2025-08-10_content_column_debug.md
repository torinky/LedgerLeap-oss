# `LedgerDiff` の `content` カラムにおけるJSON不正形式問題の調査

## 1. 問題の概要
`app/Livewire/Ledger/Show.php` の `it_prepares_content_diff_correctly()` テストが、`hasChangedColumns` プロパティが `true` にならないために失敗していました。詳細な調査の結果、`prepareContentDiff()` メソッド内で比較対象となる古いコンテンツ (`oldContentArray`) が空の配列として認識されていることが根本原因であることが判明しました。

## 2. 調査の経緯と発見

### 2.1. `oldContentArray` が空であることの特定

*   `prepareContentDiff()` メソッド内のデバッグログにより、`oldContentArray` がデコード処理の直後には正しくデータが格納されているにもかかわらず、その直後のログ出力では空の配列として表示されるという矛盾が確認されました。
*   これは、Livewireのプロパティのシリアライズ/デシリアライズプロセス中に値が失われている可能性を示唆していました。

### 2.2. `LedgerDiff` モデルの `content` の不正形式

*   `LedgerDiff` モデルの `content` 属性を `getRawOriginal()` メソッドで取得した際に、期待されるJSON文字列ではなく、二重にエスケープされた不正な形式のJSON文字列が返されていることが判明しました。
    *   例: `["{\"data\":[\"Updated Text\",456,\"Option A\",\"New Value\"]}"]`
*   この不正な形式の文字列が `prepareContentDiff()` 内の `json_decode` を失敗させ、結果として `oldContentArray` が空になる原因となっていました。

### 2.3. `Ledger` モデルの `content` との比較

*   `Ledger` モデルの `content` 属性（こちらも `LONGTEXT` 型で `AsColumnArrayJson` キャストを使用）を `getRawOriginal()` で取得したところ、こちらは正しくフォーマットされたJSON文字列が返されることが確認されました。
    *   例: `{"data":["Updated Text",456,"Option A","New Value"]}`
*   この違いは、問題が `LedgerDiff` モデルに特有であり、`LedgerDiff` テーブルの特定の構成（Mroongaエンジンと `content` カラムの `FULLTEXT` インデックス）に起因する可能性が高いことを示唆しています。

### 2.4. `AsColumnArrayJson` キャストの修正

*   ユーザーからのヒント「contentカラムは第一階層が配列のjsonですが、laravelはこれをうまく処理できません。」に基づき、`AsColumnArrayJson` キャストを修正し、`set()` メソッドでコンテンツを `{"data": [...]}` の形式でラップし、`get()` メソッドでそれをアンラップするように変更しました。
*   この変更後も、`LedgerDiff` モデルの `getRawOriginal('content')` は依然として不正な形式のJSON文字列を返しました。これは、`getRawOriginal()` がキャストをバイパスするため、データベースに実際に保存されている値が問題であることを示しています。

### 2.5. `MySqlGrammar.php` のヒント

*   `app/Database/Query/Grammars/MySqlGrammar.php` が提供されました。このファイルは `wrapJsonPathSegment()` をオーバーライドしており、JSONパスの処理に関するカスタムロジックが含まれています。
*   しかし、`getRawOriginal()` は Laravel のJSONパス文法をバイパスするため、このファイルが直接的な原因である可能性は低いと判断しました。ただし、MroongaとLaravelのJSON処理の相互作用において、何らかの関連がある可能性は否定できません。

## 3. 現在の状況と課題

*   `AsColumnArrayJson` キャストは、`Ledger` モデルでは正しく機能しています。
*   `LedgerDiff` モデルの `content` カラムは、Mroongaの `FULLTEXT` インデックス（`COLUMN_VECTOR` フラグ付き）が原因で、`getRawOriginal()` が不正な形式のJSON文字列を返すという問題が継続しています。
*   `prepareContentDiff()` メソッドは、この不正な形式のJSONをデコードしようとしますが、依然として `oldContentArray` が空になるため、差分計算が正しく行われません。

## 4. 今後の方向性

`LedgerDiff` モデルの `content` カラムから `getRawOriginal()` で取得される値が不正な形式であるという根本原因に対処する必要があります。Mroongaの全文検索機能が必須であるため、インデックスを削除することはできません。

考えられるアプローチは以下の通りです。

1.  **`prepareContentDiff()` のデコードロジックのさらなる堅牢化:** 現在の不正な形式のJSON文字列を確実にデコードできるように、`prepareContentDiff()` 内の `json_decode` ロジックをさらに調整します。これは、Mroongaが返す可能性のあるあらゆる形式の不正なJSONに対応できる必要があります。
2.  **MroongaとLaravelの相互作用の深掘り:** Mroongaの `FULLTEXT` インデックスが `LONGTEXT` カラムの `getRawOriginal()` にどのように影響しているかをさらに調査します。LaravelのコミュニティやMroongaのドキュメントで、同様の問題や推奨されるプラクティスがないかを探します。
3.  **`LedgerDiff` の `content` 取得方法の見直し:** `getRawOriginal()` 以外の方法で `LedgerDiff` の `content` を取得し、それが正しい形式であるかを確認します。例えば、`$ledgerDiff->content` のようにアクセサ経由で取得した場合の挙動を確認します。もしアクセサ経由で正しく取得できるのであれば、`prepareContentDiff()` で `getRawOriginal()` の代わりにアクセサを使用することを検討します。

この問題は、MroongaとLaravelの特定の組み合わせに起因する複雑な問題であり、慎重なデバッグと調査が必要です。

## 5. `Ledger` と `LedgerDiff` の `content` カラムの挙動比較テスト計画

### 5.1. 目的

`Ledger` モデルと `LedgerDiff` モデルの `content` カラムがDBスキーマレベルで同一であるにもかかわらず、`LedgerDiff` でのみ `getRawOriginal()` が不正な形式のJSONを返す問題が顕在化している。この挙動の違いを明確にするため、両モデルの `content` カラムの保存・取得時の挙動をテストで比較する。

### 5.2. テスト内容

1.  一時的なテストファイル `tests/Feature/MroongaJsonTest.php` を作成する。
2.  テスト内で、`Ledger` モデルと `LedgerDiff` モデルのレコードをそれぞれ作成し、`content` カラムにJSONデータを保存する。
3.  保存後、それぞれのモデルの `content` カラムを、以下の2つの方法で取得し、その結果をLaravelのログ (`storage/logs/laravel.log`) に出力する。
    *   アクセサ経由 (`$model->content`)
    *   `getRawOriginal()` 経由 (`$model->getRawOriginal('content')`)

### 5.3. 実行コマンド

`sail artisan test --filter MroongaJsonTest`

### 5.4. 期待される結果と分析

*   ログに出力される `getRawOriginal()` の結果を比較し、両モデルでどのような文字列が返されるかを確認する。
*   もし `Ledger` モデルの `content` も不正な形式になるのであれば、問題は `LedgerDiff` に特有のものではなく、Mroongaの `FULLTEXT` インデックスが適用された `LONGTEXT` + `COLUMN_VECTOR` カラム全般に言えることになる。
*   ログに出力される具体的な文字列から、不正な形式のパターンをより詳細に把握し、今後の解決策の検討に役立てる。

## 6. これまでの確認結果と考察

### 6.1. DBスキーマの確認結果

`ledgers` テーブルと `ledger_diffs` テーブルの `content` カラムは、DBスキーマレベルで**全く同じ定義**であることが確認されました。
両方とも `LONGTEXT` 型で、`COMMENT 'flags "COLUMN_VECTOR"'` が付与され、Mroongaの `FULLTEXT` インデックスが適用されています。

### 6.2. `MroongaJsonTest` の実行結果と考察

`MroongaJsonTest` の実行により、以下の重要な事実が判明しました。

*   `Ledger` モデルと `LedgerDiff` モデルの両方で、`getRawOriginal()` で取得した `content` は、**二重にエスケープされたJSON文字列 (`["{\"data\":[\"...\"]}"]`)** になることが明確になりました。
    *   これは、Mroongaの `FULLTEXT` インデックスが適用された `LONGTEXT` + `COLUMN_VECTOR` カラムのデータが、Mroongaによって内部的にこの形式で保持されているためと推測されます。
    *   `getRawOriginal()` はDBから生の値を取得するため、このMroongaの内部表現がそのまま返されていると考えられます。

*   `AsColumnArrayJson` キャストの修正（最初の修正）により、アクセサ経由 (`$model->content`) で取得した `content` は、期待通りのPHP配列 (`["Hello World!",123]` や `["Changed Text",456]`) になっていることが確認されました。
    *   これは、`AsColumnArrayJson` キャストが、Mroongaの内部表現である二重にエスケープされたJSON文字列を正しくデコードし、`data` キーをアンラップできるようになったことを意味します。

*   しかし、`it_prepares_content_diff_correctly()` テストが `Undefined array key 0` エラーで失敗しました。
    *   これは、`AsColumnArrayJson` キャストの `get()` メソッドが、`ShowTest.php` のコンテキストで渡される `content` の形式 (`{"data":["___serialized___"]}` のような連想配列) に対応できていなかったためです。

### 6.3. `AsColumnArrayJson.php` の修正の試みと課題

`AsColumnArrayJson.php` の `get()` メソッドを、`["{\"data\":[\"...\"]}"]` 形式と `{"data":["..."]}` 形式の両方に対応できるように修正を試みましたが、`replace` ツールの `old_string` とファイル内容の厳密な一致の問題により、修正が完了していません。

### 6.4. 今後の解決策の方向性

1.  **`AsColumnArrayJson` キャストの `get()` メソッドの修正を完了させる。** これが最優先です。
2.  **`app/Livewire/Ledger/Show.php` の `prepareContentDiff()` メソッドの修正:** 
    *   `$comparisonTargetDiff->getRawOriginal('content')` を使用する代わりに、`$comparisonTargetDiff->content` のようにアクセサ経由で `content` を取得するように変更します。
    *   これにより、`AsColumnArrayJson` キャストが正しく機能するため、`prepareContentDiff()` は正しいPHP配列を受け取ることができ、手動デコードロジックは不要になるか、大幅に簡略化できます。
3.  **`app/Livewire/Ledger/Show.php` の `findComparisonTargetDiff()` メソッドの修正:** 
    *   `whereRaw('content != ?', [$currentRawContent])` を削除し、アプリケーション側で `json_encode($diff->content) !== json_encode($currentContent)` のように比較するように変更します。
