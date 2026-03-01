<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * DatabaseMigrationsOnce トレイト
 *
 * Mroonga 全文検索テストなど、トランザクションロールバックでは代替できないテスト向け。
 *
 * 通常の DatabaseMigrations との違い:
 *   - DatabaseMigrations : 各テストメソッドごとに migrate:fresh を実行（テスト数 × 13秒）
 *   - DatabaseMigrationsOnce: テストクラスで 1 回だけ migrate:fresh を実行し、
 *                              各テスト後は TRUNCATE でデータをクリーンアップ（クラス単位で 13秒）
 *
 * なぜトランザクションではなく TRUNCATE か:
 *   Mroonga の全文検索インデックスはトランザクション外で更新される。
 *   トランザクションをロールバックしても Mroonga インデックスには残留データが残り、
 *   次テストに影響する。よって各テスト後に TRUNCATE でテーブルを完全にクリーンアップする。
 *
 * 使用方法:
 *   class MyTest extends TestCase
 *   {
 *       use DatabaseMigrationsOnce;
 *
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           $this->setUpDatabaseMigrationsOnce();
 *       }
 *
 *       protected function tearDown(): void
 *       {
 *           $this->tearDownDatabaseMigrationsOnce();
 *           parent::tearDown();
 *       }
 *   }
 */
trait DatabaseMigrationsOnce
{
    /** テストクラスで migrate:fresh を実行済みかどうかのフラグ（クラスごと） */
    protected static array $migratedOnceByClass = [];

    /** テストクラスで共有するテナント */
    protected static $sharedTenantForMigrationsOnce = null;

    /**
     * TRUNCATE 対象のテーブル一覧（テストクラスでオーバーライド可能）
     */
    protected function getTablesToTruncateForMigrationsOnce(): array
    {
        return [
            'ledgers',
            'ledger_chunks',
            'attached_files',
            'activity_log',
            'taggables',
            'tags',
        ];
    }

    /**
     * setUp() から呼び出す初期化メソッド
     */
    protected function setUpDatabaseMigrationsOnce(): void
    {
        $className = static::class;

        if (empty(static::$migratedOnceByClass[$className])) {
            // クラスで初回だけ migrate:fresh を実行
            $this->artisan('migrate:fresh');
            $this->app[Kernel::class]->setArtisan(null);

            // テナントを作成してテナントDBをマイグレーション
            if (! static::$sharedTenantForMigrationsOnce) {
                static::$sharedTenantForMigrationsOnce = \App\Models\Tenant::factory()->create();
            }
            tenancy()->initialize(static::$sharedTenantForMigrationsOnce);
            $this->artisan('tenants:migrate', [
                '--tenants' => [static::$sharedTenantForMigrationsOnce->id],
            ]);
            $this->app[Kernel::class]->setArtisan(null);

            static::$migratedOnceByClass[$className] = true;
        } else {
            // 2回目以降はテナントを再初期化するだけ
            tenancy()->initialize(static::$sharedTenantForMigrationsOnce);
        }
    }

    /**
     * tearDown() から呼び出すクリーンアップメソッド
     * Mroonga インデックスも含め完全にクリーンアップするため TRUNCATE を使用する
     */
    protected function tearDownDatabaseMigrationsOnce(): void
    {
        if (static::$sharedTenantForMigrationsOnce) {
            try {
                if (! tenancy()->initialized) {
                    tenancy()->initialize(static::$sharedTenantForMigrationsOnce);
                }
                $this->truncateTenantTablesForMigrationsOnce();
            } catch (\Throwable) {
                // 握りつぶす
            }
        }
    }

    /**
     * テナントテーブルを TRUNCATE する
     */
    private function truncateTenantTablesForMigrationsOnce(): void
    {
        $conn = DB::connection('tenant');
        $conn->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->getTablesToTruncateForMigrationsOnce() as $table) {
            if ($conn->getSchemaBuilder()->hasTable($table)) {
                $conn->table($table)->truncate();
            }
        }
        $conn->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
