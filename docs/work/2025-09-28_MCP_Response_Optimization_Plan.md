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
    -   これらのテストは `tests/Feature/LedgerLookupControllerTest.php` に追加する。
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