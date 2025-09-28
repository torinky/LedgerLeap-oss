# MCP応答最適化計画：LLMのためのテンプレート実装

**日付:** 2025年9月28日

**関連ドキュメント:**
- [LedgerLeap MCPサーバー実装計画](./2025-09-27_MCP_Server_Implementation_Plan.md)
- [MCPプロンプトと応答内容の設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md)

---

## 1. 概要

### 1.1. 目的

本計画は、[MCPサーバー実装計画](./2025-09-27_MCP_Server_Implementation_Plan.md)で構築したMCPサーバーが返すレスポンスを、LLMがより深く解釈し、[プロンプト設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md)で定義された「あるべき姿」の応答を自律的に生成できるように最適化することを目的とする。

具体的には、MCPレスポンス自体に「LLMへの指示」や「表示用テンプレート」のヒントを含めることで、単なるJSONデータの整形に留まらない、文脈に応じた質の高い応答を実現する。

### 1.2. 最初のターゲット

最初の実装ターゲットとして、以下のユースケースを設定する。

- **ペルソナ:** 実務担当者 (Operational Staff)
- **質問:** 「昨日私が作成した日報を見せて。」

---

## 2. 実装計画

この計画は、以下のステップで進める。

### 2.1. ステップ1: 事前準備とリグレッション防止

1.  **既存検索機能のテストカバレッジ確認:**
    -   `tests/Feature/LedgerLookupControllerTest.php` が、キーワード検索、フォルダID、台帳定義IDによるフィルタリングをカバーしていることを確認済み。
    -   `LedgerService::searchLedgersForApi` メソッドの既存の検索ロジック（キーワード、タグ、フォルダID、台帳定義IDなど）が十分にテストでカバーされているかを確認する。
2.  **既存検索機能のテスト作成（不足している場合）:**
    -   もし既存の検索ロジックをカバーするテストが不足している場合は、`spatie/laravel-query-builder` 導入前にテストを追加し、リグレッションを防止する。

### 2.2. ステップ2: 検索機能の拡張と`LedgerService`のリファクタリング

ターゲットユースケースを実現するためには、「作成者」と「期間」による絞り込み機能が必須となる。

#### 2.2.1. 方針

今後の拡張性と保守性を考慮し、**`spatie/laravel-query-builder`** ライブラリを導入する。これにより、HTTPリクエストのパラメータから動的にEloquentクエリを構築し、フィルタリングやソートを容易に実装する。

#### 2.2.2. 主なタスク

1.  **ライブラリのインストール:**
    -   `composer require spatie/laravel-query-builder` を実行する。
2.  **`LedgerService`の改修:**
    -   `app/Services/LedgerService.php` の `searchLedgersForApi` メソッドを改修し、`spatie/laravel-query-builder` を利用してクエリを構築するロジックに変更する。
    -   以下のフィルタを実装する。
        -   `AllowedFilter::exact('creator_id')`: 作成者IDによる完全一致フィルタ。
        -   `AllowedFilter::scope('created_between')`: 作成日時による期間フィルタ（開始日と終了日を指定）。
        -   既存のキーワード検索（Mroonga）やタグ検索との連携も維持する。

### 2.3. ステップ3: MCPレスポンスの最適化と`SearchLedgersTool`の修正

LLMが応答を生成しやすくなるよう、JSONレスポンスの構造を拡張する。

#### 2.3.1. 方針

**アプローチA: JSONレスポンスに「表示用の整形済みデータ」を追加する** を採用する。
構造化データを維持しつつ、LLMへの明確なヒントを追加できるため、柔軟性と確実性のバランスが良いと判断した。

#### 2.3.2. 主なタスク

1.  **`SearchLedgersTool`の改修:**
    -   `app/Mcp/Tools/SearchLedgersTool.php` の `schema()` メソッドに、オプションパラメータ `format` (enum: `raw`, `summary`, デフォルト: `raw`) を追加する。
    -   `handle()` メソッド内で、`format` パラメータが `summary` の場合、`LedgerService` から受け取った結果を加工する処理を追加する。
    -   `schema()` メソッドに、新しいフィルタ用のパラメータ (`creator_id`, `created_from`, `created_to` など) を追加する。
    -   `handle()` メソッド内で、これらのパラメータを `spatie/laravel-query-builder` が解釈できる形式で `LedgerService` に渡すように調整する。
2.  **レスポンス加工処理の実装:**
    -   `Ledger` モデルの検索結果（コレクション）をループし、各モデルに対して以下のキーを持つ `__display_fields__` オブジェクトを追加する。
        -   **件名:** 台帳のタイトル (`ledger_define.title` や `content` から抽出)
        -   **ステータス:** `status` の値（例: `pending_approval`）を人間可読な文字列（例: 「承認待ち」）に変換する。
        -   **更新日時:** `updated_at` を分かりやすい形式（例: `Y年m月d日 H:i`）にフォーマットする。
    -   レスポンスのトップレベルに、応答全体の要約文となる `__summary__` キーを追加する。（例: `あなたが昨日作成した日報は1件です。`）

#### 2.3.3. 最適化後のJSONレスポンス（例）

```json
{
    "ledgers": [
        {
            "id": 112,
            "status": "pending_approval",
            "updated_at": "2025-09-26T18:30:00.000000Z",
            // ... 元のデータ ...

            "__display_fields__": {
                "件名": "2025年9月26日 営業日報",
                "ステータス": "承認待ち",
                "更新日時": "2025年9月26日 18:30"
            }
        }
    ],
    "total": 1,
    "__summary__": "あなたが昨日作成した日報は1件です。"
}
```

### 2.4. ステップ4: テストと検証

1.  **新規フィルタのフィーチャーテスト作成:**
    -   `creator_id` によるフィルタリングが正しく機能することを確認するテストを追加する。
    -   `created_at` による期間フィルタリングが正しく機能することを確認するテストを追加する。
    -   これらのテストは `tests/Feature/Api/LedgerControllerTest.php` に追加する。
2.  **MCPツールクラスのユニットテスト作成:**
    -   `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` を新規作成する。
    -   `SearchLedgersTool` の `handle` メソッドを呼び出し、返される `Laravel\Mcp\Response` オブジェクトの内容（特に `__display_fields__` と `__summary__` の生成ロジック）が期待通りであることを検証する。
3.  **MCPサーバー起動テスト作成:**
    -   `tests/Feature/McpServerTest.php` を新規作成する。
    -   `mcp:start` コマンドがエラーなく起動することを確認するテストを追加する。
4.  **既存フィーチャーテストの実行と確認:**
    -   `vendor/bin/sail artisan test tests/Feature/LedgerLookupControllerTest.php` を実行し、`spatie/laravel-query-builder` 導入後も既存の検索機能にリグレッションがないことを確認する。
5.  **手動での動作確認:**
    -   `gemini` CLI を通じて、新しいフィルタパラメータと `format: summary` を指定した検索を行い、期待通りの結果とフォーマットで応答が返ってくることを確認する。

### 2.5. ステップ5: ドキュメント更新

1.  **関連ドキュメントの更新:**
    -   APIドキュメント（もしあれば）に、新しい検索パラメータについて追記する。

### 2.6. ステップ6: プロンプトチューニング

1.  **LLMへの指示:**
    -   `gemini` のシステムプロンプト（またはツール呼び出し時のプロンプト）に、以下のような指示を含めることを検討する。
    > あなたはLedgerLeapシステムのアシスタントです。ツールからの応答に `__summary__` が含まれる場合、それを応答の冒頭に記述してください。また、`__display_fields__` を含むオブジェクトのリストがある場合、その内容を人間にとって分かりやすい箇条書き形式で提示してください。
2.  **テスト:**
    -   `@ledgerleap-api SearchLedgers q: "日報" format: "summary"` のようなツール呼び出しを伴う自然言語プロンプト（「昨日私が作成した日報を要約して見せて」など）を `gemini` に与える。
    -   LLMが最適化されたJSONレスポンスを正しく解釈し、設計案通りの応答を生成できるかを確認する。

---

## 3. 今後の展望

本計画で確立した手法を他のツール（`GetLedgerDefinesTool`, `CreateLedgerTool`など）にも展開し、システム全体としてLLMとの対話品質を向上させていく。


    1 ## 5. デバッグと問題解決の記録
    2 
    3 ### 5.1. `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` のデバッグ履歴
    4 
    5 #### 5.1.1. `TypeError: Cannot assign Laravel\Sanctum\NewAccessToken to property ...::$accessToken` の発生と解決
    6 
    7 -   **問題の概要:** `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` の `setUp` メソッドで、`$this->user->createToken('test-token')` の戻り値 (`Laravel\Sanctum\NewAccessToken`
      型) を、`Laravel\Sanctum\PersonalAccessToken` 型として宣言された `$this->accessToken` プロパティに直接代入しようとしたため、`TypeError` が発生した。
    8 -   **原因:** `createToken` メソッドは `NewAccessToken` オブジェクトを返し、`PersonalAccessToken` インスタンスは `NewAccessToken` オブジェクトの `accessToken` 
      プロパティに格納されている。
    9 -   **解決策:** `$this->accessToken = $this->user->createToken('test-token')->accessToken;` と修正し、`NewAccessToken` オブジェクトから `PersonalAccessToken` 
      インスタンスを正しく取得するようにした。

#### 5.1.2. `ParseError: syntax error, unexpected token "}"` の発生と解決

-   **問題の概要:** `app/Mcp/Tools/SearchLedgersTool.php` の `handle` メソッド内の `summary` 生成ロジックで、`if ($results['total'] > 0) { $summary = "あなたが作成した台帳は{$results['total']}件です。"` の行の末尾にセミコロン `;` が抜けていたため、`ParseError` が発生した。
-   **原因:** PHP の構文エラー。
-   **解決策:** 該当行の末尾にセミコロン `;` を追加した。

#### 5.1.3. `Call to undefined method Laravel\Mcp\Request::fromArray()` の発生と解決

-   **問題の概要:** `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` 内で `Request::fromArray([])` を呼び出している箇所で、`Laravel\Mcp\Request` クラスに `fromArray`メソッドが存在しないため、`Error` が発生した。
-   **原因:** `Laravel\Mcp\Request` クラスには `fromArray` メソッドが存在しない。インスタンス化にはコンストラクタを使用する必要がある。
-   **解決策:** `Request::fromArray($params)` を `new Request($params)` に修正した。この修正は複数箇所に存在したため、`replace_regex`を使用して一括置換を試みたが、エスケープの問題で失敗したため、最終的に `replace` ツールで各箇所を個別に修正した。

#### 5.1.4. `ParseError: syntax error, unexpected token "\\"` の発生と解決

-   **問題の概要:** `Request::fromArray(` を `new Request(` に置換した際に、`new Request\([])` のようにバックスラッシュが誤って挿入されてしまい、`ParseError` が発生した。
-   **原因:** `replace_regex` ツールの `repl` 引数でバックスラッシュのエスケープが正しく行われなかったため。
-   **解決策:** `replace` ツールで各箇所を個別に修正し、`new Request\([])` を `new Request([])` に修正した。

#### 5.1.5. `QueryException: Unknown column 'tenant_id' in 'field list'` の発生と解決

-   **問題の概要:** `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` の `setUp` メソッドで `User::factory()->create(['tenant_id' => $tenant->id])` とした際に、`users`テーブルに `tenant_id` カラムが存在しないため、`QueryException` が発生した。
-   **原因:** `User` モデルはグローバルモデルであり、`tenant_id` カラムを持たない。テストコードが `User` モデルをテナントに属するモデルとして扱おうとしたため。
-   **解決策:** `User::factory()->create(['tenant_id' => $tenant->id])` から `tenant_id` の指定を削除し、`User::factory()->create()` に修正した。

#### 5.1.6. `Call to undefined method Laravel\Mcp\Response::status()` の発生と解決

-   **問題の概要:** `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` 内で `$response->status()` を呼び出している箇所で、`Laravel\Mcp\Response` クラスに `status()`メソッドが存在しないため、`Error` が発生した。
-   **原因:** `Laravel\Mcp\Response` オブジェクトには `status()` メソッドが存在しない。エラーの有無は `isError()` メソッドで、コンテンツは `content()->__toString()`で取得する必要がある。
-   **解決策:** `dd($response)` で `Laravel\Mcp\Response` の構造を確認し、`$response->status()` を `$response->isError()` に、`$response->content()` を`$response->content()->__toString()` に修正した。この修正は複数箇所に存在したため、`replace` ツールで各箇所を個別に修正した。

#### 5.1.7. `NoMatchingExpectationException` の発生と解決

-   **問題の概要:** `it_calls_ledger_service_with_correct_parameters_for_raw_format()` メソッドで、`$this->ledgerService->shouldReceive('searchLedgersForApi')`で設定したモックの期待値と、実際に呼び出された `searchLedgersForApi` の引数が一致しないため、`NoMatchingExpectationException` が発生した。
-   **原因:** `SearchLedgersTool.php` の `handle` メソッド内で `created_from` と `created_to` を結合して `created_between` を作成し、`unset`する処理が実行されていなかったため、`LedgerService` に渡される `$parameters` に `created_from` と `created_to` がそのまま含まれていた。テストの `expectedServiceParams` は`created_between` を期待していたため、不一致が発生した。
-   **解決策:** `app/Mcp/Tools/SearchLedgersTool.php` の `handle` メソッド内で、`created_from` と `created_to` が存在する場合に、それらを結合して `created_between`パラメータを作成し、`LedgerService` に渡す `$parameters` から `created_from` と `created_to` を削除する処理を追加した。また、`tests/Unit/Mcp/Tools/SearchLedgersToolTest.php`の `expectedServiceParams` も `created_between` を含む形に修正した。

#### 5.1.8. `Failed asserting that two strings are equal.` の発生と解決

-   **問題の概要:** `it_returns_summary_format_with_display_fields_and_summary_text()` メソッドで、`$this->assertEquals('承認待ち',       $firstLedger['__display_fields__']['ステータス']);` のアサーションが失敗した。期待値は `'承認待ち'` だが、実際には `'pending_approval'` が返された。
-   **原因:** `app/Mcp/Tools/SearchLedgersTool.php` の `handle` メソッド内のステータス変換ロジックで、`$ledger->status` が `enum` 型として扱われていたため、`value`プロパティにアクセスする必要があった。
-   **解決策:** `$ledger->status` を `$ledger->status->value` に修正した。

#### 5.1.9. `ParseError: syntax error, unexpected token ";", expecting "]"` の発生と解決

-   **問題の概要:** `it_returns_summary_format_with_display_fields_and_summary_text()` メソッドの `$params` 配列の閉じ括弧 `]` の後に、誤ってアサーションが挿入されてしまい、`ParseError` が発生した。
-   **原因:** `replace` ツールの `old_string` と `new_string` の範囲が正しくなかったため。
-   **解決策:** `$params` 配列の閉じ括弧 `]` を追加し、その後に続くアサーションを正しい位置に配置するように修正した。

#### 5.1.10. `ParseError: Unclosed '{' on line ... does not match ']'` の発生と解決

-   **問題の概要:** `it_handles_empty_results_for_summary_format()` メソッドの `$params` 配列の定義が誤っており、余分な `];` が存在したため、`ParseError` が発生した。
-   **原因:** 前回の修正で `old_string` と `new_string` の範囲が正しくなかったため。
-   **解決策:** 余分な `];` を削除した。

### 5.2. `tests/Feature/Api/LedgerControllerTest.php` の `it_can_filter_ledgers_by_q()` テストのデバッグ履歴

#### 5.2.1. `TypeError: App\Services\LedgerService::{closure}(): Argument #1 ($q) must be of type App\Services\EloquentBuilder, Illuminate\Database\Query\Builder given`の発生と解決

-   **問題の概要:** `app/Services/LedgerService.php` の `searchLedgersForApi` メソッド内の `AllowedFilter::callback('q', ...)` クロージャで、`$q` の型ヒントが`EloquentBuilder` となっているにもかかわらず、実際に渡されるのが `Illuminate\Database\Query\Builder` であるために発生した。
-   **原因:** `Spatie\QueryBuilder\QueryBuilder` の `where` メソッドに渡されるクロージャの `$q` は、`Illuminate\Database\Query\Builder`のインスタンスであるため、型不一致が発生した。
-   **解決策:** クロージャの引数 `$q` の型ヒントを `Illuminate\Database\Query\Builder` に変更した。

#### 5.2.2. `Object of class Illuminate\Database\Query\Expression could not be converted to string` の発生と解決

-   **問題の概要:** `app/QueryFilters/MroongaFullTextFilter.php` の `__invoke` メソッドで `DB::raw("mroonga_escape(?)", [$value])` を使用した結果、`Illuminate\Database\Query\Expression` オブジェクトがバインディングとして渡され、それが文字列に変換できないために発生した。
-   **原因:** `whereRaw` のバインディングにはプリミティブ型を渡す必要がある。`DB::raw()` は `Illuminate\Database\Query\Expression`オブジェクトを返すため、バインディングとして渡すとエラーになる。
-   **解決策:** `app/QueryFilters/MroongaFullTextFilter.php` の `__invoke` メソッドを修正し、`mroonga_escape()` を使用する際に、`addslashes($value)` でエスケープした値を SQL クエリ文字列に直接埋め込むようにした。

#### 5.2.3. `ErrorException: Undefined property: App\QueryFilters\MroongaFullTextFilter::$columns` の発生と解決

-   **問題の概要:** `MroongaFullTextFilter` の `__invoke` メソッド内で `$this->columns` にアクセスしようとした際に、`$columns` プロパティが初期化されていないために発生した。
-   **原因:** `AllowedFilter::custom()` の第2引数に `MroongaFullTextFilter::class` (クラス名) を渡した場合、`Spatie\QueryBuilder`がコンストラクタの引数を自動的に解決できないため、`$columns` プロパティが初期化されないまま `__invoke` メソッドが呼び出されてしまう。
-   **解決策:** `app/Services/LedgerService.php` の `searchLedgersForApi` メソッド内の `AllowedFilter::custom('q', ...)` の部分を修正し、`AllowedFilter::custom()` の第2引数に`new \App\QueryFilters\MroongaFullTextFilter(['content', 'content_attached'])` のように `MroongaFullTextFilter` のインスタンスを直接渡すようにした。

#### 5.2.4. `TypeError: Spatie\QueryBuilder\AllowedFilter::custom(): Argument #2 ($filterClass) must be of type Spatie\QueryBuilder\Filters\Filter, string given` の発生と解決

-   **問題の概要:** `AllowedFilter::custom()` の第2引数に `Spatie\QueryBuilder\Filters\Filter` 型のインスタンスまたはクラス名を期待しているにもかかわらず、`string` (クラス名)が渡されているために発生した。
-   **原因:** `AllowedFilter::custom()` の第2引数にクラス名を渡す場合、`Spatie\QueryBuilder` はそのクラスを解決する際に、コンストラクタの引数を自動的に解決しようとするが、`MroongaFullTextFilter` のコンストラクタは `$columns` を受け取るため、クラス名を渡すだけでは `$columns` が初期化されない。
-   **解決策:** `app/Services/LedgerService.php` の `searchLedgersForApi` メソッド内の `AllowedFilter::custom('q', ...)` の部分を修正し、`AllowedFilter::custom()` の第2引数に`new \App\QueryFilters\MroongaFullTextFilter(['content', 'content_attached'])` のように `MroongaFullTextFilter` のインスタンスを直接渡すようにした。

---
