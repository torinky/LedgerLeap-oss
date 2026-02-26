# RAG導入 Phase1 実装計画 - WBS2.4 MCP API統合

**作成日:** 2025年10月19日  
**ステータス:** 計画完了  
**担当:** Backend

> **📖 関連ドキュメント:**
> - [2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md) - 全体計画

---

## 1. 目的

本計画は、全体計画 WBS `2.4 LedgerService::searchLedgersForApi()` への統合タスクを具体化するものである。`RagSearchService` を `LedgerService` に統合し、MCP API (`SearchLedgersTool`) から `order_by=semantic_score` パラメータによるセマンティック検索を実行可能にすることを目的とする。

## 2. 実装方針

`LedgerService` の `searchLedgersForApi` メソッド内に、`order_by` パラメータを評価する分岐ロジックを追加する。`semantic_score` が指定された場合、処理を `RagSearchService` に委譲する。それ以外の場合は、既存の全文検索ロジックを維持する。このアプローチにより、既存機能への影響を最小限に抑えつつ、新機能を選択的に導入する。

## 3. WBS詳細

| ID | タスク | 担当 | 見積工数 | 成果物 |
| :--- | :--- | :--- | :--- | :--- |
| **2.4.1** | `LedgerService` の改修 | Backend | 0.25日 | `app/Services/LedgerService.php` |
| **2.4.2** | `SearchLedgersTool` のドキュメント更新 | Backend | 0.1日 | `app/Mcp/Tools/SearchLedgersTool.php` |
| **2.4.3** | **Artisanコマンド作成: デモデータチャンク化** | Backend | 0.5日 | `app/Console/Commands/RagChunkDemoLedgers.php` |
| **2.4.4** | **シナリオベース統合テストの作成** | Backend | 1.0日 | `tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php` |
| **2.4.5** | 動作確認とデバッグ | Backend | 0.15日 | ログ、APIレスポンス |
| | **合計** | | **2.0日** | |

## 4. 詳細設計

### 4.1. `LedgerService::searchLedgersForApi()` の改修

`app/Services/LedgerService.php`

**変更前のロジック:**
```php
public function searchLedgersForApi(\App\Models\User $user, array $params)
{
    // 既存の全文検索ロジックのみ
    // ...
}
```

**変更後のロジック:**
`order_by` パラメータを確認し、`semantic_score` であれば `RagSearchService` を呼び出す分岐処理を追加する。

```php
// app/Services/LedgerService.php

// ... (use文など)

class LedgerService
{
    // ... (既存のコンストラクタとメソッド)

    public function searchLedgersForApi(\App\Models\User $user, array $params)
    {
        \Log::info('[MCP Search Debug] === Start searchLedgersForApi ===');
        \Log::info('[MCP Search Debug] User ID: '.$user->id);
        \Log::info('[MCP Search Debug] Input params: '.json_encode($params, JSON_UNESCAPED_UNICODE));

        // ▼▼▼ ここから追加 ▼▼▼
        // セマンティック検索の分岐
        if (($params['order_by'] ?? null) === 'semantic_score') {
            if (empty($params['q'])) {
                throw new \InvalidArgumentException(
                    'semantic_score sorting requires a search query (q parameter).'
                );
            }
            
            \Log::info('[MCP Search Debug] Semantic search triggered. Delegating to RagSearchService.');

            // RagSearchServiceを呼び出し、結果をAPI形式に整形して返す
            $ragResults = app(\App\Services\RagSearchService::class)->searchForApi(
                $user,
                [
                    'query' => $params['q'],
                    'limit' => $params['limit'] ?? 20,
                    'filters' => [
                        'ledger_define_id' => $params['ledger_define_id'] ?? null,
                        'folder_id' => $params['folder_id'] ?? null,
                    ]
                ]
            );

            // APIのレスポンス形式に合わせる
            $ledgers = collect($ragResults)->pluck('ledger');
            $total = $ledgers->count();
            
            // メタデータ構築 (既存ロジックを参考に簡略化)
            $meta = $this->buildMetaData($ledgers);

            \Log::info('[MCP Search Debug] === End searchLedgersForApi (Semantic Search) ===');

            return [
                'ledgers' => $ledgers,
                'meta' => $meta,
                'total' => $total,
            ];
        }
        // ▲▲▲ ここまで追加 ▲▲▲

        // 既存のキーワード検索ロジック (変更なし)
        // ...
    }

    // ... (既存のメソッド)

    // ▼▼▼ ヘルパーメソッドとして追加 ▼▼▼
    private function buildMetaData(Collection $ledgers): array
    {
        $ledgerDefines = $ledgers->pluck('define')->filter()->unique('id');
        $creators = $ledgers->pluck('creator')->filter()->unique('id');
        $modifiers = $ledgers->pluck('modifier')->filter()->unique('id');
        $users = $creators->union($modifiers)->keyBy('id');

        $folders = collect();
        $ledgerDefines->each(function ($define) use (&$folders) {
            if ($define && $define->folder) {
                $folders->push($define->folder);
                if ($define->folder->relationLoaded('ancestors')) {
                    $folders = $folders->merge($define->folder->ancestors);
                }
            }
        });
        $uniqueFolders = $folders->unique('id');

        $uniqueFolders->each(function ($folder) {
            if ($folder->relationLoaded('ancestors')) {
                $path = $folder->ancestors->reverse()->pluck('name')->push($folder->name)->implode('/');
                $folder->setAttribute('path', '/'.$path);
            }
        });

        return [
            'ledger_defines' => $ledgerDefines->keyBy('id'),
            'folders' => $uniqueFolders->keyBy('id'),
            'users' => $users,
        ];
    }
}
```

### 4.2. `SearchLedgersTool` のドキュメント更新

`app/Mcp/Tools/SearchLedgersTool.php`

ツールの説明文 (`description` プロパティ) に、`order_by` パラメータの選択肢として `semantic_score` を追加する。

```php
// app/Mcp/Tools/SearchLedgersTool.php

protected string $description = <<<'MARKDOWN'
// ... (既存の説明) ...
**Sorting (ソート機能):**
- 'order_by': Field to sort by (default: composite_score)
  - 'composite_score': Overall importance combining activity, freshness, and workflow status
  - 'activity_score': Recent activity frequency (useful for "What's hot?" queries)
  - 'created_at': Creation date (useful for "Show recent entries")
  - 'updated_at': Last update date
  - 'semantic_score': Semantic relevance to search query (requires 'q' parameter). Finds records based on meaning, not just keywords.
// ... (既存の説明) ...
MARKDOWN;
```

## 5. テスト計画

### 5.1. テストデータ準備

本テストでは、`@docs/development/test-data-design.md` で設計されたデモデータを活用する。テスト実行の前提として、以下の手順でセマンティック検索用のテストデータを準備する。

1.  **デモデータの投入:**
    ```bash
    php artisan db:seed --class=DemoSeeder
    ```

2.  **デモデータのチャンク化:**
    新規に作成する `rag:chunk-demo-ledgers` コマンドを実行し、`[DEMO]` プレフィックスを持つ台帳のみを対象にチャンク化とエンベディングを行う。
    ```bash
    php artisan rag:chunk-demo-ledgers
    ```

#### 5.1.1. `rag:chunk-demo-ledgers` コマンド仕様

-   **目的:** `DemoSeeder` によって作成された台帳データのみをチャンク化の対象とする。
-   **対象レコード:** `ledger_defines` テーブルの `title` カラムが `[DEMO]` で始まる台帳定義に紐づく `ledgers` レコード。
-   **処理内容:**
    -   既存のデモデータ関連チャンクをクリアする。
    -   対象の台帳レコードに対して `ProcessLedgerForRagJob` をディスパッチする。

### 5.2. テストシナリオ（シナリオベース）

`tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php` に以下のテストシナリオを実装する。

#### シナリオ1: 業務報告書の横断検索

-   **目的:** 異なる種類の業務報告書（営業日報、開発日報、週報）から、意味的に関連する内容を横断検索できることを確認する。
-   **クエリ:** `「今日の業務内容について」`
-   **期待される結果:**
    -   `[DEMO] 営業日報` の `content` に含まれる「本日の活動概要」セクションを持つレコードが上位にランクインする。
    -   `[DEMO] 開発日報` の `content` に含まれる「作業進捗」セクションを持つレコードがランクインする。
    -   `[DEMO] 週報` の `content` に含まれる「今週のサマリー」を持つレコードがランクインする。
    -   キーワード「業務」を直接含まないレコードでも、内容が関連していれば検索結果に含まれる。

#### シナリオ2: 経費申請の目的検索

-   **目的:** 経費申請の `content` に含まれる自由記述の「目的」欄から、曖昧な表現で検索できることを確認する。
-   **クエリ:** `「顧客との打ち合わせで使った費用」`
-   **期待される結果:**
    -   `[DEMO] 経費申請` の `content` に「A社との定例ミーティングのための交通費」「クライアントとの会食費」といった内容が含まれるレコードが上位にランクインする。
    -   「打ち合わせ」「費用」という直接的な単語がなくても、「ミーティング」「交通費」「会食」など関連語句を含むレコードがヒットする。

#### シナリオ3: 障害報告の類似事例検索

-   **目的:** 過去の障害報告から、類似した内容の報告を検索できることを確認する。
-   **クエリ:** `「データベースの接続エラーについて」`
-   **期待される結果:**
    -   `[DEMO] 障害報告` の `content` に「DBコネクションがタイムアウトしました」「MySQLのパフォーマンスが低下」といった内容が含まれるレコードが上位にランクインする。
    -   エラーメッセージや技術的な詳細が類似しているレコードが、キーワードの一致度以上に高く評価される。

### 5.3. テストコード例

```php
// tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php

namespace Tests\Feature\Mcp;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SearchLedgersToolSemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 1. デモデータを準備
        Artisan::call('db:seed', ['--class' => 'DemoSeeder']);
        
        // 2. デモデータをチャンク化
        Artisan::call('rag:chunk-demo-ledgers');

        $this->user = User::where('email', 'admin@example.com')->first();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /** 
     * @test
     * @group semantic-search
     */
    public function it_can_search_across_different_daily_reports_semantically()
    {
        // Act
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/mcp/execute-tool', [
                'tool' => 'search-ledgers-tool',
                'arguments' => [
                    'q' => '今日の業務内容について',
                    'order_by' => 'semantic_score',
                    'limit' => 5
                ]
            ]);

        // Assert
        $response->assertOk();
        $response->assertJsonStructure(['ledgers', 'meta', 'total']);
        
        $titles = collect($response->json('ledgers'))->pluck('content.title');
        
        // 営業日報、開発日報、週報などが含まれていることを確認
        $this->assertTrue($titles->contains(fn($title) => str_contains($title, '営業日報')));
        $this->assertTrue($titles->contains(fn($title) => str_contains($title, '開発日報')));
    }

    /** 
     * @test
     * @group semantic-search
     */
    public function it_can_find_expense_reports_by_purpose()
    {
        // Act
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/mcp/execute-tool', [
                'tool' => 'search-ledgers-tool',
                'arguments' => [
                    'q' => '顧客との打ち合わせで使った費用',
                    'order_by' => 'semantic_score',
                    'limit' => 3
                ]
            ]);

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('total'));
        
        // 返された結果のcontentに「交通費」や「会食」が含まれることを確認
        $contents = collect($response->json('ledgers'))->pluck('content.purpose');
        $this->assertTrue(
            $contents->contains(fn($purpose) => str_contains($purpose, '交通費') || str_contains($purpose, '会食'))
        );
    }
    
    // ... 他のシナリオや異常系テスト ...
}
```

## 6. 成功基準

- 上記の全テストシナリオがパスすること。
- `SearchLedgersTool` を `order_by=semantic_score` で実行した際に、意図通りセマンティック検索が実行され、関連性の高い結果が返却されることを手動テストで確認できること。
- コードが `pint` でフォーマットされていること。
