<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * RefreshDatabaseWithTenant トレイト
 *
 * テスト実行プロセス全体で1回だけ migrate:fresh + tenants:migrate を行い、
 * 各テストケースはトランザクション内で実行されます。
 *
 * 特徴:
 * - プロセス全体で1回だけマイグレーション実行（高速化）
 * - テナントも1回だけ作成・初期化
 * - 各テストはトランザクション内で実行（ロールバックで副作用なし）
 * - テナントデータはトランザクション外なので永続化
 *
 * 使用方法:
 * ```php
 * class MyTest extends TestCase
 * {
 *     use RefreshDatabaseWithTenant;
 * }
 * ```
 */
trait RefreshDatabaseWithTenant
{
    /**
     * クラスごとにデータベースが初期化されたかを管理
     */
    protected static array $databaseInitializedByClass = [];

    /**
     * プロセス全体で migrate:fresh が実行済みかを管理するグローバルフラグ
     * 全クラスで共有し、1回のみ migrate:fresh を実行する
     */
    protected static bool $globalDatabaseMigrated = false;

    /**
     * 作成されたテナント（クラス全体で共有）
     */
    protected static $sharedTenant = null;

    /**
     * トランケート可能なテーブルのキャッシュ
     */
    protected static ?array $truncatableTablesCache = null;

    /**
     * テストクラスの最初の実行前に1回だけ初期化
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // クラスごとのフラグをリセット（グローバルフラグはリセットしない）
        $className = static::class;
        static::$databaseInitializedByClass[$className] = false;
    }

    /**
     * RefreshDatabaseWithTenant の初期化処理
     *
     * テストクラスのsetUp()から明示的に呼び出す必要があります：
     *
     * protected function setUp(): void
     * {
     *     parent::setUp();
     *     $this->setUpRefreshDatabaseWithTenant();
     *     // ... 残りのセットアップ
     * }
     */
    protected function setUpRefreshDatabaseWithTenant(): void
    {
        $className = static::class;
        $initialized = static::$databaseInitializedByClass[$className] ?? false;

        // このクラスで最初のテストの場合のみ初期化
        if (! $initialized) {
            // プロセス全体でまだマイグレーションが実行されていない場合のみ実行
            if (! static::$globalDatabaseMigrated) {
                $this->refreshDatabase();
                static::$globalDatabaseMigrated = true;
            }

            // テナントが存在しない場合のみ作成
            if (! static::$sharedTenant) {
                $this->createSharedTenant();
                tenancy()->initialize(static::$sharedTenant);
                $this->migrateTenantDatabase();
            } else {
                tenancy()->initialize(static::$sharedTenant);
            }

            // 共有データ（ユーザーなど）を作成
            $this->createSharedData();

            static::$databaseInitializedByClass[$className] = true;
        } else {
            // 2回目以降のテストではテナントを初期化
            if (static::$sharedTenant) {
                tenancy()->initialize(static::$sharedTenant);
            }
        }

        // トランザクションを開始（各テスト後に自動ロールバック）
        $this->beginDatabaseTransaction();
    }

    /**
     * 各テストの後処理
     *
     * tenancy()->end() を呼ぶテストの後でも次のテストが正常に動作するよう
     * テナントコンテキストを必ず復元する。
     */
    protected function tearDown(): void
    {
        // tenancy()->end() が呼ばれた場合に備えてテナントを再初期化
        if (static::$sharedTenant && ! tenancy()->initialized) {
            try {
                tenancy()->initialize(static::$sharedTenant);
            } catch (\Throwable) {
                // 初期化失敗は握りつぶす（次のテストの setUp で再試行される）
            }
        }

        // トランザクションロールバックは beforeApplicationDestroyed で自動実行される
        parent::tearDown();
    }

    /**
     * データベースのリフレッシュ（プロセス全体で1回のみ実行）
     *
     * - CI環境（CI=true）: migrate:fresh はスキップ。
     *   ワークフローの "Migrate database" ステップで既に実行済みのため、
     *   再実行すると全テーブル drop/recreate で60秒超かかる。
     * - ローカル環境: 従来通り migrate:fresh で完全リセット。
     */
    protected function refreshDatabase(): void
    {
        if (env('CI')) {
            // CI: ワークフローで migrate --force 実行済みのため何もしない
        } else {
            $this->artisan('migrate:fresh', [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
                '--seed' => $this->shouldSeed(),
            ]);

            $this->app[Kernel::class]->setArtisan(null);
        }
    }

    /**
     * テナントデータベースのマイグレーション（最初の1回のみ実行）
     *
     * テナント初期化後に実行される。
     * CI環境ではワークフローの "Setup test tenant" ステップで実行済みのためスキップ。
     */
    protected function migrateTenantDatabase(): void
    {
        if (env('CI')) {
            // CI: ワークフローで tenants:migrate 実行済みのため何もしない
        } else {
            $this->artisan('tenants:migrate', [
                '--tenants' => [static::$sharedTenant->id],
            ]);

            $this->app[Kernel::class]->setArtisan(null);
        }
    }

    /**
     * 共有テナントの作成（最初の1回のみ実行）
     *
     * CI環境ではワークフローの "Setup test tenant" ステップで ci-test-tenant が
     * 作成済みのため、それを再利用する。
     */
    protected function createSharedTenant(): void
    {
        if (env('CI')) {
            // CI: ワークフローで作成済みの ci-test-tenant を再利用
            $existing = \App\Models\Tenant::find('ci-test-tenant')
                ?? \App\Models\Tenant::first();
            if ($existing) {
                static::$sharedTenant = $existing;

                return;
            }
        }

        // ローカル: テナントを新規作成（トランザクション外なので永続化される）
        static::$sharedTenant = \App\Models\Tenant::factory()->create();
    }

    /**
     * トランザクションの開始
     */
    protected function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            try {
                // テナント接続の場合、テナントが初期化されているか確認
                if ($name === 'tenant' && ! tenancy()->initialized) {
                    continue;
                }

                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                $connection->beginTransaction();
                $connection->setEventDispatcher($dispatcher);
            } catch (\InvalidArgumentException $e) {
                // テナント接続が設定されていない場合はスキップ
                if ($name === 'tenant') {
                    continue;
                }
                throw $e;
            }
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                // テナント接続の場合、テナントが初期化されているか確認
                if ($name === 'tenant' && ! tenancy()->initialized) {
                    continue;
                }

                try {
                    $connection = $database->connection($name);
                    $dispatcher = $connection->getEventDispatcher();

                    $connection->unsetEventDispatcher();
                    $connection->rollBack();
                    $connection->setEventDispatcher($dispatcher);
                    $connection->disconnect();
                } catch (\InvalidArgumentException $e) {
                    // テナント接続が設定されていない場合はスキップ
                    if ($name === 'tenant') {
                        continue;
                    }
                    throw $e;
                }
            }
        });
    }

    /**
     * 共有テナントを取得
     */
    protected function getTenant()
    {
        return static::$sharedTenant;
    }

    /**
     * テナントテーブルをトランケートしてクリーンアップ
     *
     * 各テストの前に呼ばれ、前のテストのデータをクリーンアップします。
     * 共有データ（ユーザーなど）は保持されます。
     */
    protected function truncateTenantTables(): void
    {
        $connection = DB::connection('mysql');

        // 外部キー制約を一時的に無効化してトランケート
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        // トランケート対象のテーブルを取得
        $tablesToCheck = $this->getTablesToTruncate();

        if (empty($tablesToCheck)) {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');

            return;
        }

        foreach ($tablesToCheck as $table) {
            if ($connection->getSchemaBuilder()->hasTable($table)) {
                $connection->table($table)->truncate();
            }
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * トランケート対象のテーブル一覧を取得
     *
     * テストクラスでオーバーライドまたはプロパティで指定できます。
     * デフォルトでは最小限のテーブルのみトランケートします。
     *
     * @return array<string>
     */
    protected function getTablesToTruncate(): array
    {
        // テストクラスでプロパティが定義されている場合はそれを使用
        if (property_exists($this, 'tablesToTruncate')) {
            return $this->tablesToTruncate;
        }

        // デフォルトでは最小限のテーブルのみトランケート
        return [
            'personal_access_tokens', // トークンはクリーンアップ
        ];
    }

    /**
     * 共有データ（各テストで共通して使用するデータ）を作成
     *
     * テストクラスでオーバーライドして、共有データを作成できます。
     * デフォルトでは何も作成しません。
     */
    protected function createSharedData(): void
    {
        // テストクラスで必要に応じてオーバーライド
        // 例: テスト用ユーザーを作成して static::$sharedUser に保存
    }

    /**
     * トランザクション対象の接続名を取得
     *
     * セントラルとテナントの両方のデータベースに対してトランザクションを開始
     */
    protected function connectionsToTransact(): array
    {
        // プロパティで明示的に指定されている場合はそれを使用
        if (property_exists($this, 'connectionsToTransact')) {
            return $this->connectionsToTransact;
        }

        // セントラル（mysql_testing）とテナント接続の両方を使用
        return ['mysql_testing', 'tenant'];
    }

    /**
     * ビューを削除するかどうか
     */
    protected function shouldDropViews(): bool
    {
        return property_exists($this, 'dropViews') ? $this->dropViews : false;
    }

    /**
     * カスタム型を削除するかどうか
     */
    protected function shouldDropTypes(): bool
    {
        return property_exists($this, 'dropTypes') ? $this->dropTypes : false;
    }

    /**
     * シーディングを実行するかどうか
     */
    protected function shouldSeed(): bool
    {
        return property_exists($this, 'seed') ? $this->seed : false;
    }
}
