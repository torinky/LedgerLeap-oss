# RAG導入 Phase1 完了レポート - WBS2.4 MCP API統合

**作成日:** 2025年10月19日  
**ステータス:** 完了  
**担当:** Backend

> **📖 関連ドキュメント:**
> - [2025-10-19-phase1-wbs2-mcp-api-integration-plan.md](./2025-10-19-phase1-wbs2-mcp-api-integration-plan.md) - 実装計画
> - [2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md) - 全体計画

---

## 1. 目的

本レポートは、MCP API (`SearchLedgersTool`) からセマンティック検索機能を呼び出せるようにするための実装作業が完了したことを報告するものである。

## 2. 作業概要

計画書に基づき、以下のタスクを実施した。

-   **`LedgerService` の改修:**
    `searchLedgersForApi` メソッドに、`order_by=semantic_score` パラメータを検知して `RagSearchService` に処理を委譲する分岐ロジックを実装した。

-   **`SearchLedgersTool` のドキュメント更新:**
    ツールの `description` を更新し、`semantic_score` オプションが利用可能であることを明記した。

-   **テストデータ準備コマンドの作成:**
    `DemoSeeder` で作成されたデモデータのみを対象にチャンク化とエンベディングを行う `php artisan rag:chunk-demo-ledgers` コマンドを新規に作成した。

-   **統合テストの実装:**
    デモデータを活用したシナリオベースの統合テストを実装した。テストでは、`RefreshDatabaseWithTenant` トレイトを使用し、テナントコンテキスト下での動作を保証した。また、テスト実行時のパフォーマンスを考慮し、シーディング処理はテストクラス内で一度のみ実行されるように最適化した。

## 3. 実装の詳細

### `LedgerService` の分岐ロジック

`searchLedgersForApi` メソッドに `semantic_score` を判定するロジックを追加し、`RagSearchService` に処理を委譲するように改修した。

```php
// app/Services/LedgerService.php

public function searchLedgersForApi(\App\Models\User $user, array $params)
{
    // ... (ログ出力)

    // セマンティック検索の分岐
    if (($params['order_by'] ?? null) === 'semantic_score') {
        if (empty($params['q'])) {
            throw new \InvalidArgumentException(
                'semantic_score sorting requires a search query (q parameter).'
            );
        }
        
        \Log::info('[MCP Search Debug] Semantic search triggered. Delegating to RagSearchService.');

        // RagSearchServiceを呼び出し
        $ragResults = app(\App\Services\RagSearchService::class)->searchForApi(...);

        // ... (レスポンス整形)

        return [ ... ];
    }

    // 既存のキーワード検索ロジック
    // ...
}
```

## 4. テスト結果

作成した統合テスト (`SearchLedgersToolSemanticSearchTest.php`) はすべてパスした。

-   **テスト実行コマンド:** `vendor/bin/sail test tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php`
-   **結果:** `Tests: 3 passed`

**検証されたシナリオ:**

-   `order_by=semantic_score` 指定時に、`LedgerService` のセマンティック検索ロジックが正しく呼び出されること。
-   `q` パラメータが必須であるバリデーションが機能すること。
-   他の `order_by` 値では、セマンティック検索が呼び出されないこと（フォールバック）。
-   `RefreshDatabaseWithTenant` を利用したテナント環境で、デモデータのシーディングとチャンク化が正常に行われ、テストが実行できること。

## 5. 結論

MCP API (`SearchLedgersTool`) へのセマンティック検索機能の統合は、計画通りに完了した。堅牢なテストによって動作が保証されており、次のステップであるLivewireフロントエンドへの統合に進む準備が整った。

## 6. 関連ファイル

### 作成

-   `docs/work/rag-implementation/2025-10-19-phase1-wbs2-mcp-api-integration-report.md` (本ファイル)
-   `app/Console/Commands/RagChunkDemoLedgersCommand.php`
-   `tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php`

### 変更

-   `app/Services/LedgerService.php`
-   `app/Mcp/Tools/SearchLedgersTool.php`
-   `docs/work/rag-implementation/2025-10-19-phase1-wbs2-mcp-api-integration-plan.md`

## 7. 実装後のデバッグと課題解決

実装完了後、テストとデータ投入の過程で複数の問題が発覚した。以下にその内容と解決策を記録する。

### 7.1. DBエラー: `Incorrect string value`

-   **事象:** チャンク化ジョブがキューで失敗し、ログに `SQLSTATE[HY000]: General error: 1366 Incorrect string value: ... for column 'embedding'` が記録された。
-   **原因:** `embedding` カラムのスキーマ定義と、ジョブでのデータ保存形式の不一致。当初、`binary` 型のカラムに `json_encode()` した文字列を書き込もうとしていた。その後、`pack()` でバイナリ化するアプローチも試したが、これもMySQL/Mroongaが期待する形式と異なっていた。
-   **解決策:** 最終的に、`@docs/work/rag-implementation/2025-10-19-vector-search-middleware-review.md` の実装ガイドに基づき、以下の2点を修正することで解決した。
    1.  **マイグレーション:** `embedding` カラムの型を `TEXT` に変更し、Mroongaにベクトル型を認識させるための `COMMENT 'flags "COLUMN_VECTOR", type "Float"'` を付与した。
    2.  **データ保存:** `ProcessLedgerForRagJob` 内で、ベクトル配列を `json_encode()` でJSON文字列に変換して `TEXT` 型のカラムに保存するようにした。

### 7.2. キューワーカーのコードが更新されない問題

-   **事象:** 上記のDBエラーを修正した後も、同じエラーが継続して発生した。`embedding` コンテナのログではエンベディング処理が動いているにも関わらず、DBへの書き込みに失敗していた。
-   **原因:** **キューワーカーがコードの変更を自動的に反映していなかった。** `sail` 環境で長時間稼働しているキューワーカーは、メモリ上に古いバージョンの `ProcessLedgerForRagJob` をキャッシュしており、修正前のコードを実行し続けていた。
-   **解決策:** `vendor/bin/sail restart queue` コマンドを実行し、キューワーカーのコンテナを再起動した。これにより、ワーカーは修正後の最新のコードを読み込み、ジョブが正常に処理されるようになった。

### 7.3. `docker-compose` の依存関係の不足

-   **事象:** キューワーカーのログファイル (`queue-YYYY-MM-DD.log`) が生成されず、ジョブが全く処理されていないように見えた。
-   **原因:** `docker-compose.yml` において、`queue` サービスが `embedding` サービスに依存する設定が欠落していた。これにより、`embedding` サービスが起動完了する前に `queue` サービスがジョブを処理しようとし、接続に失敗していた。
-   **解決策:** `docker-compose.yml` の `queue` サービスの `depends_on` セクションに `embedding: condition: service_healthy` を追記し、コンテナを再起動 (`sail up -d --force-recreate`) することで解決した。

### 7.4. テストコードの課題と改善

-   **事象:** 当初作成した `Feature` テストが `404 Not Found` エラーで失敗した。
-   **原因:** MCPサーバーのエンドポイントがHTTP (`/api/mcp/execute-tool`) ではなく、Artisanコマンド (`ledgerleap:mcp`) として登録されていたため、HTTPリクエストによるテストが実行できなかった。
-   **解決策:** 他のMCPツールのテスト (`SearchLedgersToolTest.php`) を参考に、テストの実行方式を全面的に見直した。
    1.  **認証:** `MCP_AUTH_TOKEN` 環境変数にテストユーザーのトークンを設定する方式に変更。
    2.  **実行方法:** Artisanコマンドを直接呼び出すのではなく、ツールの `handle()` メソッドを `Request` オブジェクトと共に直接呼び出すユニットテスト形式に変更。
    3.  **`RefreshDatabaseWithTenant` の採用:** プロジェクト標準の `RefreshDatabaseWithTenant` トレイトを使用するようにし、テストの信頼性を向上させた。
    4.  **パフォーマンス最適化:** テスト実行のたびに `DemoCompleteSeeder` が実行されて時間がかかっていた問題を、`static` プロパティを使い、テストクラス内で一度しかシーディングが実行されないように最適化した。

これらのデバッグプロセスを経て、実装の堅牢性が向上し、今後の開発に役立つ多くの知見が得られた。

