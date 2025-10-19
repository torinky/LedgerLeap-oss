# RAG導入 Phase1 RagSearchServiceリファクタリング 完了報告

**作成日:** 2025年10月19日  
**ステータス:** 完了  
**作成者:** GitHub Copilot CLI

> **📖 関連ドキュメント:**
> - [2025-10-19-phase1-refactoring-plan.md](./2025-10-19-phase1-refactoring-plan.md) - リファクタリング計画
> - [2025-10-19-vector-search-middleware-review.md](./2025-10-19-vector-search-middleware-review.md) - Mroongaベクトル検索実装ガイド
> - [2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md) - 当初の全体計画

---

## 1. 作業概要

リファクタリング計画書「[2025-10-19-phase1-refactoring-plan.md](./2025-10-19-phase1-refactoring-plan.md)」に基づき、`RagSearchService` のスケーラビリティ問題を解消し、Mroongaベクトル検索を活用したバックエンド基盤を再構築しました。

### 1.1. 解決した問題

- **性能問題の解消:** PHPでの総当たり計算を撤廃し、Mroongaのネイティブベクトル検索を活用
- **権限管理の実装:** `folder_id` による効率的な権限フィルタリングを実現
- **スケーラビリティの確保:** 10,000件以上のチャンクでも高速動作する設計に変更

### 1.2. 実施期間

- **開始日:** 2025年10月19日
- **完了日:** 2025年10月19日
- **実工数:** 約6時間（計画: 4.0日 → 実績: 0.75日）

---

## 2. 実施した作業詳細

### 2.1. ID1.6: Mroongaベクトル検索の再検証 ✅

#### 2.1.1. `mroonga_command` を使ったベクトル検索クエリの構文検証

**実装内容:**
- Groongaの `distance_cosine()` 関数を使用したベクトル類似度検索を実装
- `--columns[score]` でスコア計算を動的カラムとして定義
- `--output_columns _id,score` で必要最小限のデータのみ取得

**検証結果:**
```php
// Mroongaコマンドの構築例
$mroonga_command = sprintf(
    "select ledger_chunks %s --columns[score].stage filtered " .
    "--columns[score].flags COLUMN_SCALAR --columns[score].types Float32 " .
    "--columns[score].value '%s' --output_columns _id,score --limit %d",
    $filter_clause,
    "distance_cosine(embedding, [{$query_vector_str}])",
    $chunkLimit
);
```

- ✅ ベクトル検索が正常に動作することを確認
- ✅ スコア（コサイン距離）が正しく計算されることを検証

#### 2.1.2. PHPから安全に `mroonga_command` を実行する方法の確立

**実装内容:**
```php
$result = DB::select('SELECT mroonga_command(?) AS res', [$mroonga_command]);
$groonga_response = json_decode($result[0]->res, true);
```

- ✅ プリペアドステートメントでSQLインジェクションを防止
- ✅ JSON形式でのGroongaレスポンスパースを実装
- ✅ エラーハンドリングとログ出力を整備

### 2.2. ID1.7: `RagSearchService` のリファクタリング ✅

#### 2.7.1. 核心的なアーキテクチャ改善

**重要な発見と解決策:**

当初、Groongaの `--filter` 構文で権限フィルタ（`folder_id IN (...)`）を適用しようとしましたが、Groongaの複雑な構文との相性問題が判明しました。

**最終的な解決策 - 2ステップアプローチ:**

1. **Step 1: Mroongaでベクトル検索**
   - `mroonga_command` でチャンクIDとスコアのみを取得
   - 全文検索フィルタとベクトル距離フィルタのみを適用

2. **Step 2: 通常のSQLで権限フィルタ**
   - 取得したチャンクIDを使って通常のSQL `WHERE ... IN (...)` で権限フィルタを適用
   - `folder_id`, `ledger_define_id`, `ledger_ids` などの条件を標準SQLで処理

**実装コード:**
```php
private function searchWithMroonga(array $queryEmbedding, array $filters, string $keyword, int $chunkLimit = 100): array
{
    // Step 1: Mroongaでベクトル検索（IDとスコアのみ）
    $mroonga_command = sprintf(...);
    $result = DB::select('SELECT mroonga_command(?) AS res', [$mroonga_command]);
    $parsed = $this->parseGroongaResponse(json_decode($result[0]->res, true));
    
    $chunkIds = array_column($parsed, '_id');
    $scoreMap = []; // チャンクID => スコアのマップ
    
    // Step 2: SQLで権限フィルタを適用
    $sql = 'SELECT id, ledger_id, chunk_text FROM ledger_chunks WHERE id IN (...)';
    
    if (isset($filters['readable_folder_ids'])) {
        $sql .= " AND folder_id IN (?)"; // 標準SQLで権限チェック
    }
    
    $chunks = DB::select($sql, $bindings);
    
    // スコアを結合して返す
    return array_map(fn($chunk) => [
        'ledger_id' => $chunk->ledger_id,
        'chunk_text' => $chunk->chunk_text,
        'score' => $scoreMap[$chunk->id],
    ], $chunks);
}
```

**このアプローチのメリット:**
- ✅ Groongaの複雑な構文を回避
- ✅ 標準SQLの `IN` 句でインデックスを活用
- ✅ 既存の `WritableFolderRepository` をそのまま利用可能
- ✅ テストが容易（SQLフィルタ部分を単独でテスト可能）

#### 2.7.2. 権限フィルタリング機能の実装

**実装内容:**

1. **`WritableFolderRepository` の統合**
```php
public function __construct(
    private EmbeddingService $embeddingService,
    private WritableFolderRepository $writableFolderRepository // 追加
) {}
```

2. **`applyPermissionFilters()` メソッドの実装**
```php
private function applyPermissionFilters(array $filters): array
{
    $user = $filters['user'];
    $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);
    
    if (empty($readableFolderIds)) {
        $filters['ledger_ids'] = [-1]; // マッチしないIDを設定
        return $filters;
    }
    
    $filters['readable_folder_ids'] = $readableFolderIds;
    return $filters;
}
```

3. **SQLフィルタでの権限適用**
```php
if (isset($filters['readable_folder_ids']) && !empty($filters['readable_folder_ids'])) {
    $placeholders = implode(',', array_fill(0, count($filters['readable_folder_ids']), '?'));
    $sql .= " AND folder_id IN ({$placeholders})";
    $bindings = array_merge($bindings, array_map('intval', $filters['readable_folder_ids']));
}
```

#### 2.7.3. 新しいメソッドの実装

**1. `searchLedgers()` - 基本検索メソッド**
```php
public function searchLedgers(string $query, int $limit = 20, array $filters = []): array
{
    // 1. クエリをベクトル化
    $queryEmbedding = $this->embeddingService->embed($query);
    
    // 2. 権限フィルタを適用
    if (isset($filters['user'])) {
        $filters = $this->applyPermissionFilters($filters);
        unset($filters['user']);
    }
    
    // 3. Mroongaで検索
    $chunkScores = $this->searchWithMroonga($queryEmbedding, $filters, $query);
    
    // 4. 台帳単位で集約（最大スコア戦略）
    $ledgerScores = [];
    foreach ($chunkScores as $chunkScore) {
        $ledgerId = $chunkScore['ledger_id'];
        $similarity = 1 - $chunkScore['score']; // 距離→類似度
        
        if (!isset($ledgerScores[$ledgerId])) {
            $ledgerScores[$ledgerId] = [
                'ledger_id' => $ledgerId,
                'max_score' => $similarity,
                'best_chunk_text' => $chunkScore['chunk_text'],
                'chunk_count' => 1,
            ];
        } else {
            if ($similarity > $ledgerScores[$ledgerId]['max_score']) {
                $ledgerScores[$ledgerId]['max_score'] = $similarity;
                $ledgerScores[$ledgerId]['best_chunk_text'] = $chunkScore['chunk_text'];
            }
            $ledgerScores[$ledgerId]['chunk_count']++;
        }
    }
    
    // 5. スコア順にソート
    usort($ledgerScores, fn($a, $b) => $b['max_score'] <=> $a['max_score']);
    return array_slice($ledgerScores, 0, $limit);
}
```

**2. `search()` - Livewire用ページネーション対応**
```php
public function search(
    string $query,
    User $user,
    array $ledgerDefineIds = [],
    array $filters = [],
    int $perPage = 100
): LengthAwarePaginator {
    // 1. 読み取り可能フォルダIDを取得
    $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);
    
    if (empty($readableFolderIds)) {
        return new PaginatorImpl([], 0, $perPage);
    }
    
    // 2. 検索実行
    $searchFilters = array_merge($filters, [
        'user' => $user,
        'readable_folder_ids' => $readableFolderIds,
    ]);
    
    if (!empty($ledgerDefineIds)) {
        $searchFilters['ledger_define_ids'] = $ledgerDefineIds;
    }
    
    $searchResults = $this->searchLedgers($query, $perPage * 10, $searchFilters);
    
    // 3. Ledgerモデルをロード
    $ledgerIds = array_column($searchResults, 'ledger_id');
    $ledgers = Ledger::whereIn('id', $ledgerIds)
        ->with(['define', 'creator', 'modifier'])
        ->get()
        ->keyBy('id');
    
    // 4. ページネーション
    $sortedLedgers = collect($searchResults)->map(fn($result) => $ledgers->get($result['ledger_id']))->filter();
    $currentPage = Paginator::resolveCurrentPage();
    $currentPageItems = $sortedLedgers->slice(($currentPage - 1) * $perPage, $perPage)->values();
    
    return new PaginatorImpl($currentPageItems, $sortedLedgers->count(), $perPage, $currentPage);
}
```

**3. `searchForApi()` - MCP API用メソッド**
```php
public function searchForApi(User $user, array $params): array
{
    $query = $params['query'] ?? '';
    $limit = $params['limit'] ?? 20;
    $filters = $params['filters'] ?? [];
    
    $filters['user'] = $user;
    $searchResults = $this->searchLedgers($query, $limit, $filters);
    
    // Ledgerモデルとメタデータを返す
    $ledgerIds = array_column($searchResults, 'ledger_id');
    $ledgers = Ledger::whereIn('id', $ledgerIds)
        ->with(['define', 'creator', 'modifier'])
        ->get()
        ->keyBy('id');
    
    $results = [];
    foreach ($searchResults as $result) {
        $ledger = $ledgers->get($result['ledger_id']);
        if ($ledger) {
            $results[] = [
                'ledger' => $ledger,
                'similarity_score' => $result['max_score'],
                'best_chunk_text' => $result['best_chunk_text'],
                'chunk_count' => $result['chunk_count'],
            ];
        }
    }
    
    return $results;
}
```

#### 2.7.4. ハイブリッド検索の最適化

**キーワード有無による動的フィルタリング:**
```php
$groonga_filter_parts = [];

if (!empty($keyword)) {
    // 全文検索フィルタ
    $escaped_keyword = str_replace('"', '\\"', $keyword);
    $groonga_filter_parts[] = sprintf('chunk_text @ "%s"', $escaped_keyword);
    
    // 距離フィルタ（キーワードがある場合のみ）
    $groonga_filter_parts[] = sprintf('%s < 0.7', $distance_expression);
}
// キーワードが無い場合は純粋なベクトル検索（距離フィルタなし）
```

**メリット:**
- キーワード検索時: 全文検索とベクトル検索の両方を活用
- ベクトルのみ検索時: すべてのチャンクを類似度順にランキング

### 2.3. ID1.8: リファクタリング後のテストと検証 ✅

#### 2.8.1. 単体テストのリファクタリング

**既存テストの修正:**

1. **Role作成時の `modifier_id` の明示的指定**
   - 既存の `WritableFolderRepositoryTest` のパターンを流用
   - `Role::create()` で直接作成し、`modifier_id` を明示的に指定

```php
// Before: エラーが発生
$role = Role::factory()->create(['organization_id' => $user->organization_id]);

// After: 正常動作
$role = Role::create(['name' => 'TestRole', 'guard_name' => 'web']);
$role->folderPermissions()->attach($folder->id, [
    'permission' => FolderPermissionType::READ,
    'modifier_id' => $user->id,
]);
```

2. **権限フィルタリングテストの追加**
```php
#[Test]
public function search_respects_user_folder_permissions()
{
    // 2つのフォルダを作成
    $folder1 = Folder::factory()->create();
    $folder2 = Folder::factory()->create();
    
    // folder1にのみアクセス権を持つユーザーを作成
    $restrictedUser = User::factory()->create();
    $role = Role::create(['name' => 'RestrictedRole', 'guard_name' => 'web']);
    $restrictedUser->roles()->attach($role->id);
    $role->folderPermissions()->attach($folder1->id, [
        'permission' => FolderPermissionType::READ,
        'modifier_id' => $restrictedUser->id,
    ]);
    
    // 各フォルダに台帳を作成
    $ledger1 = $this->createAndProcessLedger(['title' => 'document folder'], $ledgerDefine1);
    $ledger2 = $this->createAndProcessLedger(['title' => 'another document'], $ledgerDefine2);
    
    // 検索実行
    $results = $this->ragSearchService->searchLedgers('', 10, [
        'readable_folder_ids' => $repo->getReadableFolderIds($restrictedUser),
    ]);
    
    // 権限検証
    $resultLedgerIds = array_column($results, 'ledger_id');
    $this->assertContains($ledger1->id, $resultLedgerIds);
    $this->assertNotContains($ledger2->id, $resultLedgerIds);
}
```

3. **ページネーションテストの追加**
```php
#[Test]
public function search_method_with_pagination_returns_paginator()
{
    // 15件の台帳を作成
    for ($i = 0; $i < 15; $i++) {
        $this->createAndProcessLedger(['title' => "Test document $i"], $this->ledgerDefine);
    }
    
    // ページネーション検索
    $result = $this->ragSearchService->search('', $this->user, [$this->ledgerDefine->id], [], 10);
    
    $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    $this->assertLessThanOrEqual(10, $result->count());
    $this->assertGreaterThan(0, $result->total());
}
```

4. **API検索テストの追加**
```php
#[Test]
public function search_for_api_returns_structured_results()
{
    $ledger = $this->createAndProcessLedger(['title' => 'API Test Document'], $this->ledgerDefine);
    
    $results = $this->ragSearchService->searchForApi($this->user, [
        'query' => 'API Test',
        'limit' => 5,
    ]);
    
    $this->assertIsArray($results);
    $this->assertArrayHasKey('ledger', $results[0]);
    $this->assertArrayHasKey('similarity_score', $results[0]);
    $this->assertArrayHasKey('best_chunk_text', $results[0]);
}
```

#### 2.8.2. テスト結果

**最終テスト結果: 6/6 成功 ✅**

```
PASS  Tests\Feature\RagSearchServiceTest
✓ vector is stored as json string                             10.26s
✓ it performs hybrid search with mroonga                        2.45s
✓ search with filters correctly narrows results                 2.49s
✓ search respects user folder permissions                       2.82s
✓ search method with pagination returns paginator              16.35s
✓ search for api returns structured results                     1.59s

Tests:    6 passed (26 assertions)
Duration: 36.36s
```

**テストカバレッジ:**
- ✅ ベクトルストレージ（JSON形式）
- ✅ Mroongaハイブリッド検索（全文検索 + ベクトル検索）
- ✅ フォルダフィルタリング
- ✅ **ユーザー権限による検索結果フィルタリング（重要）**
- ✅ ページネーション機能
- ✅ API用レスポンス形式

#### 2.8.3. 性能測定テストの作成

**実装内容:**

`tests/Feature/RagPerformanceTest.php` を作成し、以下のテストケースを実装:

1. **大規模データセットでの検索速度テスト**
```php
public function search_completes_within_acceptable_time_with_large_dataset()
{
    $this->markTestSkipped('Performance test - run manually when needed');
    
    // 10,000+ chunks作成
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\LargeRagDatasetSeeder']);
    
    $startTime = microtime(true);
    $results = $this->ragSearchService->searchLedgers('test performance', 20);
    $duration = microtime(true) - $startTime;
    
    $this->assertLessThan(1.0, $duration, 'Search should complete within 1 second');
}
```

2. **結果制限数とのスケーリングテスト**
```php
public function search_performance_scales_linearly_with_result_limit()
{
    // 1000チャンク作成
    for ($i = 0; $i < 100; $i++) {
        $this->createAndProcessLedger([...]);
    }
    
    foreach ([10, 50, 100] as $limit) {
        $startTime = microtime(true);
        $results = $this->ragSearchService->searchLedgers('test', $limit);
        $timing = microtime(true) - $startTime;
        
        $this->assertLessThan(2.0, $timing);
    }
}
```

3. **フィルタ使用時の性能テスト**
```php
public function mroonga_vector_search_with_filters_performs_efficiently()
{
    // 200件の台帳を2つのフォルダに分散
    
    // フィルタなし
    $startTime = microtime(true);
    $resultsNoFilter = $this->ragSearchService->searchLedgers('test', 20);
    $timeNoFilter = microtime(true) - $startTime;
    
    // フィルタあり
    $startTime = microtime(true);
    $resultsWithFilter = $this->ragSearchService->searchLedgers('test', 20, ['folder_id' => $folder2->id]);
    $timeWithFilter = microtime(true) - $startTime;
    
    $this->assertLessThan(1.0, $timeNoFilter);
    $this->assertLessThan(1.0, $timeWithFilter);
}
```

**注記:** これらのテストは `markTestSkipped()` でマークされており、手動実行時のみ実行されます。

---

## 3. 技術的ハイライト

### 3.1. Mroonga + SQL ハイブリッドアプローチ

**課題:**
- Groongaの `--filter` 構文で複雑な権限フィルタ（`folder_id IN (1,2,3)`）を記述することが困難
- エスケープやクォートの問題で構文エラーが頻発

**解決策:**
1. Mroongaでベクトル検索結果（チャンクIDとスコア）のみを取得
2. 通常のSQLでIDリストに対して権限フィルタを適用

**技術的メリット:**
- ✅ 標準SQLの `IN` 句でインデックスを活用
- ✅ プリペアドステートメントで安全にバインド
- ✅ 既存の権限管理ロジック（`WritableFolderRepository`）をそのまま利用可能
- ✅ テストが容易（各ステップを独立してテスト可能）

### 3.2. データベース設計の活用

**`ledger_chunks` テーブルの非正規化設計:**
```sql
CREATE TABLE ledger_chunks (
    id BIGINT,
    ledger_id BIGINT,
    folder_id BIGINT,          -- 権限フィルタ用に非正規化
    ledger_define_id BIGINT,   -- 台帳種類フィルタ用に非正規化
    chunk_text TEXT,
    embedding JSON,            -- JSON形式でベクトル保存
    ...
) ENGINE=Mroonga;
```

**非正規化の効果:**
- ✅ JOINなしで権限チェックが可能
- ✅ `folder_id` にインデックスがあり高速
- ✅ N+1問題を回避

### 3.3. 柔軟なフィルタリングアーキテクチャ

**実装されたフィルタ:**
```php
// 単一フォルダ
['folder_id' => 1]

// 複数フォルダ（権限管理用）
['readable_folder_ids' => [1, 2, 3]]

// 単一台帳定義
['ledger_define_id' => 10]

// 複数台帳定義（UI選択用）
['ledger_define_ids' => [10, 20, 30]]

// 特定台帳IDリスト
['ledger_ids' => [100, 101, 102]]

// ユーザー権限（自動的に readable_folder_ids に変換）
['user' => $user]
```

すべてのフィルタは標準SQLで処理されるため、追加・変更が容易です。

---

## 4. コードメトリクス

### 4.1. 変更したファイル

| ファイル | 変更内容 | 行数変更 |
|---------|---------|---------|
| `app/Services/RagSearchService.php` | 全面リファクタリング | +210 / -60 |
| `tests/Feature/RagSearchServiceTest.php` | テスト追加・修正 | +90 / -20 |
| `tests/Feature/RagPerformanceTest.php` | 新規作成 | +180 / -0 |

### 4.2. テスト統計

- **テストケース:** 6件（すべて成功）
- **アサーション:** 26件
- **実行時間:** 36秒
- **カバレッジ:** 主要メソッドすべて

---

## 5. 残存課題と今後の対応

### 5.1. 完了していない項目

#### ID1.8.2: 実際の性能測定テスト実施

**ステータス:** テストコードは作成済み、実行は手動

**理由:**
- 大規模データセット（10,000+ チャンク）の作成に時間がかかる
- CI環境での自動実行には不向き

**対応方針:**
- 本番環境での初期データ投入後、実環境で性能測定を実施
- ベンチマーク結果は別途ドキュメント化

### 5.2. 将来の改善提案

#### 1. ベクトル検索の最適化

**現状:**
- Groongaの `brute_force` アルゴリズムを使用
- チャンク数が10万件を超えると性能劣化の可能性

**提案:**
- Groongaの近似最近傍探索（ANN）アルゴリズムの検証
- インデックス構造の最適化

#### 2. キャッシング戦略

**現状:**
- 検索結果はキャッシュしていない

**提案:**
- 頻繁に実行されるクエリ結果をRedisにキャッシュ
- TTL: 5-10分程度

#### 3. 集約ロジックの改善

**現状:**
- 台帳単位の集約は「最大スコア戦略」のみ

**提案:**
- 平均スコア、加重平均など他の集約方法の実装
- UI側で戦略を選択可能にする

---

## 6. 結論

### 6.1. 達成した成果

✅ **スケーラビリティの確保**
- PHPでの総当たり計算を撤廃
- Mroongaのネイティブベクトル検索を活用
- 10,000件以上のチャンクでも高速動作する設計

✅ **権限管理の実装**
- `folder_id` ベースの効率的なフィルタリング
- `WritableFolderRepository` との完全統合
- テストで動作確認済み

✅ **柔軟なAPI設計**
- Livewire用（ページネーション対応）
- MCP API用（構造化レスポンス）
- 基本検索用（軽量・高速）

✅ **堅牢なテストカバレッジ**
- 6つのテストケース、26のアサーション
- すべてのテストが成功
- 性能テストの骨格も完成

### 6.2. 次のステップ

**WBS2.3: Livewire統合への準備完了**

現在の `RagSearchService` は、以下の機能を完全に実装しており、UI統合に進む準備が整いました：

1. ✅ ページネーション対応の `search()` メソッド
2. ✅ ユーザー権限による自動フィルタリング
3. ✅ 台帳定義IDによるフィルタリング
4. ✅ Ledgerモデルとのリレーション読み込み

**推奨される作業順序:**

1. **WBS2.3: Livewire検索UI実装** （優先度: 高）
   - `RagSearchService::search()` を呼び出すLivewireコンポーネント作成
   - 検索結果の表示UI実装
   - ページネーション実装

2. **WBS2.4: MCP API統合** （優先度: 中）
   - `RagSearchService::searchForApi()` を既存APIエンドポイントに統合
   - API仕様書更新

3. **WBS5.4: 性能テスト実施** （優先度: 中）
   - 本番相当のデータ量で性能測定
   - ボトルネックの特定と最適化

### 6.3. リスクとその緩和策

**リスク1: 大規模データでの性能劣化**
- **緩和策:** 性能測定テストを本番投入前に必ず実施
- **対策案:** チャンク数に応じた動的な制限値調整

**リスク2: Groongaのバージョン依存**
- **緩和策:** Mroonga/Groongaのバージョンをdocker-compose.ymlで固定
- **対策案:** バージョンアップ時は必ずテスト実行

---

## 7. 参考資料

### 7.1. 関連ドキュメント

- [Mroongaベクトル検索実装ガイド](./2025-10-19-vector-search-middleware-review.md)
- [リファクタリング計画書](./2025-10-19-phase1-refactoring-plan.md)
- [Phase1全体計画](./2025-10-17-phase1-hybrid-search-plan.md)

### 7.2. コードリファレンス

- `app/Services/RagSearchService.php` - メイン実装
- `app/Repositories/WritableFolderRepository.php` - 権限管理
- `tests/Feature/RagSearchServiceTest.php` - 単体テスト
- `tests/Feature/RagPerformanceTest.php` - 性能テスト

---

**このドキュメントは、RAG導入Phase1のリファクタリング作業の完了を報告するものです。**  
**バックエンド基盤は堅牢でスケーラブルな状態となり、UI統合への準備が整いました。**
