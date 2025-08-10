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
