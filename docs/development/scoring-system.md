# スコアリングシステム 開発者ガイド

**対象読者:** LedgerLeap開発者・保守担当者  
**最終更新:** 2025年10月13日

## 📖 関連ドキュメント

### 公式ドキュメント
- [MCP アーキテクチャと動作フロー](./MCP_Architecture_and_Flow.md) - スコアリング機能のMCP統合
- [機能仕様: スコアリングシステム](../features/scoring-system.md) - ユーザー向け機能説明

### 作業ファイル（計画・設計・実装記録）
- [スコアリング実装計画](../work/architecture/scoring-system/2025-10-08_search-result-scoring-and-sorting-plan.md) - 初期設計
- [ハイブリッドスコアリング性能調査](../work/architecture/scoring-system/2025-10-12_hybrid-scoring-performance-study.md) - パフォーマンス最適化
- [Phase 1.5 Step1-8 実装完了](../work/architecture/scoring-system/2025-10-12_phase1-5-step1-8-implementation-complete.md) - 実装記録
- [ドキュメント再編成完了](../work/architecture/scoring-system/2025-10-13_documentation-reorganization-complete.md) - ドキュメント整備
- [MCPスコアリング統合計画](../work/llm-integration/2025-10-13_MCP_Scoring_Integration_Plan.md) - MCP統合設計 🆕
- [MCPスコアリング統合実装完了](../work/llm-integration/2025-10-13_MCP_Sorting_Implementation_Complete.md) - MCP統合実装記録 🆕

---

## アーキテクチャ概要

スコアリングシステムは以下のコンポーネントで構成されています：

```
┌─────────────────────────────────────────────────┐
│           Artisan Command                        │
│     (scoring:calculate - スケジュール実行)        │
└─────────────┬───────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────┐
│         Service Layer                            │
│  ┌──────────────────────────────────────────┐  │
│  │  CompositeScoreCalculator                │  │
│  │  ├─ ActivityScoreService                 │  │
│  │  ├─ FreshnessScoreService                │  │
│  │  └─ ImportanceScoreService               │  │
│  └──────────────────────────────────────────┘  │
└─────────────┬───────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────┐
│         Data Layer                               │
│  ├─ Ledger Model (activity_score,               │
│  │                 composite_score)              │
│  ├─ ActivityLog (イベント履歴)                   │
│  └─ Config (ledgerleap.php)                     │
└─────────────────────────────────────────────────┘
```

---

## コアサービス

### 1. ActivityScoreService

**責務:** activity_log からイベントを集計し活動スコアを計算

**場所:** `app/Services/Scoring/ActivityScoreService.php`

**メソッド:**
```php
public function calculateForLedger(Ledger $ledger): float
```

**実装ポイント:**
- 直近7日間のイベント × 10点
- 直近30日間のイベント × 3点
- `activity_log` テーブルを直接集計（N+1クエリなし）

**設定:**
```php
// config/ledgerleap.php
'scoring' => [
    'activity' => [
        'windows' => [
            ['days' => 7, 'multiplier' => 10],
            ['days' => 30, 'multiplier' => 3],
        ],
    ],
],
```

### 2. FreshnessScoreService

**責務:** 更新日時からの経過時間で新鮮度スコアを計算

**場所:** `app/Services/Scoring/FreshnessScoreService.php`

**メソッド:**
```php
public function calculateForLedger(Ledger $ledger): float
```

**計算式:**
```php
$daysSinceUpdate = now()->diffInDays($ledger->updated_at);
$score = max(0, 100 - ($daysSinceUpdate * 2));
```

**特徴:**
- Pure Function（副作用なし）
- テスト容易性が高い
- 設定ファイル不要（ビジネスロジックに組み込み）

### 3. ImportanceScoreService

**責務:** ワークフローステータスから重要度スコアを計算

**場所:** `app/Services/Scoring/ImportanceScoreService.php`

**メソッド:**
```php
public function calculateForLedger(Ledger $ledger): float
```

**スコアマッピング:**
```php
private const STATUS_SCORES = [
    WorkflowStatus::PENDING->value => 100,    // 承認待ち
    WorkflowStatus::RETURNED->value => 80,    // 差し戻し
    WorkflowStatus::IN_REVIEW->value => 60,   // 検査中
    WorkflowStatus::DRAFT->value => 20,       // 下書き
    WorkflowStatus::APPROVED->value => 10,    // 承認済み
];
```

**Phase 2での拡張予定:**
- タグによる重要度判定
- コメント数の考慮
- 添付ファイル数の考慮

### 4. CompositeScoreCalculator

**責務:** 各スコアを統合し複合スコアを計算

**場所:** `app/Services/Scoring/CompositeScoreCalculator.php`

**メソッド:**
```php
public function calculate(Ledger $ledger): array
```

**返り値:**
```php
[
    'activity_score' => 30.0,
    'freshness_score' => 80.0,
    'importance_score' => 20.0,
    'composite_score' => 45.0,  // 加重平均
]
```

**重み付け:**
```php
// config/ledgerleap.php
'weights' => [
    'activity' => 0.40,    // 40%
    'freshness' => 0.30,   // 30%
    'importance' => 0.30,  // 30%
],
```

---

## Artisanコマンド

### CalculateScores

**場所:** `app/Console/Commands/CalculateScores.php`

**シグネチャ:**
```bash
php artisan scoring:calculate
```

**処理フロー:**
```php
1. 全テナントを取得
2. 各テナントに対して:
   a. テナントコンテキストを初期化
   b. 全Ledgerを取得
   c. 各Ledgerに対して:
      - ActivityScoreService で活動スコア計算
      - CompositeScoreCalculator で複合スコア計算
      - Ledger に保存
   d. 進捗バー表示
3. ログ出力
```

**依存性注入:**
```php
public function handle(
    ActivityScoreService $activityScoreService,
    CompositeScoreCalculator $compositeScoreCalculator
): int
```

**テスト:**
- `tests/Feature/Feature/Console/CalculateScoresCommandTest.php`
- マルチテナント対応を確認
- スコア計算の正確性を検証

---

## スケジューリング

### Kernel.php

**場所:** `app/Console/Kernel.php`

**実装:**
```php
protected function schedule(Schedule $schedule): void
{
    $frequency = config('ledgerleap.scoring.schedule_frequency', 'daily');
    
    $command = $schedule->command('scoring:calculate');
    
    match ($frequency) {
        'everyMinute' => $command->everyMinute(),
        'everyFiveMinutes' => $command->everyFiveMinutes(),
        'everyTenMinutes' => $command->everyTenMinutes(),
        'hourly' => $command->hourly(),
        'daily' => $command->daily(),
        'weekly' => $command->weekly(),
        default => $command->daily(),
    };
}
```

**頻度の選択:**
- 環境変数 `SCORING_SCHEDULE_FREQUENCY` で制御
- match 式で6種類の頻度をサポート
- デフォルトは `daily`

**スケジュール確認:**
```bash
./vendor/bin/sail artisan schedule:list
```

**scheduler コンテナ:**
- `docker-compose.yml` で定義済み
- `php artisan schedule:work` を常時実行
- `restart: always` で自動復旧

---

## データベース設計

### マイグレーション

**ファイル:** `database/migrations/*_add_scoring_columns_to_ledgers_table.php`

**追加カラム:**
```php
$table->decimal('activity_score', 5, 2)
    ->default(0)
    ->comment('活動スコア (0-100)');
    
$table->decimal('composite_score', 5, 2)
    ->default(0)
    ->comment('複合スコア (0-100)');
    
$table->index('composite_score', 'idx_ledgers_composite_score');
```

**インデックス戦略:**
- `composite_score` にインデックス付与
- ORDER BY クエリの高速化
- NULL値は最後にソート（`ORDER BY composite_score = 0, composite_score DESC`）

---

## Livewireコンポーネント統合

### RecordsTable

**場所:** `app/Livewire/Ledger/RecordsTable.php`

**デフォルトソート:**
```php
public string $orderBy = 'composite_score';
public string $orderDirection = 'desc';
```

**ソート処理:**
```php
// NULLを最後にするMySQLハック
$query->orderByRaw('composite_score = 0')
      ->orderBy('composite_score', 'desc');
```

**スコアラベル管理:**
```php
public string $orderByLabel = '';

private function getStandardSortLabel(string $columnName): string
{
    return match ($columnName) {
        'composite_score' => __('ledger.composite_score'),
        'created_at' => __('ledger.created_at'),
        'updated_at' => __('ledger.updated_at'),
        default => '',
    };
}
```

---

## テスト戦略

### ユニットテスト

**各サービスのテスト:**
```php
// tests/Unit/Services/Scoring/ActivityScoreServiceTest.php
public function test_calculates_activity_score_correctly()
{
    $ledger = Ledger::factory()->create();
    
    // 直近7日間に3イベント
    activity()->performedOn($ledger)->log('created');
    activity()->performedOn($ledger)->log('updated');
    activity()->performedOn($ledger)->log('viewed');
    
    $service = new ActivityScoreService();
    $score = $service->calculateForLedger($ledger);
    
    $this->assertEquals(30, $score); // 3 × 10
}
```

### フィーチャーテスト

**コマンドテスト:**
```php
// tests/Feature/Feature/Console/CalculateScoresCommandTest.php
public function it_calculates_scores_for_ledgers_in_a_tenant()
{
    $tenant = Tenant::create(['id' => 'test-tenant']);
    
    $tenant->run(function () {
        $ledger = Ledger::factory()->create([
            'activity_score' => 0,
            'composite_score' => 0,
        ]);
        
        activity()->performedOn($ledger)->log('created');
    });
    
    $this->artisan('scoring:calculate')
        ->assertExitCode(0);
    
    $tenant->run(function () use ($ledger) {
        $updated = $ledger->fresh();
        $this->assertGreaterThan(0, $updated->composite_score);
    });
}
```

**スケジュールテスト:**
```php
// tests/Feature/Feature/Console/CalculateScoresScheduleTest.php
public function it_schedules_scoring_command_with_default_daily_frequency()
{
    config(['ledgerleap.scoring.schedule_frequency' => 'daily']);
    
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());
    
    $scoringEvent = $events->first(function (Event $event) {
        return str_contains($event->command ?? '', 'scoring:calculate');
    });
    
    $this->assertNotNull($scoringEvent);
}
```

### テスト実行

```bash
# 全スコアリングテスト
./vendor/bin/sail test --filter=Score

# 特定のテストクラス
./vendor/bin/sail test tests/Feature/Feature/Console/CalculateScoresCommandTest.php

# カバレッジレポート
./vendor/bin/sail test --coverage
```

---

## 設定管理

### config/ledgerleap.php

```php
return [
    'scoring' => [
        // 活動スコア設定
        'activity' => [
            'windows' => [
                ['days' => 7, 'multiplier' => 10],
                ['days' => 30, 'multiplier' => 3],
            ],
        ],
        
        // 複合スコアの重み付け（合計 1.0）
        'weights' => [
            'activity' => 0.40,
            'freshness' => 0.30,
            'importance' => 0.30,
            'relevance' => 0.00,   // Phase 3で有効化
            'popularity' => 0.00,  // Phase 5で有効化
        ],
        
        // バッチ処理設定
        'batch' => [
            'chunk_size' => 100,
            'schedule' => 'daily',
        ],
        
        // スケジュール頻度
        'schedule_frequency' => env('SCORING_SCHEDULE_FREQUENCY', 'daily'),
    ],
];
```

### 環境変数

```bash
# .env.development
SCORING_SCHEDULE_FREQUENCY=everyFiveMinutes

# .env.production
SCORING_SCHEDULE_FREQUENCY=daily

# .env.testing（テスト用は設定不要、setUp()で上書き）
```

---

## パフォーマンス考慮事項

### N+1問題の回避

**ActivityScoreService:**
```php
// ❌ 悪い例: N+1クエリ
foreach ($ledgers as $ledger) {
    $events = $ledger->activities()->count();
}

// ✅ 良い例: 単一クエリで集計
DB::table('activity_log')
    ->where('subject_id', $ledger->id)
    ->where('subject_type', Ledger::class)
    ->where('created_at', '>=', now()->subDays(7))
    ->count();
```

### チャンク処理

```php
// 大量データの場合
Ledger::chunk(100, function ($ledgers) {
    foreach ($ledgers as $ledger) {
        // スコア計算
    }
});
```

### インデックス活用

```sql
-- 複合スコアでソート（インデックス利用）
EXPLAIN SELECT * FROM ledgers 
ORDER BY composite_score = 0, composite_score DESC 
LIMIT 10;

-- インデックススキャンで高速化
+----+-------------+---------+-------+-------------------------------+
| id | select_type | table   | type  | key                           |
+----+-------------+---------+-------+-------------------------------+
|  1 | SIMPLE      | ledgers | index | idx_ledgers_composite_score   |
+----+-------------+---------+-------+-------------------------------+
```

---

## デバッグ

### ログ確認

**バッチ処理ログ:**
```bash
# scheduler コンテナのログ
./vendor/bin/sail logs scheduler -f

# Laravel ログ
tail -f storage/logs/laravel.log | grep "scoring"
```

**SQL クエリログ:**
```php
// 一時的にクエリログを有効化
DB::enableQueryLog();
$service->calculateForLedger($ledger);
dd(DB::getQueryLog());
```

### 手動実行

```bash
# スコア再計算
./vendor/bin/sail artisan scoring:calculate

# 特定のテナントのみ（Tinkerで）
./vendor/bin/sail artisan tinker
> $tenant = Tenant::find('tenant-id');
> $tenant->run(function() {
>     Artisan::call('scoring:calculate');
> });
```

---

## 今後の拡張方法

### 新しいスコア指標の追加

1. **サービスクラス作成:**
```php
// app/Services/Scoring/PopularityScoreService.php
class PopularityScoreService
{
    public function calculateForLedger(Ledger $ledger): float
    {
        // ユニーク閲覧者数をカウント
        $uniqueViewers = DB::table('activity_log')
            ->where('subject_id', $ledger->id)
            ->where('event', 'viewed')
            ->distinct('causer_id')
            ->count();
            
        return min(100, $uniqueViewers * 5);
    }
}
```

2. **CompositeScoreCalculator に統合:**
```php
public function calculate(Ledger $ledger): array
{
    $scores = [
        'activity_score' => $this->activityScoreService->calculateForLedger($ledger),
        'freshness_score' => $this->freshnessScoreService->calculateForLedger($ledger),
        'importance_score' => $this->importanceScoreService->calculateForLedger($ledger),
        'popularity_score' => $this->popularityScoreService->calculateForLedger($ledger), // 追加
    ];
    
    // 重み付け計算に追加
}
```

3. **設定ファイル更新:**
```php
'weights' => [
    'activity' => 0.30,
    'freshness' => 0.25,
    'importance' => 0.25,
    'popularity' => 0.20,  // 追加
],
```

4. **テスト追加:**
```php
// tests/Unit/Services/Scoring/PopularityScoreServiceTest.php
```

---

## トラブルシューティング

### よくある問題

**問題1: スコアが更新されない**

原因: scheduler コンテナが起動していない

解決策:
```bash
./vendor/bin/sail ps
./vendor/bin/sail up -d scheduler
```

**問題2: テストでスコアが 0 になる**

原因: テスト内でconfigが設定されていない

解決策:
```php
protected function setUp(): void
{
    parent::setUp();
    
    config([
        'ledgerleap.scoring.activity.windows' => [
            ['days' => 7, 'multiplier' => 10],
            ['days' => 30, 'multiplier' => 3],
        ],
        'ledgerleap.scoring.weights' => [
            'activity' => 0.40,
            'freshness' => 0.30,
            'importance' => 0.30,
        ],
    ]);
}
```

**問題3: マイグレーション失敗**

原因: カラムが既に存在する

解決策:
```bash
# 開発環境のみ
./vendor/bin/sail artisan migrate:fresh --seed
```

---

## 関連ドキュメント

- [機能ドキュメント](../features/scoring-system.md) - ユーザー向け説明
- [データベーススキーマ](../database/schema.md) - テーブル定義
- [Activity Log](../function/Activity.md) - イベントログ仕様
- [作業ドキュメント](/docs/work/architecture/scoring-system/) - 実装プロセスの記録

---

**作成日:** 2025年10月13日  
**管理:** LedgerLeap開発チーム
