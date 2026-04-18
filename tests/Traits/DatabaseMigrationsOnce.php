<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     *
     * CI環境では migrate:fresh / tenants:migrate はワークフローで実行済みのためスキップし、
     * ci-test-tenant を再利用する。ローカルでは従来通りクラス初回に migrate:fresh を実行する。
     */
    protected function setUpDatabaseMigrationsOnce(): void
    {
        $this->resetTenantRuntimeState();
        $className = static::class;

        if (empty(static::$migratedOnceByClass[$className])) {
            if (env('CI')) {
                // CI: ワークフローで migrate --force / tenants:migrate 実行済み
                // ci-test-tenant を再利用してテナント初期化のみ行う
                //
                // ただし FolderTest (DatabaseMigrations) の tearDown() が migrate:rollback を
                // 実行して central DB の tenants テーブルを DROP する場合がある。
                // その場合は migrate を再実行してテーブルを復元してから Tenant を再作成する。
                if (! Schema::connection('mysql_testing')->hasTable('tenants')) {
                    $this->artisan('migrate', ['--force' => true]);
                    $this->app[Kernel::class]->setArtisan(null);
                    // テナント再作成後に tenants:migrate も実行
                    $newTenant = \App\Models\Tenant::firstOrCreate(['id' => 'ci-test-tenant']);
                    $this->artisan('tenants:migrate', [
                        '--tenants' => [$newTenant->id],
                        '--force' => true,
                    ]);
                    $this->app[Kernel::class]->setArtisan(null);
                    static::$sharedTenantForMigrationsOnce = $newTenant;
                } else {
                    $candidates = ['ci-test-tenant'];
                    foreach ($candidates as $id) {
                        $existing = \App\Models\Tenant::find($id);
                        if ($existing) {
                            static::$sharedTenantForMigrationsOnce = $existing;
                            break;
                        }
                    }
                    if (! static::$sharedTenantForMigrationsOnce) {
                        static::$sharedTenantForMigrationsOnce = \App\Models\Tenant::first()
                            ?? \App\Models\Tenant::factory()->create();
                    }
                }
            } else {
                // ローカル: クラスで初回だけ migrate:fresh を実行
                $this->artisan('migrate:fresh');
                $this->app[Kernel::class]->setArtisan(null);

                if (! static::$sharedTenantForMigrationsOnce) {
                    static::$sharedTenantForMigrationsOnce = \App\Models\Tenant::factory()->create();
                }
                $this->artisan('tenants:migrate', [
                    '--tenants' => [static::$sharedTenantForMigrationsOnce->id],
                ]);
                $this->app[Kernel::class]->setArtisan(null);
            }

            tenancy()->initialize(static::$sharedTenantForMigrationsOnce);
            static::$migratedOnceByClass[$className] = true;
        } else {
            // 2回目以降: CI で FolderTest の migrate:rollback により tenants テーブルが
            // DROP されている場合は static フラグをリセットして再初期化する
            if (env('CI') && ! Schema::connection('mysql_testing')->hasTable('tenants')) {
                static::$migratedOnceByClass[$className] = false;
                static::$sharedTenantForMigrationsOnce = null;
                $this->setUpDatabaseMigrationsOnce();

                return;
            }
            // テナントを再初期化するだけ
            tenancy()->initialize(static::$sharedTenantForMigrationsOnce);
        }
    }

    protected function resetTenantRuntimeState(): void
    {
        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        } catch (\Throwable) {
            // 次の初期化で再構築する
        }

        foreach (['tenant', 'mysql_testing'] as $connection) {
            try {
                DB::disconnect($connection);
            } catch (\Throwable) {
            }

            try {
                DB::purge($connection);
            } catch (\Throwable) {
            }
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
