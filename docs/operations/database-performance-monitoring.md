# データベース性能監視ガイド

**作成日:** 2025年10月11日  
**対象:** LedgerLeap - データベース性能監視と最適化  
**関連ドキュメント:** `2025-10-09_physical-db-separation-architecture-study.md`

## 1. 概要

このドキュメントは、案A（現行アーキテクチャ継続+最適化）を採用した LedgerLeap システムにおける、
データベース性能の継続的な監視手順とアラート基準を定義します。

## 2. 監視すべき主要メトリクス

### 2.1. Buffer Pool 関連

```sql
-- Buffer Pool 使用率とヒット率
SELECT 
    ROUND((@@innodb_buffer_pool_size / 1024 / 1024 / 1024), 2) AS pool_size_gb,
    ROUND((
        (SELECT SUM(data_length + index_length) 
         FROM information_schema.TABLES 
         WHERE engine = 'InnoDB') / 1024 / 1024 / 1024
    ), 2) AS total_data_gb,
    ROUND((
        SELECT VARIABLE_VALUE 
        FROM performance_schema.global_status 
        WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'
    ) / (
        SELECT VARIABLE_VALUE 
        FROM performance_schema.global_status 
        WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'
    ) * 100, 2) AS hit_rate_percent;

-- 詳細なBuffer Pool統計
SELECT 
    pool_id,
    ROUND(pool_size * @@innodb_page_size / 1024 / 1024, 2) AS pool_mb,
    ROUND(free_buffers * @@innodb_page_size / 1024 / 1024, 2) AS free_mb,
    ROUND(database_pages * @@innodb_page_size / 1024 / 1024, 2) AS used_mb
FROM information_schema.innodb_buffer_pool_stats;
```

**アラート基準:**
- Buffer Pool ヒット率 < 95%: 警告
- Buffer Pool ヒット率 < 90%: クリティカル → サイズ増加を検討

### 2.2. クエリ性能

```sql
-- 遅いクエリの特定
SELECT 
    DIGEST_TEXT,
    COUNT_STAR AS exec_count,
    ROUND(AVG_TIMER_WAIT / 1000000000000, 3) AS avg_seconds,
    ROUND(MAX_TIMER_WAIT / 1000000000000, 3) AS max_seconds,
    ROUND(SUM_TIMER_WAIT / 1000000000000, 3) AS total_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'ledgerleap'
ORDER BY avg_seconds DESC
LIMIT 20;

-- テナント別クエリ実行時間（要：アプリケーション側でログ記録）
-- Laravelのクエリログやテレメトリーシステムで実装
```

**アラート基準:**
- 特定クエリの95%ile > 500ms: 警告
- 特定クエリの95%ile > 1000ms: クリティカル → クエリ最適化必須
- 特定テナントのクエリ > 1秒が継続: 調査必要

### 2.3. パーティション効率

```sql
-- パーティション別のデータサイズ
SELECT 
    TABLE_NAME,
    PARTITION_NAME,
    TABLE_ROWS,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_mb
FROM information_schema.PARTITIONS
WHERE TABLE_SCHEMA = 'ledgerleap'
  AND PARTITION_NAME IS NOT NULL
ORDER BY TABLE_NAME, PARTITION_NAME;

-- パーティションプルーニングの確認
EXPLAIN PARTITIONS
SELECT * FROM ledgers WHERE tenant_id = 'test-tenant' LIMIT 10;
-- 結果の "partitions" カラムで使用されたパーティション数を確認
-- 1つのパーティションのみが表示されていれば正常
```

**アラート基準:**
- 特定パーティションのサイズが他の10倍以上: データ偏りの調査
- EXPLAINで複数パーティションがスキャンされている: クエリ最適化必要

### 2.4. テナント別データ量

```sql
-- テナント別のレコード数とサイズ推定
SELECT 
    tenant_id,
    COUNT(*) AS record_count,
    ROUND(
        COUNT(*) * (
            SELECT AVG(data_length + index_length) / TABLE_ROWS
            FROM information_schema.TABLES
            WHERE TABLE_NAME = 'ledgers'
        ) / 1024 / 1024, 2
    ) AS estimated_mb
FROM ledgers
GROUP BY tenant_id
ORDER BY record_count DESC;
```

**アラート基準:**
- 単一テナントが総データの50%超: 将来的な分離検討
- テナント数 > 100: パーティション数増加を検討
- 総データサイズ > 50GB: 物理DB分離の再評価

### 2.5. Mroonga全文検索性能

```sql
-- 全文検索インデックスのサイズ
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    ROUND(STAT_VALUE * @@innodb_page_size / 1024 / 1024, 2) AS index_mb
FROM information_schema.INNODB_SYS_TABLESTATS
WHERE TABLE_NAME LIKE '%ledger%';

-- 検索クエリの実行時間は Laravel Telescope や APM ツールで監視
```

**アラート基準:**
- 全文検索の95%ile > 500ms: 警告
- 全文検索の95%ile > 1000ms: Meilisearch導入検討

## 3. 監視の実装方法

### 3.1. Laravel Telescope による監視（開発・ステージング）

```bash
# Telescope のインストール（既にインストール済みの場合はスキップ）
./vendor/bin/sail composer require laravel/telescope --dev
./vendor/bin/sail artisan telescope:install
./vendor/bin/sail artisan migrate

# Telescope の設定
# config/telescope.php で監視対象を調整
```

Telescope ダッシュボード: `http://localhost/telescope`

### 3.2. Laravel Pulse による本番監視（推奨）

```bash
# Pulse のインストール
./vendor/bin/sail composer require laravel/pulse
./vendor/bin/sail artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
./vendor/bin/sail artisan migrate

# 設定ファイル: config/pulse.php
# 監視対象: クエリ実行時間、例外、キュー処理時間など
```

Pulse ダッシュボード: `http://localhost/pulse`

### 3.3. Performance Schema の有効化（既に設定済み）

`docker/mroonga/mroonga.cnf` で設定済み：

```ini
performance_schema = ON
performance_schema_instrument = '%=ON'
```

### 3.4. Artisan コマンドによる定期監視

```bash
# カスタム監視コマンドの作成
./vendor/bin/sail artisan make:command MonitorDatabasePerformance
```

**実装例:** `app/Console/Commands/MonitorDatabasePerformance.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorDatabasePerformance extends Command
{
    protected $signature = 'db:monitor';
    protected $description = 'Monitor database performance metrics';

    public function handle()
    {
        // Buffer Pool ヒット率
        $hitRate = $this->getBufferPoolHitRate();
        if ($hitRate < 95) {
            Log::warning("Buffer Pool hit rate is low: {$hitRate}%");
            $this->warn("⚠️  Buffer Pool hit rate: {$hitRate}%");
        } else {
            $this->info("✓ Buffer Pool hit rate: {$hitRate}%");
        }

        // 遅いクエリの検出
        $slowQueries = $this->getSlowQueries();
        if ($slowQueries->isNotEmpty()) {
            Log::warning("Slow queries detected", $slowQueries->toArray());
            $this->warn("⚠️  {$slowQueries->count()} slow queries detected");
            $this->table(['Query', 'Avg Time (s)', 'Max Time (s)'], 
                $slowQueries->take(5)->toArray()
            );
        } else {
            $this->info("✓ No slow queries");
        }

        // テナント別データサイズ
        $tenantSizes = $this->getTenantSizes();
        $maxTenant = $tenantSizes->first();
        $totalRecords = $tenantSizes->sum('record_count');
        
        if ($maxTenant && ($maxTenant->record_count / $totalRecords) > 0.5) {
            Log::warning("Single tenant dominates data", [
                'tenant_id' => $maxTenant->tenant_id,
                'percentage' => ($maxTenant->record_count / $totalRecords) * 100
            ]);
            $this->warn("⚠️  Tenant {$maxTenant->tenant_id} has " . 
                round(($maxTenant->record_count / $totalRecords) * 100, 1) . "% of data");
        }

        return 0;
    }

    private function getBufferPoolHitRate(): float
    {
        $result = DB::selectOne("
            SELECT ROUND((
                SELECT VARIABLE_VALUE FROM performance_schema.global_status 
                WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'
            ) / (
                SELECT VARIABLE_VALUE FROM performance_schema.global_status 
                WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads'
            ) * 100, 2) AS hit_rate
        ");
        
        return $result->hit_rate ?? 0;
    }

    private function getSlowQueries()
    {
        return DB::table('performance_schema.events_statements_summary_by_digest')
            ->select([
                'DIGEST_TEXT as query',
                DB::raw('ROUND(AVG_TIMER_WAIT / 1000000000000, 3) as avg_seconds'),
                DB::raw('ROUND(MAX_TIMER_WAIT / 1000000000000, 3) as max_seconds')
            ])
            ->where('SCHEMA_NAME', config('database.connections.mysql.database'))
            ->where(DB::raw('AVG_TIMER_WAIT / 1000000000000'), '>', 0.5)
            ->orderByDesc('avg_seconds')
            ->limit(20)
            ->get();
    }

    private function getTenantSizes()
    {
        return DB::table('ledgers')
            ->select('tenant_id', DB::raw('COUNT(*) as record_count'))
            ->groupBy('tenant_id')
            ->orderByDesc('record_count')
            ->get();
    }
}
```

**スケジュール登録:** `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // 1時間ごとに監視
    $schedule->command('db:monitor')->hourly();
    
    // 監視結果をSlackに通知（オプション）
    // $schedule->command('db:monitor')->hourly()
    //          ->appendOutputTo(storage_path('logs/db-monitor.log'))
    //          ->emailOutputOnFailure('admin@example.com');
}
```

## 4. アラート基準まとめ

| メトリクス | 警告レベル | クリティカルレベル | アクション |
|:----------|:----------|:----------------|:----------|
| Buffer Pool ヒット率 | < 95% | < 90% | Buffer Pool サイズ増加 |
| クエリ95%ile | > 500ms | > 1000ms | クエリ最適化、インデックス追加 |
| テナント別クエリ | > 1秒が継続 | > 2秒が継続 | テナント固有の調査 |
| 全文検索95%ile | > 500ms | > 1000ms | Meilisearch導入検討 |
| データサイズ | > 50GB | > 100GB | 物理DB分離の再評価 |
| テナント偏り | 50%超 | 70%超 | データ分離検討 |
| CPU使用率 | > 70% | > 85% | スケールアップ検討 |

## 5. 定期レビュースケジュール

### 5.1. 日次モニタリング
- Buffer Pool ヒット率
- 遅いクエリのログ確認
- エラーログの確認

### 5.2. 週次レビュー
- クエリ性能トレンドの確認
- テナント別データ増加率
- パーティション効率の確認

### 5.3. 月次レポート
- 総データサイズの推移
- テナント数の推移
- 性能劣化の兆候がないか
- インフラコスト vs 性能のバランス確認

### 5.4. 四半期評価
- 物理DB分離の再検討判断
- インフラスケールアップの必要性
- 新技術導入（Meilisearch等）の検討

## 6. トラブルシューティング

### 6.1. Buffer Pool ヒット率が低い場合

```sql
-- 原因調査: どのテーブルが大きいか
SELECT 
    TABLE_NAME,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'ledgerleap'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
LIMIT 10;

-- 対策
-- 1. Buffer Pool サイズを増加（docker/mroonga/mroonga.cnf）
-- 2. 不要なインデックスの削除
-- 3. アーカイブデータの分離
```

### 6.2. 特定クエリが遅い場合

```sql
-- EXPLAINで実行計画を確認
EXPLAIN ANALYZE
SELECT * FROM ledgers WHERE tenant_id = 'xxx' AND status = 'APPROVED';

-- 対策
-- 1. 複合インデックスの追加
-- 2. クエリの書き換え
-- 3. キャッシュの活用
```

### 6.3. パーティションプルーニングが効いていない場合

```sql
-- WHERE句にtenant_idが含まれているか確認
EXPLAIN PARTITIONS
SELECT * FROM ledgers WHERE id = 123;  -- ×: 全パーティションスキャン

EXPLAIN PARTITIONS
SELECT * FROM ledgers WHERE tenant_id = 'xxx' AND id = 123;  -- ○: 1パーティションのみ
```

## 7. 外部監視ツールとの連携（将来検討）

### 7.1. APM ツール
- **New Relic**: フルスタック監視、トランザクション追跡
- **Datadog**: インフラ + APM 統合監視
- **Scout APM**: Laravel専用APM

### 7.2. ログ集約
- **Elasticsearch + Kibana**: ログ検索・可視化
- **Grafana Loki**: 軽量ログ集約

### 7.3. メトリクス可視化
- **Grafana + Prometheus**: メトリクスダッシュボード
- **Percona Monitoring and Management (PMM)**: MySQL専用監視

## 8. 関連ドキュメント

**DB アーキテクチャ検討:**
- [物理DB分離アーキテクチャ検討記録](../work/db-architecture-study/2025-10-09_physical-db-separation-architecture-study.md) - アーキテクチャ決定の背景
- [パーティショニング実装調査結果](../work/db-architecture-study/2025-10-11_partitioning-investigation-result.md) - 技術的制約の詳細分析
- [実装完了サマリー](../work/db-architecture-study/2025-10-11_implementation-summary.md) - 最終実装内容

**運用・デプロイ:**
- `docs/deployment/database-optimization.md` - 本番環境最適化ガイド（要作成）
- `docs/operations/incident-response.md` - インシデント対応手順（要作成）

**関連実装:**
- `database/migrations/2025_08_30_063606_add_tenant_id_to_tenant_tables.php` - tenant_idカラム・インデックス追加
- `docker/mroonga/my.cnf/mroonga.cnf` - MySQL Buffer Pool設定
- `docker-compose.yml` - コンテナ設定

## 9. 更新履歴

| 日付 | 更新内容 | 更新者 |
|:-----|:---------|:-------|
| 2025-10-11 | 初版作成 | GitHub Copilot CLI |
| 2025-10-11 | 相互リンク追加 | GitHub Copilot CLI |
